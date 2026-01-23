<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService; // Import Service
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    protected $accurate;

    // Inject Service
    public function __construct(AccurateService $accurate)
    {
        $this->accurate = $accurate;
    }

    // 1. DASHBOARD
    public function dashboard()
    {
        // Cek koneksi sederhana (bisa via DB atau coba request ringan)
        $isConnected = DB::table('accurate_tokens')->where('id', 1)->exists();

        if (!$isConnected) {
            return view('warehouse.dashboard', [
                'stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [], 'chartData' => [], 'isConnected' => false
            ]);
        }

        try {
            // Panggil API via Service
            $soRes = $this->accurate->get('sales-order/list.do', [
                'fields' => 'id', 'filter.status.op' => 'NOT_EQUAL', 'filter.status.val' => 'CLOSED', 'sp.pageSize' => 1
            ]);
            
            $doRes = $this->accurate->get('delivery-order/list.do', [
                'fields' => 'id', 'filter.transDate.op' => 'EQUAL', 'filter.transDate.val' => date('d/m/Y'), 'sp.pageSize' => 1
            ]);

            $itemRes = $this->accurate->get('item/list.do', [
                'fields' => 'id', 'sp.pageSize' => 1
            ]);

            // DATA GRAFIK: 7 Hari Terakhir
            $chartLabels = [];
            $chartData = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $dateObj = now()->subDays($i);
                $dateStr = $dateObj->format('d/m/Y');
                $chartLabels[] = $dateObj->format('d M');

                $resChart = $this->accurate->get('delivery-order/list.do', [
                    'fields' => 'id',
                    'filter.transDate.op' => 'EQUAL',
                    'filter.transDate.val' => $dateStr,
                    'sp.pageSize' => 1
                ]);
                
                $chartData[] = $resChart['sp']['rowCount'] ?? 0;
            }

            return view('warehouse.dashboard', [
                'stats' => [
                    'pending_so' => $soRes['sp']['rowCount'] ?? 0,
                    'today_do'   => $doRes['sp']['rowCount'] ?? 0,
                    'total_items'=> $itemRes['sp']['rowCount'] ?? 0,
                ],
                'chartLabels' => $chartLabels,
                'chartData' => $chartData,
                'isConnected' => true
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Error: " . $e->getMessage());
            return view('warehouse.dashboard', [
                'stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [], 'chartData' => [], 'isConnected' => false
            ]);
        }
    }

    // 2. SCAN SO LIST PAGE
    public function scanSOListPage()
    {
        $response = $this->accurate->get('sales-order/list.do', [
            'fields' => 'id,number,transDate,customer,totalAmount,status',
            'sort' => 'transDate desc',
            'filter.status.op' => 'NOT_EQUAL', 
            'filter.status.val' => 'CLOSED'
        ]);

        if (isset($response['status']) && $response['status'] === false) {
            return redirect('/accurate/auth')->with('warning', 'Koneksi Accurate terputus.');
        }

        return view('warehouse.scan-so', ['orders' => $response['d'] ?? []]);
    }

    // 3. SCAN SO DETAIL PAGE
    public function scanSODetailPage($id)
    {
        $response = $this->accurate->get('sales-order/detail.do', ['id' => $id]);
        $so = $response['d'] ?? null;

        if (!$so) return redirect('/scan-so')->with('error', 'Data SO tidak ditemukan.');

        // Cek Stok Real
        foreach ($so['detailItem'] as &$item) {
            $itemNo = $item['item']['no'];
            try {
                $stokRes = $this->accurate->get('item/list.do', [
                    'fields' => 'quantity,upcNo',
                    'filter.no.op' => 'EQUAL',    
                    'filter.no.val' => $itemNo
                ]);
                $dataBarang = $stokRes['d'][0] ?? [];
                
                $item['barcode_asli'] = $dataBarang['upcNo'] ?? $itemNo;
                $item['stok_gudang'] = $dataBarang['quantity'] ?? 0;

            } catch (\Exception $e) {
                $item['stok_gudang'] = 0; 
                $item['barcode_asli'] = $itemNo;
            }
        }

        return view('warehouse.scan-process', ['so' => $so]);
    }

    // 4. SUBMIT DO WITH LOGIC (SPLIT / BACKORDER)
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber = $request->so_number;
        $itemsScanned = $request->items; 

        try {
            // A. TARIK DATA SO ASLI
            $findSo = $this->accurate->get('sales-order/list.do', ['filter.number.op' => 'EQUAL', 'filter.number.val' => $soNumber]);
            $soId = $findSo['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO ID tidak ditemukan.']);

            $soDetailRes = $this->accurate->get('sales-order/detail.do', ['id' => $soId]);
            $soData = $soDetailRes['d'] ?? null;
            if (!$soData) return response()->json(['success' => false, 'message' => 'Gagal tarik detail SO.']);

            // B. CEK SPLIT
            $itemsReady = [];    
            $itemsBackorder = []; 
            $needsSplit = false;

            foreach ($soData['detailItem'] as $line) {
                $sku = $line['item']['no'];
                $barcode = $line['item']['upcNo'] ?? $sku; 
                $qtyOrder = (float) $line['quantity'];
                
                $qtyScan = 0;
                if (isset($itemsScanned[$barcode])) {
                    $qtyScan = (float) $itemsScanned[$barcode];
                } elseif (isset($itemsScanned[$sku])) {
                    $qtyScan = (float) $itemsScanned[$sku];
                }

                if ($qtyScan > 0) {
                    $lineReady = $line;
                    $lineReady['quantity'] = $qtyScan; 
                    unset($lineReady['id']); 
                    $itemsReady[] = $lineReady;
                }

                if ($qtyScan < $qtyOrder) {
                    $needsSplit = true;
                    $sisa = $qtyOrder - $qtyScan;
                    if ($sisa > 0) {
                        $lineBack = $line;
                        $lineBack['quantity'] = $sisa;
                        unset($lineBack['id']);
                        $itemsBackorder[] = $lineBack;
                    }
                }
            }

            if (empty($itemsReady)) return response()->json(['success' => false, 'message' => 'Tidak ada barang yang discan!']);

            // C. EKSEKUSI SPLIT
            if ($needsSplit) {
                // 1. BUAT SO BARU #1 (READY)
                $payloadReady = [
                    'transDate' => $soData['transDate'],
                    'customerNo' => $soData['customer']['customerNo'],
                    'description' => 'Split (Ready) dari ' . $soNumber,
                    'detailItem' => $this->formatDetailForSave($itemsReady)
                ];
                if(isset($soData['branch'])) $payloadReady['branch'] = ['id' => $soData['branch']['id']];
                if(isset($soData['poNumber'])) $payloadReady['poNumber'] = $soData['poNumber'];

                $resReady = $this->accurate->post('sales-order/save.do', $payloadReady);
                
                if (!isset($resReady['r']['id'])) {
                    return response()->json(['success' => false, 'message' => 'Gagal buat SO Ready.']);
                }
                $newSoReadyId = $resReady['r']['id'];
                $newSoReadyNumber = $resReady['r']['number'];

                // 2. BUAT SO BARU #2 (BACKORDER)
                if (!empty($itemsBackorder)) {
                    $payloadBack = $payloadReady;
                    $payloadBack['description'] = 'Split (Backorder) dari ' . $soNumber;
                    $payloadBack['detailItem'] = $this->formatDetailForSave($itemsBackorder);
                    $this->accurate->post('sales-order/save.do', $payloadBack);
                }

                // 3. HAPUS SO LAMA
                $this->accurate->post('sales-order/delete.do', ['id' => $soId]);

                // 4. SWITCH DATA KE SO BARU
                $soNumber = $newSoReadyNumber;
                $newSoDetailRes = $this->accurate->get('sales-order/detail.do', ['id' => $newSoReadyId]);
                $soData = $newSoDetailRes['d'];
                
                // Reset Scan Array agar sesuai dengan SO baru yang qty-nya sudah pas
                $itemsScanned = [];
                foreach($soData['detailItem'] as $ln) {
                    $itemsScanned[$ln['item']['no']] = $ln['quantity'];
                }
            }

            // D. SUSUN PAYLOAD DO
            $detailItemPayload = [];
            foreach ($soData['detailItem'] as $accLine) {
                $accSku = $accLine['item']['no'];
                $qtyToShip = 0;
                
                if ($needsSplit) {
                    $qtyToShip = $accLine['quantity'];
                } else {
                    $barcode = $accLine['item']['upcNo'] ?? $accSku;
                    if (isset($itemsScanned[$barcode])) {
                        $qtyToShip = (int) $itemsScanned[$barcode];
                    } elseif (isset($itemsScanned[$accSku])) {
                        $qtyToShip = (int) $itemsScanned[$accSku];
                    }
                }

                if ($qtyToShip > 0) {
                    $linePayload = [
                        'itemNo' => $accSku,
                        'salesOrderDetailId' => $accLine['id'],
                        'quantity' => $qtyToShip,
                        'itemUnit' => $accLine['itemUnit'] ?? null,
                    ];
                    
                    if (isset($accLine['warehouse'])) $linePayload['warehouse'] = ['id' => $accLine['warehouse']['id']];
                    if (isset($accLine['department'])) $linePayload['department'] = ['id' => $accLine['department']['id']];
                    if (isset($accLine['project'])) $linePayload['project'] = ['id' => $accLine['project']['id']];

                    $detailItemPayload[] = $linePayload;
                }
            }

            // E. KIRIM DO
            $payloadDO = [
                'transDate' => date('d/m/Y'),
                'description' => 'DO Scan App: Mengirim ' . $soNumber,
                'customerNo' => $soData['customer']['customerNo'],
                'detailItem' => $detailItemPayload
            ];
            if (isset($soData['branch'])) $payloadDO['branch'] = ['id' => $soData['branch']['id']];

            $result = $this->accurate->post('delivery-order/save.do', $payloadDO);

            if (isset($result['r'])) {
                // F. FORCE CLOSE SO
                try {
                    $this->accurate->post('sales-order/manual-close-order.do', ['number' => $soNumber, 'orderClosed' => true]);
                } catch (\Exception $ex) { /* Ignore */ }

                DB::table('local_so_details')
                    ->where('so_number', $request->so_number)
                    ->update(['status' => 'CLOSED', 'updated_at' => now()]);

                // [FIX PENTING] Kirim do_id agar tombol cetak tidak error 'undefined'
                return response()->json([
                    'success' => true,
                    'do_id' => $result['r']['id'],  // <--- WAJIB ADA
                    'do_number' => $result['r']['number'],
                    'message' => $needsSplit ? 'Order dipecah & Backorder dibuat.' : 'Order terkirim full.'
                ]);
            } else {
                return response()->json(['success' => false, 'message' => 'Accurate Error (DO): ' . json_encode($result['d'] ?? 'Unknown')]);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
    }

    // 5. PRINT DO
    public function printDeliveryOrder($id)
    {
        $response = $this->accurate->get('delivery-order/detail.do', ['id' => $id]);
        $data = $response['d'] ?? null;

        if (!$data) return response("Data DO tidak ditemukan.", 404);
        return view('warehouse.print-do', ['do' => $data]);
    }

    // 6. HISTORY PAGE
    public function historyDOPage(Request $request)
    {
        $response = $this->accurate->get('sales-order/list.do', [
            'fields' => 'id,number,transDate,customer,description,status,totalAmount', 
            'filter.status.op' => 'EQUAL',
            'filter.status.val' => 'CLOSED',
            'sort'   => 'transDate desc',
            'sp.pageSize' => 20,
            'sp.page' => $request->query('page', 1) 
        ]);

        if (isset($response['status']) && $response['status'] === false) {
            return redirect('/dashboard')->with('error', 'Koneksi terputus.');
        }

        return view('warehouse.history-api', [
            'orders' => $response['d'] ?? [],
            'page' => $response['sp']['page'] ?? 1
        ]);
    }

    // 7. SEARCH DO & PRINT
    public function searchAndPrintDO($soNumber)
    {
        $response = $this->accurate->get('delivery-order/list.do', [
            'fields' => 'id,number,description',
            'sort'   => 'transDate desc',
            'sp.pageSize' => 100 
        ]);

        $doList = $response['d'] ?? [];
        $foundDOId = null;
        
        foreach ($doList as $do) {
            if (isset($do['description']) && str_contains($do['description'], $soNumber)) {
                $foundDOId = $do['id'];
                break; 
            }
        }

        if ($foundDOId) {
            return $this->printDeliveryOrder($foundDOId);
        } else {
            return response("<h2>Surat Jalan Tidak Ditemukan</h2><p>Tidak ditemukan DO untuk SO: <b>$soNumber</b></p>", 404);
        }
    }

    // HELPER FORMAT SAVE
    private function formatDetailForSave($items) {
        $formatted = [];
        foreach ($items as $item) {
            $unitName = null;
            if (isset($item['itemUnit']) && is_array($item['itemUnit'])) {
                $unitName = $item['itemUnit']['name'];
            } elseif (isset($item['itemUnit']) && is_string($item['itemUnit'])) {
                $unitName = $item['itemUnit'];
            }

            $row = [
                'itemNo' => $item['item']['no'],
                'unitPrice' => $item['unitPrice'],
                'quantity' => $item['quantity'],
                'itemUnit' => $unitName, 
            ];

            if(isset($item['itemDiscPercent'])) $row['itemDiscPercent'] = $item['itemDiscPercent'];
            if(isset($item['warehouse'])) $row['warehouse'] = ['id' => $item['warehouse']['id']];
            if(isset($item['department'])) $row['department'] = ['id' => $item['department']['id']];
            if(isset($item['project'])) $row['project'] = ['id' => $item['project']['id']];
            
            $formatted[] = $row;
        }
        return $formatted;
    }
    
    public function generateDummyData() {}
    public function fillDummyStock() {}
}