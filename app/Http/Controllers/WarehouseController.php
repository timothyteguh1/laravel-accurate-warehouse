<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Driver;
use App\Models\Delivery;
use App\Services\OrinService;
use App\Models\DeliveryAudit;

class WarehouseController extends Controller
{
    protected $accurate;
    protected $orin;

    public function __construct(AccurateService $accurate, OrinService $orin)
    {
        $this->accurate = $accurate;
        $this->orin = $orin;
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

        $drivers = Driver::all(); // Tarik semua data sopir dari database lokal

        return view('warehouse.scan-process', [
            'so'        => $so,
            'isWaiting' => $isWaiting,
            'drivers'   => $drivers, // <--- Variabel ini dilempar ke Blade
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
    // 4. SUBMIT DO — Murni 1 SO banyak DO (Tanpa Split)
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber     = $request->so_number;
        $itemsScanned = $request->items;
        $isWaiting    = (bool) $request->is_waiting;

        Log::info("START SUBMIT DO: $soNumber", ['isWaiting' => $isWaiting, 'scanned' => $itemsScanned]);

        try {
            // A. Cari SO di Accurate
            $findSo = $this->accurate->get('sales-order/list.do', [
                'filter.number.op'  => 'EQUAL',
                'filter.number.val' => $soNumber,
            ]);
            $soId = $findSo['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO tidak ditemukan.']);

            $soDetailRes = $this->accurate->get('sales-order/detail.do', ['id' => $soId]);
            $soData      = $soDetailRes['d'] ?? null;
            if (!$soData) return response()->json(['success' => false, 'message' => 'Detail SO tidak bisa dimuat.']);

            // B. Hitung shipped (Untuk perhitungan lanjutan sisa WAITING)
            // Menggunakan fungsi getShippedQtyBySO presisi tinggi yang baru saja kita buat
            $shippedPerSku = $isWaiting ? $this->getShippedQtyBySO($soNumber, $soId) : [];

            // C. Kalkulasi barang yang akan dikirim di sesi DO ini
            $itemsToShip = [];
            $isPartial   = false; // Penanda apakah ini pengiriman sebagian

            foreach ($soData['detailItem'] as $line) {
                $sku            = $line['item']['no'];
                $qtyTotal       = $line['quantity'];
                $alreadyShipped = $shippedPerSku[$sku] ?? 0;
                $effectiveQty   = max(0, $qtyTotal - $alreadyShipped); // Sisa target asli

                // Skip jika item ini sudah beres dikirim di DO sebelumnya
                if ($effectiveQty === 0) continue;

                // Cek hasil scan dari frontend
                $qtyScan = 0;
                $upc     = $line['item']['upcNo'] ?? null;
                if ($upc && isset($itemsScanned[$upc])) {
                    $qtyScan = (int) $itemsScanned[$upc];
                } elseif (isset($itemsScanned[$sku])) {
                    $qtyScan = (int) $itemsScanned[$sku];
                }

                if ($qtyScan > 0) {
                    // Proteksi ganda: Jangan sampai kirim melebihi sisa target
                    $qtyScan = min($qtyScan, $effectiveQty);

                    $itemsToShip[] = [
                        'lineId' => $line['id'],
                        'itemNo' => $sku,
                        'qty'    => $qtyScan,
                        'unit'   => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                        'price'  => $line['unitPrice'],
                        'disc'   => $line['itemDiscPercent'] ?? 0,
                    ];

                    // Jika yang discan kurang dari sisa target, berarti ini DO Parsial
                    if ($qtyScan < $effectiveQty) {
                        $isPartial = true;
                    }
                } else {
                    // Ada sisa target, tapi tidak ikut di-scan hari ini -> otomatis Parsial
                    $isPartial = true;
                }
            }

            if (empty($itemsToShip)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada barang valid untuk dikirim.']);
            }

            // D. SPLIT SO DIHAPUS SEPENUHNYA! Kita biarkan Accurate yang mengatur status SO.

            // E. Langsung Buat DO
            $doPayload = [
                'transDate'        => date('d/m/Y'),
                'customerNo'       => $soData['customer']['customerNo'],
                // Tambahkan keterangan beda jika ini pengiriman sebagian
                'description'      => 'DO Scan App: Mengirim ' . $soNumber . ($isPartial ? ' (Parsial)' : ''),
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
                    // Ini kunci utamanya: Memberitahu Accurate bahwa DO ini melunasi baris SO yang mana
                    'salesOrderDetailId' => $shipItem['lineId'], 
                ];
            }
            if (isset($soData['branch'])) $doPayload['branch'] = ['id' => $soData['branch']['id']];

            // Tembak API Accurate
            $res = $this->accurate->post('delivery-order/save.do', $doPayload);

            if (($res['s'] ?? false) === true) {
                Cache::forget('dashboard_data');
                return response()->json([
                    'success'   => true,
                    'do_id'     => $res['r']['id'],
                    'do_number' => $res['r']['number'],
                    'message'   => $isPartial
                        ? 'DO Sebagian Terbit! SO akan berstatus WAITING di Accurate.'
                        : 'DO Terbit! Pesanan Selesai (Fully Shipped).',
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
// 8. AMBIL LIST DO UNTUK POP-UP SURAT JALAN
    public function getDoListBySo($soNumber)
{
    try {
        // A. Ambil SO ID dulu
        $soReq = $this->accurate->get('sales-order/list.do', [
            'fields'            => 'id',
            'filter.number.op'  => 'EQUAL',
            'filter.number.val' => $soNumber
        ]);
        $soId = $soReq['d'][0]['id'] ?? null;

        if (!$soId) {
            return response()->json(['success' => false, 'message' => 'Data SO tidak ditemukan.']);
        }

        // B. Tarik DO pakai filter salesOrderId
        $doRes = $this->accurate->get('delivery-order/list.do', [
            'fields'                  => 'id,number,transDate,salesOrderId,salesOrder,detailItem',
            'filter.salesOrderId.op'  => 'EQUAL',
            'filter.salesOrderId.val' => $soId,
            'sp.pageSize'             => 50,
            'sort'                    => 'transDate asc'
        ]);
        $dos = $doRes['d'] ?? [];

        // C. Fallback jika filter salesOrderId kosong
        if (empty($dos)) {
            $doResFallback = $this->accurate->get('delivery-order/list.do', [
                'fields'         => 'id,number,transDate,salesOrderId,salesOrder,detailItem',
                'filter.keyword' => $soNumber,
                'sp.pageSize'    => 50,
                'sort'           => 'transDate asc'
            ]);
            $dos = $doResFallback['d'] ?? [];
        }

        if (!is_array($dos)) $dos = [];

        // D. VALIDASI KETAT — sama seperti getShippedQtyBySO
        $validDos = [];

        foreach ($dos as $do) {
            if (!isset($do['id'])) continue;

            // Cek header DO dulu
            $headerSoId  = $do['salesOrderId'] ?? $do['salesOrder']['id'] ?? null;
            $headerSoNum = $do['salesOrder']['number'] ?? $do['salesOrderNumber'] ?? null;

            if (($headerSoId && $headerSoId == $soId) || ($headerSoNum === $soNumber)) {
                $validDos[] = [
                    'id'        => $do['id'],
                    'number'    => $do['number'],
                    'transDate' => $do['transDate'],
                ];
                continue;
            }

            // Jika header tidak cocok, cek ke detail baris barang
            $doItems = $do['detailItem'] ?? null;

            if (empty($doItems)) {
                try {
                    $detail  = $this->accurate->get('delivery-order/detail.do', ['id' => $do['id']]);
                    $doItems = $detail['d']['detailItem'] ?? [];
                } catch (\Exception $e) {
                    Log::warning("getDoListBySo: Gagal fetch detail DO id={$do['id']}");
                    continue;
                }
            }

            $isLinked = false;
            foreach ($doItems as $item) {
                $itemSoId  = $item['salesOrderId'] ?? $item['salesOrder']['id'] ?? null;
                $itemSoNum = $item['salesOrder']['number'] ?? $item['salesOrderNumber'] ?? null;

                if (($itemSoId && $itemSoId == $soId) || ($itemSoNum === $soNumber)) {
                    $isLinked = true;
                    break;
                }
            }

            if ($isLinked) {
                $validDos[] = [
                    'id'        => $do['id'],
                    'number'    => $do['number'],
                    'transDate' => $do['transDate'],
                ];
            }
        }

        if (empty($validDos)) {
            return response()->json(['success' => false, 'message' => 'Belum ada Surat Jalan (DO) yang terbit untuk SO ini.']);
        }

        return response()->json(['success' => true, 'data' => $validDos]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
}
   // 9. ASSIGN DRIVER (LOGISTIK)
    public function assignDriver(Request $request)
    {
        try {
            Delivery::create([
                'accurate_do_id'     => $request->do_id,
                'accurate_do_number' => $request->do_number,
                'driver_id'          => $request->driver_id,
                'status'             => 'Di Perjalanan', // Otomatis diset jalan
                
                // ─── TAMBAHAN TANGKAP DATA DARI FRONTEND ───
                'alamat_tujuan'      => $request->alamat_tujuan,
                'latitude'           => $request->latitude,
                'longitude'          => $request->longitude,
            ]);

            return response()->json(['success' => true, 'message' => 'Sopir berhasil ditugaskan!']);
        } catch (\Exception $e) {
            Log::error('ASSIGN DRIVER ERROR: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal assign sopir: ' . $e->getMessage()]);
        }
    }
    // 10. MONITORING SOPIR & ARMADA
 // 10. MONITORING SOPIR & ARMADA (Dengan Auto-Sync)
    public function driverMonitor()
    {
        // ─── 1. PROSES SINKRONISASI OTOMATIS ───
        // Ambil semua pengiriman lokal yang masih aktif (Belum Selesai)
        $activeDeliveries = Delivery::all();

        foreach ($activeDeliveries as $deliv) {
            try {
                // Cek ke Accurate apakah ID DO ini masih eksis
                $cekAccurate = $this->accurate->get('delivery-order/detail.do', ['id' => $deliv->accurate_do_id]);
                
                // Jika Accurate mengembalikan status false (berarti ID tidak ditemukan / sudah dihapus di Accurate)
                if (isset($cekAccurate['s']) && $cekAccurate['s'] === false) {
                    // Hapus data "hantu" ini dari database lokal kita
                    $deliv->delete();
                }
            } catch (\Exception $e) {
                // Jika error karena masalah koneksi internet/timeout, abaikan (jangan dihapus)
                Log::warning("Sync Check Error untuk DO {$deliv->accurate_do_number}: " . $e->getMessage());
            }
        }
        // ────────────────────────────────────────

        // ─── 2. TARIK DATA UNTUK TAMPILAN ───
        $drivers = Driver::with(['deliveries' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])->get();

        return view('warehouse.drivers', compact('drivers'));
    }
    // 11. UPDATE ALAMAT PENGIRIMAN
    public function updateAlamat(Request $request)
    {
        try {
            $delivery = Delivery::findOrFail($request->delivery_id);
            $delivery->update([
                'alamat_tujuan' => $request->alamat_tujuan,
                'latitude'      => $request->latitude,
                'longitude'     => $request->longitude,
            ]);

            return response()->json(['success' => true, 'message' => 'Alamat berhasil diperbarui!']);
        } catch (\Exception $e) {
            Log::error('UPDATE ALAMAT ERROR: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengupdate alamat: ' . $e->getMessage()]);
        }
    }
   // ─── TAHAP 3: API JEMBATAN LIVE TRACKING (VERSI AUTO-CENTER) ───
    public function getOrinLocation($sn)
    {
        // Gunakan taktik ambil semua data agar lebih stabil (seperti ORIN Fleet)
        $result = $this->orin->getAllDevices();

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => 'Gagal akses satelit'], 404);
        }

        $devices = $result['data']['data'] ?? $result['data'] ?? [];
        $foundDevice = null;

        foreach ($devices as $d) {
            $deviceSn = (string) ($d['device_sn'] ?? $d['sn'] ?? '');
            if ($deviceSn === (string)trim($sn)) {
                $foundDevice = $d;
                break;
            }
        }

        if (!$foundDevice) {
            return response()->json(['success' => false, 'message' => 'SN tidak ditemukan'], 404);
        }

        $loc = $foundDevice['last_location'] ?? $foundDevice;
        $speed = (float)($loc['speed'] ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'lat'    => $loc['lat'] ?? null,
                'lng'    => $loc['lng'] ?? null,
                'speed'  => $speed,
                'status' => $speed > 0 ? 'MOVING' : 'PARKING',
            ]
        ]);
    }
    // ─── TAHAP 2: KENDALI WAKTU PENGIRIMAN (TRACKING) ───

