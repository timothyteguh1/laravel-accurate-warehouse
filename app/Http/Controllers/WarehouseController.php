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

    public function refreshDashboard()
    {
        Cache::forget('dashboard_data');
        return redirect('/dashboard')->with('success', 'Data Dashboard berhasil diperbarui!');
    }

    // --- 1. DASHBOARD ---
    public function dashboard()
    {
        $isConnected = DB::table('accurate_tokens')->where('id', 1)->exists();
        if (!$isConnected) {
            return view('warehouse.dashboard', ['stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0], 'chartLabels' => [], 'chartData' => [], 'isConnected' => false]);
        }
        try {
            $data = Cache::remember('dashboard_data', 600, function () {
                $soRes = $this->accurate->get('sales-order/list.do', ['fields' => 'id', 'filter.status.op' => 'NOT_EQUAL', 'filter.status.val' => 'CLOSED', 'sp.pageSize' => 1]);
                $doRes = $this->accurate->get('delivery-order/list.do', ['fields' => 'id', 'filter.transDate.op' => 'EQUAL', 'filter.transDate.val' => date('d/m/Y'), 'sp.pageSize' => 1]);
                $itemRes = $this->accurate->get('item/list.do', ['fields' => 'id', 'sp.pageSize' => 1]);
                
                $chartLabels = []; $chartData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i);
                    $chartLabels[] = $d->format('d M');
                    $r = $this->accurate->get('delivery-order/list.do', ['fields' => 'id', 'filter.transDate.op' => 'EQUAL', 'filter.transDate.val' => $d->format('d/m/Y'), 'sp.pageSize' => 1]);
                    $chartData[] = $r['sp']['rowCount'] ?? 0;
                }
                return ['stats' => ['pending_so' => $soRes['sp']['rowCount'] ?? 0, 'today_do' => $doRes['sp']['rowCount'] ?? 0, 'total_items' => $itemRes['sp']['rowCount'] ?? 0], 'chartLabels' => $chartLabels, 'chartData' => $chartData];
            });
            return view('warehouse.dashboard', $data + ['isConnected' => true]);
        } catch (\Exception $e) {
            return view('warehouse.dashboard', ['stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0], 'chartLabels' => [], 'chartData' => [], 'isConnected' => false]);
        }
    }

    // --- 2. SCAN SO LIST (HANYA ANTRIAN MURNI - PROCEED DIBUANG) ---
    public function scanSOListPage(Request $request)
    {
        $params = [
            'fields' => 'id,number,transDate,customer,totalAmount,status',
            'sort'   => 'transDate desc',
            'filter.status.op' => 'NOT_EQUAL',
            'filter.status.val' => 'CLOSED',
        ];

        if ($request->has('search') && !empty($request->search)) {
            $params['filter.number.op'] = 'CONTAIN';
            $params['filter.number.val'] = $request->search;
        }

        $response = $this->accurate->get('sales-order/list.do', $params);
        if (isset($response['status']) && $response['status'] === false) return redirect('/accurate/auth')->with('warning', 'Koneksi bermasalah.');

        $orders = $response['d'] ?? [];
        if (!is_array($orders)) $orders = []; 

        // LOGIKA SPLIT: 
        // SO Lama -> Jadi PROCEED/CLOSED (Hidden).
        // SO Baru -> Jadi QUEUE (Show).
        $orders = array_values(array_filter($orders, function ($order) {
            if (!is_array($order) || !isset($order['status'])) return false;
            $s = strtoupper($order['status']);
            // Hanya tampilkan antrian yang benar-benar baru
            return in_array($s, ['QUEUE', 'OPEN', 'WAITING']); 
        }));

        if ($request->ajax()) return view('warehouse.partials.table-scan', ['orders' => $orders])->render();
        return view('warehouse.scan-so', ['orders' => $orders]);
    }

    // --- 3. DETAIL SCAN ---
    public function scanSODetailPage($id)
    {
        $response = $this->accurate->get('sales-order/detail.do', ['id' => $id]);
        $so = $response['d'] ?? null;
        if (!$so) return redirect('/scan-so')->with('error', 'Data tidak ditemukan');

        foreach ($so['detailItem'] as &$item) {
            $itemNo = $item['item']['no'];
            try {
                $stokRes = $this->accurate->get('item/list.do', ['fields' => 'quantity,upcNo', 'filter.no.op' => 'EQUAL', 'filter.no.val' => $itemNo]);
                $d = $stokRes['d'][0] ?? [];
                $item['barcode_asli'] = $d['upcNo'] ?? $itemNo;
                $item['stok_gudang'] = $d['quantity'] ?? 0;
            } catch (\Exception $e) {
                $item['stok_gudang'] = 0; $item['barcode_asli'] = $itemNo;
            }
        }
        return view('warehouse.scan-process', ['so' => $so]);
    }

    // --- 4. SUBMIT DO DENGAN LOGIKA SPLIT SO (PERBAIKAN API) ---
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber = $request->so_number;
        $itemsScanned = $request->items; // Array [SKU => QtyScan]

        Log::info("START SPLIT DO PROCESS: $soNumber", ['scanned' => $itemsScanned]);

        try {
            // A. AMBIL DATA SO
            $findSo = $this->accurate->get('sales-order/list.do', ['filter.number.op' => 'EQUAL', 'filter.number.val' => $soNumber]);
            $soId = $findSo['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO tidak ditemukan.']);

            $soDetailRes = $this->accurate->get('sales-order/detail.do', ['id' => $soId]);
            $soData = $soDetailRes['d'] ?? null;

            // B. HITUNG PEMISAHAN
            $itemsToShip = [];
            $itemsBackorder = [];
            $needsSplit = false;

            foreach ($soData['detailItem'] as $line) {
                $sku = $line['item']['no'];
                $qtyOrder = $line['quantity'];
                
                // Ambil Qty Scan
                $qtyScan = 0;
                if (isset($itemsScanned[$line['item']['upcNo'] ?? $sku])) {
                    $qtyScan = (int) $itemsScanned[$line['item']['upcNo'] ?? $sku];
                } elseif (isset($itemsScanned[$sku])) {
                    $qtyScan = (int) $itemsScanned[$sku];
                }

                if ($qtyScan > 0) {
                    // Barang Dikirim (Masuk ke SO Lama yang akan diedit)
                    $itemsToShip[] = [
                        'lineId' => $line['id'], 
                        'itemNo' => $sku,
                        'qty' => $qtyScan,
                        'unit' => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                        'price' => $line['unitPrice'],
                        'disc' => $line['itemDiscPercent'] ?? 0
                    ];

                    // Cek Sisa
                    if ($qtyScan < $qtyOrder) {
                        $needsSplit = true;
                        $itemsBackorder[] = [
                            'itemNo' => $sku,
                            'qty' => ($qtyOrder - $qtyScan),
                            'unit' => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                            'price' => $line['unitPrice'],
                            'disc' => $line['itemDiscPercent'] ?? 0
                        ];
                    }
                } else {
                    // Tidak discan sama sekali -> Full Backorder
                    $needsSplit = true;
                    $itemsBackorder[] = [
                        'itemNo' => $sku,
                        'qty' => $qtyOrder,
                        'unit' => is_array($line['itemUnit']) ? $line['itemUnit']['name'] : $line['itemUnit'],
                        'price' => $line['unitPrice'],
                        'disc' => $line['itemDiscPercent'] ?? 0
                    ];
                }
            }

            if (empty($itemsToShip)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada barang valid untuk dikirim.']);
            }

            // C. EKSEKUSI SPLIT (JIKA ADA SISA)
            if ($needsSplit && count($itemsBackorder) > 0) {
                
                // 1. BUAT SO BARU (Isi Sisa) -> Status otomatis QUEUE
                $newSOPayload = [
                    'transDate' => date('d/m/Y'),
                    'customerNo' => $soData['customer']['customerNo'],
                    'description' => "Split (Sisa) dari $soNumber. " . ($soData['description'] ?? ''),
                    'detailItem' => [],
                    'toAddress' => $soData['toAddress'] ?? '',
                    'taxable' => $soData['taxable'] ?? false,
                    'inclusiveTax' => $soData['inclusiveTax'] ?? false
                ];
                foreach ($itemsBackorder as $boItem) {
                    $newSOPayload['detailItem'][] = [
                        'itemNo' => $boItem['itemNo'],
                        'quantity' => $boItem['qty'],
                        'unitPrice' => $boItem['price'],
                        'itemUnit' => $boItem['unit'],
                        'itemDiscPercent' => $boItem['disc']
                    ];
                }
                if (isset($soData['branch'])) $newSOPayload['branch'] = ['id' => $soData['branch']['id']];
                
                // Simpan SO Baru
                $resNew = $this->accurate->post('sales-order/save.do', $newSOPayload);
                if (!isset($resNew['s']) || !$resNew['s']) {
                    return response()->json(['success' => false, 'message' => 'Gagal membuat SO Sisa. DO dibatalkan.']);
                }

                // 2. UPDATE SO LAMA (Shrink Qty & Hapus Item Tak Terkirim)
                $updateSOPayload = [
                    'id' => $soId,
                    'detailItem' => [],
                    'description' => $soData['description'] . " (Sudah di-split)"
                ];

                foreach ($soData['detailItem'] as $originalLine) {
                    $foundInShip = false;
                    foreach ($itemsToShip as $shipItem) {
                        if ($shipItem['lineId'] == $originalLine['id']) {
                            // Update Qty menjadi Qty Scan
                            // WAJIB SERTAKAN itemNo dan unitPrice meski update, sesuai docs
                            $updateSOPayload['detailItem'][] = [
                                'id' => $originalLine['id'],
                                'itemNo' => $shipItem['itemNo'],
                                'unitPrice' => $shipItem['price'],
                                'quantity' => $shipItem['qty']
                            ];
                            $foundInShip = true;
                            break;
                        }
                    }
                    if (!$foundInShip) {
                        // Barang tidak dikirim -> HAPUS dari SO Lama
                        // PERBAIKAN: Gunakan '_status' => 'DELETE' (Bukan action)
                        $updateSOPayload['detailItem'][] = [
                            'id' => $originalLine['id'],
                            'itemNo' => $originalLine['item']['no'], // Wajib ada
                            'unitPrice' => $originalLine['unitPrice'], // Wajib ada
                            '_status' => 'DELETE' // Syntax API Accurate yang benar
                        ];
                    }
                }
                
                // Eksekusi Update SO Lama
                $resUpdate = $this->accurate->post('sales-order/save.do', $updateSOPayload);
                
                // Validasi Update
                if (!isset($resUpdate['s']) || !$resUpdate['s']) {
                    // Jika gagal update SO lama, proses berhenti agar data tidak korup
                    return response()->json(['success' => false, 'message' => 'Gagal update SO Lama: ' . json_encode($resUpdate['d'] ?? '')]);
                }
            }

            // D. BUAT DO UNTUK SO LAMA (YANG SUDAH DISUSUTKAN)
            // Karena SO Lama qty-nya sudah == Scan, statusnya pasti TERPROSES/CLOSED.
            $doPayload = [
                'transDate' => date('d/m/Y'),
                'customerNo' => $soData['customer']['customerNo'],
                'description' => "DO Scan App: Mengirim $soNumber",
                'salesOrderNumber' => $soNumber, // Link ke SO Lama
                'toAddress' => $soData['toAddress'] ?? '',
                'taxable' => $soData['taxable'] ?? false,
                'inclusiveTax' => $soData['inclusiveTax'] ?? false
            ];
            
            // Mapping Detail DO
            $doPayload['detailItem'] = [];
            foreach ($itemsToShip as $shipItem) {
                $doPayload['detailItem'][] = [
                    'itemNo' => $shipItem['itemNo'],
                    'quantity' => $shipItem['qty'],
                    'itemUnit' => $shipItem['unit'],
                    'salesOrderNumber' => $soNumber,
                    'salesOrderDetailId' => $shipItem['lineId']
                ];
            }

            if (isset($soData['branch'])) $doPayload['branch'] = ['id' => $soData['branch']['id']];

            $res = $this->accurate->post('delivery-order/save.do', $doPayload);

            if (($res['s'] ?? false) == true) {
                Cache::forget('dashboard_data');
                
                $msg = $needsSplit ? "DO Terbit! Sisa barang sudah dipisah ke SO Baru." : "DO Terbit! Pesanan Selesai.";
                
                return response()->json([
                    'success' => true, 
                    'do_id' => $res['r']['id'], 
                    'do_number' => $res['r']['number'], 
                    'message' => $msg
                ]);
            } else {
                return response()->json(['success' => false, 'message' => json_encode($res['d'] ?? 'Gagal DO')]);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
    }

    // --- 5. HISTORY PAGE (SEMUA YANG SUDAH SELESAI/TERPROSES) ---
    public function historyDOPage(Request $request)
    {
        $params = [
            'fields' => 'id,number,transDate,customer,description,status,totalAmount',
            'sort' => 'transDate desc',
            'sp.pageSize' => 50,
            'sp.page' => $request->query('page', 1)
        ];

        if ($request->has('search') && !empty($request->search)) {
            $params['filter.number.op'] = 'CONTAIN';
            $params['filter.number.val'] = $request->search;
        }

        $response = $this->accurate->get('sales-order/list.do', $params);

        if (isset($response['status']) && $response['status'] === false) {
             if ($request->ajax()) return response()->json(['error' => 'Koneksi Accurate bermasalah'], 500);
             return redirect('/dashboard')->with('error', 'Koneksi terputus.');
        }

        $orders = $response['d'] ?? [];
        if (!is_array($orders)) $orders = []; 

        $page = $response['sp']['page'] ?? 1;

        // [FILTER HISTORY]
        // Tampilkan yang statusnya PROCEED atau CLOSED.
        // (Ini adalah SO Lama yang sudah selesai diproses/split)
        $orders = array_values(array_filter($orders, function ($order) {
            if (!is_array($order) || !isset($order['status'])) return false; 
            $s = strtoupper($order['status']);
            return in_array($s, ['PROCEED', 'PROCESSED', 'CLOSED']);
        }));

        if ($request->ajax()) return view('warehouse.partials.table-history', ['orders' => $orders])->render();
        return view('warehouse.history-api', ['orders' => $orders, 'page' => $page]);
    }

    // --- 6. PRINT DO ---
    public function printDeliveryOrder($id)
    {
        $response = $this->accurate->get('delivery-order/detail.do', ['id' => $id]);
        $data = $response['d'] ?? null;

        if (!$data) return response("Data DO tidak ditemukan.", 404);
        return view('warehouse.print-do', ['do' => $data]);
    }

    // --- 7. SEARCH DO ---
    public function searchAndPrintDO($soNumber)
    {
        $res = $this->accurate->get('delivery-order/list.do', [
            'fields' => 'id', 'sort' => 'transDate desc', 'sp.pageSize' => 10,
            'filter.description.op' => 'CONTAIN', 'filter.description.val' => $soNumber
        ]);
        
        if (!empty($res['d']) && is_array($res['d'])) {
            return $this->printDeliveryOrder($res['d'][0]['id']);
        }
        return response("Belum ada Surat Jalan untuk SO: $soNumber", 404);
    }
}