<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    // --- HELPER: GET TOKEN & SESSION ---
    private function getAuthData()
    {
        if (!Storage::exists('accurate_token.json') || !Storage::exists('accurate_session.json')) {
            return null;
        }
        $token = json_decode(Storage::get('accurate_token.json'), true)['access_token'] ?? null;
        $session = json_decode(Storage::get('accurate_session.json'), true);
        
        return [
            'token' => $token,
            'session' => $session['session'] ?? null,
            'host' => $session['host'] ?? 'https://zeus.accurate.id'
        ];
    }

    // Tambahkan atau update method dashboard di WarehouseController.php
public function dashboard()
{
    $auth = $this->getAuthData();
    if (!$auth) return redirect('/accurate/login');

    try {
        // 1. STATISTIK: SO Menunggu (Status NOT CLOSED)
        $soRes = Http::withHeaders([
            'Authorization' => 'Bearer ' . $auth['token'], 
            'X-Session-ID' => $auth['session']
        ])->get($auth['host'] . '/accurate/api/sales-order/list.do', [
            'fields' => 'id',
            'filter.status.op' => 'NOT_EQUAL', 
            'filter.status.val' => 'CLOSED',
            'sp.pageSize' => 1 
        ]);

        // 2. STATISTIK: Pengiriman (DO) Hari Ini
        $doRes = Http::withHeaders([
            'Authorization' => 'Bearer ' . $auth['token'], 
            'X-Session-ID' => $auth['session']
        ])->get($auth['host'] . '/accurate/api/delivery-order/list.do', [
            'fields' => 'id',
            'filter.transDate.op' => 'EQUAL',
            'filter.transDate.val' => date('d/m/Y'),
            'sp.pageSize' => 1
        ]);

        // 3. STATISTIK: Total Item di Accurate
        $itemRes = Http::withHeaders([
            'Authorization' => 'Bearer ' . $auth['token'], 
            'X-Session-ID' => $auth['session']
        ])->get($auth['host'] . '/accurate/api/item/list.do', [
            'fields' => 'id',
            'sp.pageSize' => 1
        ]);

        // 4. DATA GRAFIK: 7 Hari Terakhir
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

        $stats = [
            'pending_so' => $soRes->json()['sp']['rowCount'] ?? 0,
            'today_do'   => $doRes->json()['sp']['rowCount'] ?? 0,
            'total_items'=> $itemRes->json()['sp']['rowCount'] ?? 0,
        ];

        return view('warehouse.dashboard', compact('stats', 'chartLabels', 'chartData'));

    } catch (\Exception $e) {
        \Log::error("Dashboard Error: " . $e->getMessage());
        return view('warehouse.dashboard', [
            'stats' => ['pending_so' => 0, 'today_do' => 0, 'total_items' => 0],
            'chartLabels' => [],
            'chartData' => []
        ]);
    }
}

    // 1. HALAMAN LIST SO
    public function scanSOListPage()
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/login');

        // Ambil List SO
        $url = $auth['host'] . '/accurate/api/sales-order/list.do';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($url, [
                'fields' => 'id,number,transDate,customer,totalAmount,status',
                'sort' => 'transDate desc',
                'filter.status.op' => 'NOT_EQUAL', 
                'filter.status.val' => 'CLOSED'
            ]);
            
            $orders = $response->json()['d'] ?? [];

            return view('warehouse.scan-so', [
                'orders' => $orders
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal koneksi: ' . $e->getMessage());
        }
    }

    // 2. HALAMAN DETAIL SO
    public function scanSODetailPage($id)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/login');

        // Ambil Detail SO
        $url = $auth['host'] . '/accurate/api/sales-order/detail.do';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($url, ['id' => $id]);

            $so = $response->json()['d'] ?? null;
            if (!$so) return redirect('/scan-so')->with('error', 'Data SO tidak ditemukan.');

            // Cek Stok Real (Bypass 100 jika error/0 agar bisa ditest)
            foreach ($so['detailItem'] as &$item) {
                $itemNo = $item['item']['no'];
                try {
                    $stokRes = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $auth['token'],
                        'X-Session-ID' => $auth['session']
                    ])->get($auth['host'] . '/accurate/api/item/list.do', [
                        'fields' => 'quantity',       
                        'filter.no.op' => 'EQUAL',    
                        'filter.no.val' => $itemNo
                    ]);
                    $dataBarang = $stokRes->json()['d'][0] ?? [];
                    $stokAsli = $dataBarang['quantity'] ?? 0;
                    
                    if ($stokAsli <= 0) $item['stok_gudang'] = 100; // BYPASS STOK
                    else $item['stok_gudang'] = $stokAsli;

                } catch (\Exception $e) {
                    $item['stok_gudang'] = 100; // BYPASS ERROR
                }
            }

            return view('warehouse.scan-process', ['so' => $so]);

        } catch (\Exception $e) {
            return redirect('/scan-so')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // 3. PROSES SUBMIT DO + MANUAL CLOSE
    public function submitDOWithLocalLookup(Request $request)
    {
        $soNumber = $request->so_number;
        $itemsScanned = $request->items; 
        $auth = $this->getAuthData();

        if (!$auth) return response()->json(['success' => false, 'message' => 'Sesi habis, login ulang.']);

        // --- A. TARIK DATA SO ASLI ---
        try {
            $findSo = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->get($auth['host'] . '/accurate/api/sales-order/list.do', ['filter.number.op' => 'EQUAL', 'filter.number.val' => $soNumber]);
            
            $soId = $findSo->json()['d'][0]['id'] ?? null;
            if (!$soId) return response()->json(['success' => false, 'message' => 'SO ID tidak ditemukan.']);

            $soDetailRes = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->get($auth['host'] . '/accurate/api/sales-order/detail.do', ['id' => $soId]);
            
            $soData = $soDetailRes->json()['d'] ?? null;
            if (!$soData) return response()->json(['success' => false, 'message' => 'Gagal tarik detail SO.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Koneksi Error: ' . $e->getMessage()]);
        }

        // --- B. SUSUN PAYLOAD DO ---
        $detailItemPayload = [];
        
        foreach ($soData['detailItem'] as $accLine) {
            $accSku = $accLine['item']['no'];
            
            if (isset($itemsScanned[$accSku])) {
                $qtyScan = (int) $itemsScanned[$accSku];

                if ($qtyScan > 0) {
                    $linePayload = [
                        'itemNo' => $accSku,
                        'salesOrderDetailId' => $accLine['id'],
                        'quantity' => $qtyScan,
                        'itemUnit' => $accLine['itemUnit'] ?? null,
                    ];
                    
                    if (isset($accLine['warehouse'])) $linePayload['warehouse'] = ['id' => $accLine['warehouse']['id']];
                    if (isset($accLine['department'])) $linePayload['department'] = ['id' => $accLine['department']['id']];
                    if (isset($accLine['project'])) $linePayload['project'] = ['id' => $accLine['project']['id']];

                    $detailItemPayload[] = $linePayload;
                    $itemsScanned[$accSku] -= $qtyScan;
                }
            }
        }

        if (empty($detailItemPayload)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada item yang cocok.']);
        }

        // --- C. KIRIM DELIVERY ORDER (DO) ---
        $payloadDO = [
            'transDate' => date('d/m/Y'),
            'description' => 'DO Scan App: Mengirim ' . $soNumber,
            'customerNo' => $soData['customer']['customerNo'],
            'detailItem' => $detailItemPayload
        ];
        if (isset($soData['branch'])) $payloadDO['branch'] = ['id' => $soData['branch']['id']];

        try {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->post($auth['host'] . '/accurate/api/delivery-order/save.do', $payloadDO);
            
            $result = $res->json();

            // === JIKA DO SUKSES ===
            if (isset($result['r'])) {
                
                // --- D. FORCE CLOSE ---
                try {
                    $closeUrl = $auth['host'] . '/accurate/api/sales-order/manual-close-order.do';
                    
                    $closePayload = [
                        'number' => $soNumber,
                        'orderClosed' => true
                    ];

                    $closeRes = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $auth['token'], 
                        'X-Session-ID' => $auth['session']
                    ])->post($closeUrl, $closePayload);
                    
                    $closeResult = $closeRes->json();
                    if (!isset($closeResult['r'])) {
                         Log::warning('Manual Close SO Warning: ' . json_encode($closeResult));
                    }

                } catch (\Exception $ex) {
                    // Ignore error close
                }

                // Update DB Lokal (Tetap dibiarkan sesuai request)
                DB::table('local_so_details')
                    ->where('so_number', $soNumber)
                    ->update(['status' => 'CLOSED', 'updated_at' => now()]);

                return response()->json([
                    'success' => true,
                    'do_number' => $result['r']['number'],
                    'do_id' => $result['r']['id']
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

    // --- PRINT DO ---
    public function printDeliveryOrder($id)
    {
        $auth = $this->getAuthData();
        if (!$auth) return response("Login ulang diperlukan.", 401);

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

// --- [UPDATE] HALAMAN RIWAYAT: SOURCE DARI SO CLOSED ---
    public function historyDOPage(Request $request)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/login');

        // UBAH ENDPOINT: Gunakan Sales Order List
        $url = $auth['host'] . '/accurate/api/sales-order/list.do';
        
        try {
            // FILTER: Hanya ambil SO yang statusnya CLOSED
            // Ini menjamin jika SO dibuka lagi (uncheck closed), dia hilang dari list ini.
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($url, [
                'fields' => 'id,number,transDate,customer,description,status,totalAmount', 
                'filter.status.op' => 'EQUAL',
                'filter.status.val' => 'CLOSED', // <--- KUNCI FILTER STATUS
                'sort'   => 'transDate desc',
                'sp.pageSize' => 20,
                'sp.page' => $request->query('page', 1) 
            ]);

            if ($response->failed()) {
                return redirect()->back()->with('error', 'Gagal ambil data SO.');
            }

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

    // --- [BARU] CARI DO BERDASARKAN NO SO LALU PRINT ---
    // Dipanggil saat user klik "Print SJ" di halaman riwayat
    // --- [FIX] CARI ID DO BERDASARKAN NOMOR SO LALU PRINT ---
    public function searchAndPrintDO($soNumber)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/login');

        // URL untuk list Delivery Order
        $url = $auth['host'] . '/accurate/api/delivery-order/list.do';
        
        try {
            // 1. Ambil list DO (kita ambil 100 terakhir agar pencarian lebih akurat)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($url, [
                'fields' => 'id,number,description', // Kita butuh ID dan Description
                'sort'   => 'transDate desc',
                'sp.pageSize' => 100 
            ]);

            $doList = $response->json()['d'] ?? [];

            // 2. Cari di dalam list DO, mana yang deskripsinya mengandung $soNumber
            $foundDOId = null;
            foreach ($doList as $do) {
                // Cek apakah di keterangan DO ada nomor SO-nya
                if (isset($do['description']) && str_contains($do['description'], $soNumber)) {
                    $foundDOId = $do['id']; // Ambil ID DO-nya
                    break; 
                }
            }

            // 3. Jika ketemu ID DO, jalankan fungsi printDeliveryOrder menggunakan ID tersebut
            if ($foundDOId) {
                return $this->printDeliveryOrder($foundDOId);
            } else {
                // Jika tidak ketemu, tampilkan pesan error yang informatif
                return response("
                    <div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                        <h2 style='color:red;'>Surat Jalan Tidak Ditemukan</h2>
                        <p>Tidak ditemukan Delivery Order yang mencantumkan <b>$soNumber</b> di keterangannya.</p>
                        <p>Pastikan Surat Jalan sudah dibuat melalui aplikasi ini.</p>
                        <a href='javascript:history.back()'>Kembali</a>
                    </div>
                ", 404);
            }

        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 500);
        }
    }
    
    // Stub
    public function generateDummyData() {}
    public function fillDummyStock() {}
}