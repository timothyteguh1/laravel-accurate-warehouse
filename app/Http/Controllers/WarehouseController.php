<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    // --- HELPER: AMBIL TOKEN DARI DATABASE ---
    private function getAuthData() {
        $tokenRow = DB::table('accurate_tokens')->where('id', 1)->first();
        if (!$tokenRow || !$tokenRow->session) return null;
        
        return [
            'token' => $tokenRow->access_token,
            'session' => $tokenRow->session,
            'host' => $tokenRow->host
        ];
    }

    // 1. DASHBOARD
    public function dashboard()
    {
        $auth = $this->getAuthData();

        // Jika belum connect, kembalikan data kosong + flag isConnected = false
        if (!$auth) {
            return view('warehouse.dashboard', [
                'stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [],
                'chartData' => [],
                'isConnected' => false
            ]);
        }

        try {
            // STATISTIK: SO Menunggu (Status NOT CLOSED)
            $soRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/sales-order/list.do', [
                'fields' => 'id',
                'filter.status.op' => 'NOT_EQUAL', 
                'filter.status.val' => 'CLOSED',
                'sp.pageSize' => 1 
            ]);

            // STATISTIK: Pengiriman (DO) Hari Ini
            $doRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/delivery-order/list.do', [
                'fields' => 'id',
                'filter.transDate.op' => 'EQUAL',
                'filter.transDate.val' => date('d/m/Y'),
                'sp.pageSize' => 1
            ]);

            // STATISTIK: Total Item di Accurate
            $itemRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/item/list.do', [
                'fields' => 'id',
                'sp.pageSize' => 1
            ]);

            // DATA GRAFIK: 7 Hari Terakhir
            $chartLabels = [];
            $chartData = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $dateObj = now()->subDays($i);
                $dateStr = $dateObj->format('d/m/Y');
                $chartLabels[] = $dateObj->format('d M');

                $resChart = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $auth['token'], 
                    'X-Session-ID' => $auth['session']
                ])->get($auth['host'] . '/accurate/api/delivery-order/list.do', [
                    'fields' => 'id',
                    'filter.transDate.op' => 'EQUAL',
                    'filter.transDate.val' => $dateStr,
                    'sp.pageSize' => 1
                ]);
                
                $chartData[] = $resChart->json()['sp']['rowCount'] ?? 0;
            }

            return view('warehouse.dashboard', [
                'stats' => [
                    'pending_so' => $soRes->json()['sp']['rowCount'] ?? 0,
                    'today_do'   => $doRes->json()['sp']['rowCount'] ?? 0,
                    'total_items'=> $itemRes->json()['sp']['rowCount'] ?? 0,
                ],
                'chartLabels' => $chartLabels,
                'chartData' => $chartData,
                'isConnected' => true
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Error: " . $e->getMessage());
            return view('warehouse.dashboard', [
                'stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
                'chartLabels' => [],
                'chartData' => [],
                'isConnected' => false
            ]);
        }
    }

    // 2. SCAN SO LIST PAGE
    public function scanSOListPage()
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/auth')->with('warning', 'Koneksi Accurate terputus.');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/sales-order/list.do', [
                'fields' => 'id,number,transDate,customer,totalAmount,status',
                'sort' => 'transDate desc',
                'filter.status.op' => 'NOT_EQUAL', 
                'filter.status.val' => 'CLOSED'
            ]);
            
            $orders = $response->json()['d'] ?? [];
            return view('warehouse.scan-so', ['orders' => $orders]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal koneksi: ' . $e->getMessage());
        }
    }

    // 3. SCAN SO DETAIL PAGE
    public function scanSODetailPage($id)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/auth');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/sales-order/detail.do', ['id' => $id]);

            $so = $response->json()['d'] ?? null;
            if (!$so) return redirect('/scan-so')->with('error', 'Data SO tidak ditemukan.');

            // Cek Stok Real
            foreach ($so['detailItem'] as &$item) {
                $itemNo = $item['item']['no'];
                try {
                    $stokRes = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $auth['token'],
                        'X-Session-ID' => $auth['session']
                    ])->get($auth['host'] . '/accurate/api/item/list.do', [
                        'fields' => 'quantity,upcNo',
                        'filter.no.op' => 'EQUAL',    
                        'filter.no.val' => $itemNo
                    ]);
                    $dataBarang = $stokRes->json()['d'][0] ?? [];
                    $stokAsli = $dataBarang['quantity'] ?? 0;
                    
                    $item['barcode_asli'] = $dataBarang['upcNo'] ?? $itemNo;
                    $item['stok_gudang'] = $stokAsli;

                } catch (\Exception $e) {
                    $item['stok_gudang'] = 0; 
                    $item['barcode_asli'] = $itemNo;
                }
            }

            return view('warehouse.scan-process', ['so' => $so]);

        } catch (\Exception $e) {
            return redirect('/scan-so')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // 4. SUBMIT DO
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber = $request->so_number;
        $itemsScanned = $request->items; 
        $auth = $this->getAuthData();

        if (!$auth) return response()->json(['success' => false, 'message' => 'Sesi Accurate habis.']);

        try {
            // A. TARIK DATA SO ASLI
            $findSo = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->get($auth['host'] . '/accurate/api/sales-order/list.do', ['filter.number.op' => 'EQUAL', 'filter.number.val' => $soNumber]);
            
            $soId = $findSo->json()['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO ID tidak ditemukan.']);

            $soDetailRes = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->get($auth['host'] . '/accurate/api/sales-order/detail.do', ['id' => $soId]);
            
            $soData = $soDetailRes->json()['d'] ?? null;
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

                $resReady = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                    ->post($auth['host'] . '/accurate/api/sales-order/save.do', $payloadReady);
                
                if (!isset($resReady->json()['r']['id'])) {
                    return response()->json(['success' => false, 'message' => 'Gagal buat SO Ready: ' . json_encode($resReady->json())]);
                }
                $newSoReadyId = $resReady->json()['r']['id'];
                $newSoReadyNumber = $resReady->json()['r']['number'];

                // 2. BUAT SO BARU #2 (BACKORDER)
                if (!empty($itemsBackorder)) {
                    $payloadBack = $payloadReady;
                    $payloadBack['description'] = 'Split (Backorder) dari ' . $soNumber;
                    $payloadBack['detailItem'] = $this->formatDetailForSave($itemsBackorder);

                    Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                        ->post($auth['host'] . '/accurate/api/sales-order/save.do', $payloadBack);
                }

                // 3. HAPUS SO LAMA
                Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                    ->post($auth['host'] . '/accurate/api/sales-order/delete.do', ['id' => $soId]);

                // 4. SWITCH DATA KE SO BARU
                $soNumber = $newSoReadyNumber;
                $newSoDetailRes = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                    ->get($auth['host'] . '/accurate/api/sales-order/detail.do', ['id' => $newSoReadyId]);
                
                $soData = $newSoDetailRes->json()['d'];
                
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

            $res = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->post($auth['host'] . '/accurate/api/delivery-order/save.do', $payloadDO);
            
            $result = $res->json();

            if (isset($result['r'])) {
                // F. FORCE CLOSE SO
                try {
                    $closeUrl = $auth['host'] . '/accurate/api/sales-order/manual-close-order.do';
                    Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                        ->post($closeUrl, ['number' => $soNumber, 'orderClosed' => true]);
                } catch (\Exception $ex) { /* Ignore */ }

                DB::table('local_so_details')
                    ->where('so_number', $request->so_number)
                    ->update(['status' => 'CLOSED', 'updated_at' => now()]);

                return response()->json([
                    'success' => true,
                    'do_number' => $result['r']['number'],
                    'message' => $needsSplit ? 'Order dipecah & Backorder dibuat.' : 'Order terkirim full.'
                ]);
            } else {
                $errMsg = $result['d'] ?? 'Gagal Unknown';
                if(is_array($errMsg)) $errMsg = json_encode($errMsg);
                return response()->json(['success' => false, 'message' => 'Accurate Error (DO): ' . $errMsg]);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
    }

    // 5. PRINT DO
    public function printDeliveryOrder($id)
    {
        $auth = $this->getAuthData();
        if (!$auth) return response("Koneksi Accurate terputus.", 401);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/delivery-order/detail.do', ['id' => $id]);

            $data = $response->json();
            if (empty($data['d'])) return response("Data DO tidak ditemukan.", 404);

            return view('warehouse.print-do', ['do' => $data['d']]);
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 500);
        }
    }

    // 6. HISTORY PAGE
    public function historyDOPage(Request $request)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/auth');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/sales-order/list.do', [
                'fields' => 'id,number,transDate,customer,description,status,totalAmount', 
                'filter.status.op' => 'EQUAL',
                'filter.status.val' => 'CLOSED',
                'sort'   => 'transDate desc',
                'sp.pageSize' => 20,
                'sp.page' => $request->query('page', 1) 
            ]);

            $data = $response->json();
            $orders = $data['d'] ?? [];
            if (!is_array($orders)) $orders = [];
            $pagination = $data['sp'] ?? [];

            return view('warehouse.history-api', [
                'orders' => $orders,
                'page' => $pagination['page'] ?? 1
            ]);
        } catch (\Exception $e) {
            Log::error('History Error: ' . $e->getMessage());
            return redirect('/dashboard')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    // 7. SEARCH DO & PRINT
    public function searchAndPrintDO($soNumber)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/auth');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($auth['host'] . '/accurate/api/delivery-order/list.do', [
                'fields' => 'id,number,description',
                'sort'   => 'transDate desc',
                'sp.pageSize' => 100 
            ]);

            $doList = $response->json()['d'] ?? [];
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
                return response("<h2 style='color:red;text-align:center;'>Surat Jalan Tidak Ditemukan</h2><p style='text-align:center;'>Tidak ditemukan Delivery Order untuk SO: <b>$soNumber</b></p>", 404);
            }
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 500);
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
    
    // Stub
    public function generateDummyData() {}
    public function fillDummyStock() {}
}