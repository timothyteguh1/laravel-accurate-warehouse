<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WarehouseController extends Controller
{
    protected $accurate;

    public function __construct(AccurateService $accurate)
    {
        $this->accurate = $accurate;
    }

    // 1. DASHBOARD
    public function refreshDashboard()
    {
        Cache::forget('dashboard_data');
        return redirect('/dashboard')->with('success', 'Data Dashboard berhasil diperbarui!');
    }

    public function dashboard()
    {
        $isConnected = DB::table('accurate_tokens')->where('id', 1)->exists();

        if (!$isConnected) {
            return view('warehouse.dashboard', [
                'stats'       => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [],
                'chartData'   => [],
                'isConnected' => false,
            ]);
        }

        try {
            $data = Cache::remember('dashboard_data', 600, function () {
                $soRes = $this->accurate->get('sales-order/list.do', [
                    'fields'            => 'id',
                    'filter.status.op'  => 'EQUAL',
                    'filter.status.val' => 'QUEUE',
                    'sp.pageSize'       => 1,
                ]);
                $doRes = $this->accurate->get('delivery-order/list.do', [
                    'fields'               => 'id',
                    'filter.transDate.op'  => 'EQUAL',
                    'filter.transDate.val' => date('d/m/Y'),
                    'sp.pageSize'          => 1,
                ]);
                $itemRes = $this->accurate->get('item/list.do', [
                    'fields'      => 'id',
                    'sp.pageSize' => 1,
                ]);
                $chartLabels = [];
                $chartData   = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i);
                    $chartLabels[] = $d->format('d M');
                    $r = $this->accurate->get('delivery-order/list.do', [
                        'fields'               => 'id',
                        'filter.transDate.op'  => 'EQUAL',
                        'filter.transDate.val' => $d->format('d/m/Y'),
                        'sp.pageSize'          => 1,
                    ]);
                    $chartData[] = $r['sp']['rowCount'] ?? 0;
                }
                return [
                    'stats' => [
                        'pending_so'  => $soRes['sp']['rowCount']  ?? 0,
                        'today_do'    => $doRes['sp']['rowCount']   ?? 0,
                        'total_items' => $itemRes['sp']['rowCount'] ?? 0,
                    ],
                    'chartLabels' => $chartLabels,
                    'chartData'   => $chartData,
                ];
            });
            return view('warehouse.dashboard', $data + ['isConnected' => true]);
        } catch (\Exception $e) {
            return view('warehouse.dashboard', [
                'stats'       => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [],
                'chartData'   => [],
                'isConnected' => false,
            ]);
        }
    }

    // 2. SCAN SO LIST
    public function scanSOListPage(Request $request)
    {
        $params = [
            'fields'      => 'id,number,transDate,customer,totalAmount,status',
            'sort'        => 'transDate desc',
            'sp.pageSize' => 100,
        ];
        if ($request->filled('search')) {
            $params['filter.number.op']  = 'CONTAIN';
            $params['filter.number.val'] = $request->search;
        }

        $response = $this->accurate->get('sales-order/list.do', $params);
        if (isset($response['status']) && $response['status'] === false) {
            return redirect('/accurate/auth')->with('warning', 'Koneksi bermasalah.');
        }

        $allOrders = $response['d'] ?? [];
        if (!is_array($allOrders)) $allOrders = [];

        $queueOrders   = [];
        $waitingOrders = [];

        foreach ($allOrders as $order) {
            if (!is_array($order) || !isset($order['status'])) continue;
            $s = strtoupper($order['status']);
            if (in_array($s, ['QUEUE', 'OPEN'])) {
                $queueOrders[] = $order;
            } elseif ($s === 'WAITING') {
                $waitingOrders[] = $order;
            }
        }

        if ($request->ajax()) {
            return view('warehouse.partials.table-scan', ['orders' => $queueOrders])->render();
        }

        return view('warehouse.scan-so', [
            'orders'        => $queueOrders,
            'waitingOrders' => $waitingOrders,
        ]);
    }

    // 3. DETAIL SCAN (QUEUE & WAITING)
    public function scanSODetailPage($id)
    {
        $response = $this->accurate->get('sales-order/detail.do', ['id' => $id]);
        $so = $response['d'] ?? null;

        if (!$so) {
            return redirect('/scan-so')->with('error', 'Data SO tidak ditemukan.');
        }

        $isWaiting     = strtoupper($so['status'] ?? '') === 'WAITING';
        $shippedPerSku = $isWaiting ? $this->getShippedQtyBySO($so['number'], $so['id']) : [];

        foreach ($so['detailItem'] as &$item) {
            $sku = $item['item']['no'];
            try {
                $stokRes = $this->accurate->get('item/list.do', [
                    'fields'        => 'quantity,upcNo',
                    'filter.no.op'  => 'EQUAL',
                    'filter.no.val' => $sku,
                ]);
                $d = $stokRes['d'][0] ?? [];
                $item['barcode_asli'] = $d['upcNo']    ?? $sku;
                $item['stok_gudang']  = $d['quantity'] ?? 0;
            } catch (\Exception $e) {
                $item['barcode_asli'] = $sku;
                $item['stok_gudang']  = 0;
            }
            $item['qty_shipped']   = $shippedPerSku[$sku] ?? 0;
            $item['qty_remaining'] = max(0, $item['quantity'] - $item['qty_shipped']);
        }
        unset($item);

        return view('warehouse.scan-process', [
            'so'        => $so,
            'isWaiting' => $isWaiting,
        ]);
    }

    // Helper: akumulasi qty terkirim per SKU dari semua DO terhubung ke 1 SO
    // ─── Helper: akumulasi qty terkirim per SKU — 1-2 API call, bukan N+1 ───────
    //
    // Strategi: minta 'detailItem' langsung di fields list call.
    // Jika API mengembalikan detailItem (beberapa versi Accurate mendukung),
    // kita tidak perlu fetch detail per-DO → drastis kurangi jumlah API call.
    // Jika tidak ada detailItem di list, fallback ke individual detail call
    // tapi batasi maksimum 10 DO supaya tidak timeout.

    // ─── Helper: qty terkirim per SKU dari DO yang terhubung ke 1 SO ──────────────
    //
    // Problem lama: description CONTAIN terlalu lebar → DO dari SO lain ikut masuk
    // Fix: fetch detail tiap DO dan validasi salesOrderNumber-nya match persis
    //
    // Optimasi: coba minta detailItem + salesOrderNumber di list call dulu.
    // Jika API mengembalikan, tidak perlu fetch detail individual.
    // Jika tidak, fetch per-DO tapi validasi salesOrderNumber sebelum akumulasi.