    public function startDelivery($id)
    {
        $delivery = \App\Models\Delivery::findOrFail($id);
        
        // Catat waktu berangkat saat ini
        $delivery->waktu_berangkat = now();
        $delivery->status = 'Di Perjalanan'; 
        $delivery->save();

        return back()->with('success', 'Status: Truk Berangkat. Pelacakan Live siap dimulai.');
    }

    public function endDelivery($id)
    {
        $delivery = \App\Models\Delivery::findOrFail($id);
        
        // Catat waktu kembali saat ini
        $delivery->waktu_kembali = now();
        $delivery->status = 'Selesai'; 
        $delivery->save();

        return back()->with('success', 'Status: Pengiriman Selesai. Riwayat perjalanan dikunci.');
    }
    // --- FUNGSI UPDATE SN ORIN DARI UI ---
    public function updateDriverSN(Request $request)
    {
        try {
            $driver = Driver::findOrFail($request->driver_id);
            $driver->update([
                'orin_device_sn' => trim($request->sn)
            ]);

            return response()->json(['success' => true, 'message' => 'SN GPS berhasil disimpan!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan SN: ' . $e->getMessage()]);
        }
    }

    public function getAuditData($deliveryId)
{
    try {
        $delivery = Delivery::with('driver')->findOrFail($deliveryId);
 
        // Cek apakah audit sudah pernah dibuat sebelumnya (draft)
        $existingAudit = DeliveryAudit::where('delivery_id', $deliveryId)->first();
 
        // Ambil detail DO dari Accurate untuk menampilkan info barang
        $doDetail = null;
        try {
            $res = $this->accurate->get('delivery-order/detail.do', [
                'id' => $delivery->accurate_do_id,
            ]);
            $doDetail = $res['d'] ?? null;
        } catch (\Exception $e) {
            Log::warning("Audit: Gagal ambil DO detail dari Accurate: " . $e->getMessage());
        }
 
        return response()->json([
            'success'        => true,
            'delivery'       => $delivery,
            'existing_audit' => $existingAudit,
            'do_detail'      => $doDetail,
        ]);
 
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
 
// ─────────────────────────────────────────────────────────────
// 13. SIMPAN AUDIT (DRAFT — bisa disimpan berkali-kali)
// ─────────────────────────────────────────────────────────────
public function saveAudit(Request $request)
{
    try {
        $data = [
            'pod_status'      => $request->pod_status,
            'pod_catatan'     => $request->pod_catatan,
 
            'ada_retur'       => (bool) $request->ada_retur,
            'retur_persen'    => $request->ada_retur ? (float) $request->retur_persen : 0,
            'retur_alasan'    => $request->ada_retur ? $request->retur_alasan : null,
            'retur_catatan'   => $request->retur_catatan,
 
            'uang_diberikan'  => (float) ($request->uang_diberikan ?? 0),
            'biaya_bbm'       => (float) ($request->biaya_bbm  ?? 0),
            'biaya_tol'       => (float) ($request->biaya_tol  ?? 0),
            'biaya_kuli'      => (float) ($request->biaya_kuli ?? 0),
            'biaya_lain'      => (float) ($request->biaya_lain ?? 0),
            'catatan_biaya'   => $request->catatan_biaya,
 
            'accurate_action' => $request->accurate_action ?? 'none',
            'status'          => 'draft',
        ];
 
        // Upsert — update jika sudah ada, buat baru jika belum
        $audit = DeliveryAudit::updateOrCreate(
            ['delivery_id' => $request->delivery_id],
            $data
        );
 
        return response()->json([
            'success'  => true,
            'audit_id' => $audit->id,
            'message'  => 'Draft audit berhasil disimpan.',
        ]);
 
    } catch (\Exception $e) {
        Log::error('SAVE AUDIT ERROR: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
 
// ─────────────────────────────────────────────────────────────
// 14. APPROVE AUDIT (FINALISASI — tidak bisa di-undo)
// ─────────────────────────────────────────────────────────────
public function approveAudit(Request $request, $auditId)
{
    try {
        $audit    = DeliveryAudit::findOrFail($auditId);
        $delivery = $audit->delivery;
 
        if ($audit->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'Audit ini sudah pernah di-approve.']);
        }
 
        $accurateResult = null;
 
        // ── Eksekusi aksi ke Accurate (jika dipilih) ────────────────────────
        if ($audit->accurate_action !== 'none') {
            try {
                if ($audit->accurate_action === 'close_do') {
                    // Tutup DO di Accurate
                    $res = $this->accurate->post('delivery-order/close.do', [
                        'id' => $delivery->accurate_do_id,
                    ]);
                    $accurateResult = $res;
 
                } elseif ($audit->accurate_action === 'create_invoice') {
                    // Ambil detail DO dulu untuk data Invoice
                    $doRes  = $this->accurate->get('delivery-order/detail.do', ['id' => $delivery->accurate_do_id]);
                    $doData = $doRes['d'] ?? null;
 
                    if ($doData) {
                        // Buat Sales Invoice dari DO
                        $invoicePayload = [
                            'transDate'              => date('d/m/Y'),
                            'customerNo'             => $doData['customer']['customerNo'],
                            'description'            => 'Invoice otomatis dari Audit: ' . $delivery->accurate_do_number,
                            'deliveryOrderNumber'    => $delivery->accurate_do_number,
                            'detailItem'             => array_map(function ($item) {
                                return [
                                    'itemNo'   => $item['item']['no'],
                                    'quantity' => $item['quantity'],
                                    'itemUnit' => is_array($item['itemUnit']) ? $item['itemUnit']['name'] : $item['itemUnit'],
                                    'unitPrice'=> $item['unitPrice'],
                                ];
                            }, $doData['detailItem'] ?? []),
                        ];
                        if (isset($doData['branch'])) {
                            $invoicePayload['branch'] = ['id' => $doData['branch']['id']];
                        }
 
                        $res = $this->accurate->post('sales-invoice/save.do', $invoicePayload);
                        $accurateResult = $res;
                    }
                }
            } catch (\Exception $e) {
                Log::error("AUDIT ACCURATE ACTION ERROR: " . $e->getMessage());
                // Jangan batalkan approve hanya karena API Accurate gagal — tetap approve, simpan error-nya
                $accurateResult = ['error' => $e->getMessage()];
            }
        }
 
        // ── Finalisasi audit di database lokal ──────────────────────────────
        $audit->update([
            'status'          => 'approved',
            'audited_by'      => auth()->user()->name ?? 'Admin',
            'approved_at'     => now(),
            'accurate_result' => $accurateResult,
        ]);
 
        // Tandai pengiriman sebagai sudah diaudit
        $delivery->update(['status' => 'Selesai & Diaudit']);
 
        Cache::forget('dashboard_data');
 
        $pesan = 'Audit berhasil di-approve!';
        if ($accurateResult && !isset($accurateResult['error'])) {
            $pesan .= ' Aksi ke Accurate berhasil dijalankan.';
        } elseif ($accurateResult && isset($accurateResult['error'])) {
            $pesan .= ' (Catatan: Aksi Accurate gagal — ' . $accurateResult['error'] . ')';
        }
 
        return response()->json([
            'success' => true,
            'message' => $pesan,
        ]);
 
    } catch (\Exception $e) {
        Log::error('APPROVE AUDIT ERROR: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
    
}