private function getShippedQtyBySO(string $soNumber, $soId = null): array
{
    // 1. Jika $soId tidak terkirim, kita pancing ambil dari API agar lebih akurat
    if (!$soId) {
        $soReq = $this->accurate->get('sales-order/list.do', [
            'fields'            => 'id',
            'filter.number.op'  => 'EQUAL',
            'filter.number.val' => $soNumber
        ]);
        $soId = $soReq['d'][0]['id'] ?? null;
    }

    $dos = [];

    // 2. JARING UTAMA: Tarik data DO berdasarkan Relasi ID Database (Paling Akurat)
    if ($soId) {
        $doRes = $this->accurate->get('delivery-order/list.do', [
            'fields'                  => 'id,number,transDate,salesOrderId,salesOrder,detailItem',
            'filter.salesOrderId.op'  => 'EQUAL',
            'filter.salesOrderId.val' => $soId,
            'sp.pageSize'             => 50,
        ]);
        $dos = $doRes['d'] ?? [];
    }

    // 3. JARING CADANGAN: Jika versi API Accurate gagal memfilter salesOrderId
    if (empty($dos)) {
        $doRes = $this->accurate->get('delivery-order/list.do', [
            'fields'         => 'id,number,transDate,salesOrderId,salesOrder,detailItem',
            'filter.keyword' => $soNumber,
            'sp.pageSize'    => 50,
        ]);
        $dos = $doRes['d'] ?? [];
    }

    if (!is_array($dos)) $dos = [];
    $shipped = [];

    // 4. Proses pembedahan tiap baris barang menggunakan Relasi ID Internal
    foreach ($dos as $do) {
        if (!isset($do['id'])) continue;

        $doItems = $do['detailItem'] ?? null;

        // Jika API list tidak memberikan data barang, tembak API detail per DO
        if (empty($doItems)) {
            try {
                $detail  = $this->accurate->get('delivery-order/detail.do', ['id' => $do['id']]);
                $doItems = $detail['d']['detailItem'] ?? [];
            } catch (\Exception $e) {
                Log::warning("Gagal fetch DO detail id={$do['id']}: " . $e->getMessage());
                continue; 
            }
        }

        foreach ($doItems as $doItem) {
            // Ekstrak ID relasi SO dari detail baris barang ATAU dari header DO
            $lineSoId = $doItem['salesOrderId'] ?? $doItem['salesOrder']['id'] ?? $do['salesOrderId'] ?? $do['salesOrder']['id'] ?? null;
            $lineSoNum = $doItem['salesOrderNumber'] ?? $doItem['salesOrder']['number'] ?? $do['salesOrderNumber'] ?? $do['salesOrder']['number'] ?? null;

            // VALIDASI X-RAY: Cocokkan ID Internal atau Nomor SO-nya
            $isMatch = false;
            if ($soId && $lineSoId && $lineSoId == $soId) {
                $isMatch = true; // Lolos karena ID Database Cocok
            } elseif ($lineSoNum && $lineSoNum === $soNumber) {
                $isMatch = true; // Lolos karena Nomor Teks Cocok
            }

            // Jika lulus sensor, baru hitung kuantitasnya
            if ($isMatch) {
                $sku = $doItem['item']['no'] ?? null;
                if ($sku) {
                    $shipped[$sku] = ($shipped[$sku] ?? 0) + ($doItem['quantity'] ?? 0);
                }
            }
        }
    }

    return $shipped;
}
    // 4. SUBMIT DO — WAITING-aware split
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber     = $request->so_number;
        $itemsScanned = $request->items;
        $isWaiting    = (bool) $request->is_waiting;

        Log::info("START SUBMIT DO: $soNumber", ['isWaiting' => $isWaiting, 'scanned' => $itemsScanned]);

        try {
            // A. Cari SO
            $findSo = $this->accurate->get('sales-order/list.do', [
                'filter.number.op'  => 'EQUAL',
                'filter.number.val' => $soNumber,
            ]);
            $soId = $findSo['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO tidak ditemukan.']);

            $soDetailRes = $this->accurate->get('sales-order/detail.do', ['id' => $soId]);
            $soData      = $soDetailRes['d'] ?? null;
            if (!$soData) return response()->json(['success' => false, 'message' => 'Detail SO tidak bisa dimuat.']);

            // B. Hitung shipped (WAITING only)
            $shippedPerSku = $isWaiting ? $this->getShippedQtyBySO($soNumber, $soId) : [];

            // C. Kalkulasi split
            $itemsToShip    = [];
            $itemsBackorder = [];
            $needsSplit     = false;

            foreach ($soData['detailItem'] as $line) {
                $sku            = $line['item']['no'];
                $qtyTotal       = $line['quantity'];
                $alreadyShipped = $shippedPerSku[$sku] ?? 0;
                $effectiveQty   = max(0, $qtyTotal - $alreadyShipped);

                // Skip item yang sudah fully shipped sebelumnya
                if ($effectiveQty === 0) continue;

                // Resolusi barcode → qty scan
                $qtyScan = 0;
                $upc     = $line['item']['upcNo'] ?? null;
                if ($upc && isset($itemsScanned[$upc])) {
                    $qtyScan = (int) $itemsScanned[$upc];
                } elseif (isset($itemsScanned[$sku])) {
                    $qtyScan = (int) $itemsScanned[$sku];
                }

                if ($qtyScan > 0) {
                    $itemsToShip[] = [
                        'lineId' => $line['id'],
                        'itemNo' => $sku,
                        'qty'    => $qtyScan,
                        'unit'   => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                        'price'  => $line['unitPrice'],
                        'disc'   => $line['itemDiscPercent'] ?? 0,
                    ];
                    if ($qtyScan < $effectiveQty) {
                        $needsSplit       = true;
                        $itemsBackorder[] = [
                            'itemNo' => $sku,
                            'qty'    => $effectiveQty - $qtyScan,
                            'unit'   => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                            'price'  => $line['unitPrice'],
                            'disc'   => $line['itemDiscPercent'] ?? 0,
                        ];
                    }
                } else {
                    $needsSplit       = true;
                    $itemsBackorder[] = [
                        'itemNo' => $sku,
                        'qty'    => $effectiveQty,
                        'unit'   => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                        'price'  => $line['unitPrice'],
                        'disc'   => $line['itemDiscPercent'] ?? 0,
                    ];
                }
            }

            if (empty($itemsToShip)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada barang valid untuk dikirim.']);
            }

            // D. Split jika perlu
            if ($needsSplit && count($itemsBackorder) > 0) {
                // Buat SO Baru (sisa)
                $newSOPayload = [
                    'transDate'    => date('d/m/Y'),
                    'customerNo'   => $soData['customer']['customerNo'],
                    'description'  => 'Split (Sisa) dari ' . $soNumber . '. ' . ($soData['description'] ?? ''),
                    'toAddress'    => $soData['toAddress']    ?? '',
                    'taxable'      => $soData['taxable']      ?? false,
                    'inclusiveTax' => $soData['inclusiveTax'] ?? false,
                    'detailItem'   => [],
                ];
                foreach ($itemsBackorder as $bo) {
                    $newSOPayload['detailItem'][] = [
                        'itemNo'          => $bo['itemNo'],
                        'quantity'        => $bo['qty'],
                        'unitPrice'       => $bo['price'],
                        'itemUnit'        => $bo['unit'],
                        'itemDiscPercent' => $bo['disc'],
                    ];
                }
                if (isset($soData['branch'])) $newSOPayload['branch'] = ['id' => $soData['branch']['id']];

                $resNew = $this->accurate->post('sales-order/save.do', $newSOPayload);
                if (!isset($resNew['s']) || !$resNew['s']) {
                    return response()->json(['success' => false, 'message' => 'Gagal membuat SO Sisa. DO dibatalkan.']);
                }

                // Update SO Lama
                $updateSOPayload = [
                    'id'          => $soId,
                    'description' => ($soData['description'] ?? '') . ' (Sudah di-split)',
                    'detailItem'  => [],
                ];
                foreach ($soData['detailItem'] as $originalLine) {
                    $foundInShip = false;
                    foreach ($itemsToShip as $shipItem) {
                        if ($shipItem['lineId'] == $originalLine['id']) {
                            $updateSOPayload['detailItem'][] = [
                                'id'        => $originalLine['id'],
                                'itemNo'    => $shipItem['itemNo'],
                                'unitPrice' => $shipItem['price'],
                                'quantity'  => $shipItem['qty'],
                            ];
                            $foundInShip = true;
                            break;
                        }
                    }
                    if (!$foundInShip) {
                        $updateSOPayload['detailItem'][] = [
                            'id'        => $originalLine['id'],
                            'itemNo'    => $originalLine['item']['no'],
                            'unitPrice' => $originalLine['unitPrice'],
                            '_status'   => 'DELETE',
                        ];
                    }
                }
                $resUpdate = $this->accurate->post('sales-order/save.do', $updateSOPayload);
                if (!isset($resUpdate['s']) || !$resUpdate['s']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal update SO Lama: ' . json_encode($resUpdate['d'] ?? ''),
                    ]);
                }
            }

            // E. Buat DO
            $doPayload = [
                'transDate'        => date('d/m/Y'),
                'customerNo'       => $soData['customer']['customerNo'],
                'description'      => 'DO Scan App: Mengirim ' . $soNumber,
                'salesOrderNumber' => $soNumber,
                'toAddress'        => $soData['toAddress']    ?? '',
                'taxable'          => $soData['taxable']      ?? false,
                'inclusiveTax'     => $soData['inclusiveTax'] ?? false,
                'detailItem'       => [],
            ];
            foreach ($itemsToShip as $shipItem) {
                $doPayload['detailItem'][] = [
                    'itemNo'             => $shipItem['itemNo'],
                    'quantity'           => $shipItem['qty'],
                    'itemUnit'           => $shipItem['unit'],
                    'salesOrderNumber'   => $soNumber,
                    'salesOrderDetailId' => $shipItem['lineId'],
                ];
            }
            if (isset($soData['branch'])) $doPayload['branch'] = ['id' => $soData['branch']['id']];

            $res = $this->accurate->post('delivery-order/save.do', $doPayload);

            if (($res['s'] ?? false) === true) {
                Cache::forget('dashboard_data');
                return response()->json([
                    'success'   => true,
                    'do_id'     => $res['r']['id'],
                    'do_number' => $res['r']['number'],
                    'message'   => $needsSplit
                        ? 'DO Terbit! Sisa barang sudah dipisah ke SO Baru.'
                        : 'DO Terbit! Pesanan Selesai.',
                ]);
            }

            return response()->json(['success' => false, 'message' => json_encode($res['d'] ?? 'Gagal membuat DO')]);

        } catch (\Exception $e) {
            Log::error('SUBMIT DO ERROR: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
    }

    // 5. HISTORY DO
    public function historyDOPage(Request $request)
    {
        $params = [
            'fields'      => 'id,number,transDate,customer,description,status,totalAmount',
            'sort'        => 'transDate desc',
            'sp.pageSize' => 50,
            'sp.page'     => $request->query('page', 1),
        ];
        if ($request->filled('search')) {
            $params['filter.number.op']  = 'CONTAIN';
            $params['filter.number.val'] = $request->search;
        }

        $response = $this->accurate->get('sales-order/list.do', $params);

        if (isset($response['status']) && $response['status'] === false) {
            if ($request->ajax()) return response()->json(['error' => 'Koneksi bermasalah'], 500);
            return redirect('/dashboard')->with('error', 'Koneksi terputus.');
        }

        $orders = $response['d'] ?? [];
        if (!is_array($orders)) $orders = [];

        $orders = array_values(array_filter($orders, function ($order) {
            if (!is_array($order) || !isset($order['status'])) return false;
            return in_array(strtoupper($order['status']), ['PROCEED', 'PROCESSED', 'CLOSED']);
        }));

        $page = $response['sp']['page'] ?? 1;

        if ($request->ajax()) return view('warehouse.partials.table-history', ['orders' => $orders])->render();
        return view('warehouse.history-api', ['orders' => $orders, 'page' => $page]);
    }

    // 6. PRINT DO
    public function printDeliveryOrder($id)
    {
        $response = $this->accurate->get('delivery-order/detail.do', ['id' => $id]);
        $data = $response['d'] ?? null;
        if (!$data) return response('Data DO tidak ditemukan.', 404);
        return view('warehouse.print-do', ['do' => $data]);
    }

    // 7. SEARCH & PRINT DO
    public function searchAndPrintDO($soNumber)
    {
        $res = $this->accurate->get('delivery-order/list.do', [
            'fields'                 => 'id',
            'sort'                   => 'transDate desc',
            'sp.pageSize'            => 10,
            'filter.description.op'  => 'CONTAIN',
            'filter.description.val' => $soNumber,
        ]);
        if (!empty($res['d']) && is_array($res['d'])) {
            return $this->printDeliveryOrder($res['d'][0]['id']);
        }
        return response("Belum ada Surat Jalan untuk SO: $soNumber", 404);
    }

public function checkSoDoLink($soNumber)
{
    // 1. Ambil Data SO (Agar kita dapat $so['id'])
    $soRes = $this->accurate->get('sales-order/list.do', [
        'fields'             => 'id,number,transDate,customer,status',
        'filter.number.op'   => 'EQUAL',
        'filter.number.val'  => $soNumber,
    ]);
    
    $so = $soRes['d'][0] ?? null;
    
    if (!$so) {
        return "SO $soNumber tidak ditemukan di Accurate.";
    }

    // 2. Ambil Semua DO menggunakan Relasi ID SO (Ini yang bikin akurat!)
    $doRes = $this->accurate->get('delivery-order/list.do', [
        'fields'                  => 'id,number,transDate,description,status,salesOrderId,salesOrder,detailItem',
        'filter.salesOrderId.op'  => 'EQUAL',
        'filter.salesOrderId.val' => $so['id'],
        'sp.pageSize'             => 100,
    ]);

    $allFetchedDos = $doRes['d'] ?? [];

    // Fallback cadangan jika API Accurate gagal filter pakai ID
    if (empty($allFetchedDos)) {
        $doResFallback = $this->accurate->get('delivery-order/list.do', [
            'fields'         => 'id,number,transDate,description,status,salesOrderId,salesOrder,detailItem',
            'filter.keyword' => $soNumber, 
            'sp.pageSize'    => 100,
        ]);
        $allFetchedDos = $doResFallback['d'] ?? [];
    }

    $connectedDos = [];

    // 3. Filter ketat menggunakan relasi internal Accurate
    foreach ($allFetchedDos as $do) {
        if (!isset($do['id'])) continue;

        $doItems = $do['detailItem'] ?? null;

        // Jika data barang kosong di list, tarik detailnya agar tampil di layar Debug
        if (empty($doItems)) {
            try {
                $detail = $this->accurate->get('delivery-order/detail.do', ['id' => $do['id']]);
                $doItems = $detail['d']['detailItem'] ?? [];
                // Update array $do agar di View bisa di-looping
                $do['detailItem'] = $doItems;
            } catch (\Exception $e) {
                Log::warning("Gagal fetch DO detail id={$do['id']}: " . $e->getMessage());
            }
        }

        // Cek ID Header DO
        $headerSoId = $do['salesOrderId'] ?? $do['salesOrder']['id'] ?? null;
        $headerSoNumber = $do['salesOrder']['number'] ?? $do['salesOrderNumber'] ?? null;
        
        // Pengecekan via ID Database ATAU Nomor SO
        if (($headerSoId && $headerSoId == $so['id']) || ($headerSoNumber === $soNumber)) {
            $connectedDos[] = $do;
        } else {
            // Cek lebih dalam ke detail barangnya
            $isLinked = false;
            if (!empty($doItems)) {
                foreach ($doItems as $item) {
                    $itemSoId = $item['salesOrderId'] ?? $item['salesOrder']['id'] ?? null;
                    $itemSoNumber = $item['salesOrder']['number'] ?? $item['salesOrderNumber'] ?? null;
                    
                    if (($itemSoId && $itemSoId == $so['id']) || ($itemSoNumber === $soNumber)) {
                        $isLinked = true;
                        break;
                    }
                }
            }
            if ($isLinked) {
                $connectedDos[] = $do;
            }
        }
    }

    // 4. Kirim ke tampilan khusus
    return view('warehouse.debug-so-do', [
        'so' => $so,
        'dos' => $connectedDos
    ]);
}

}