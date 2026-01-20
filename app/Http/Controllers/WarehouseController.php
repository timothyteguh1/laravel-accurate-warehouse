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
    // app/Http/Controllers/WarehouseController.php

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

            // Cek Stok Real (FIX: JANGAN BYPASS LAGI)
            foreach ($so['detailItem'] as &$item) {
                $itemNo = $item['item']['no'];
                try {
                    $stokRes = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $auth['token'],
                        'X-Session-ID' => $auth['session']
                    ])->get($auth['host'] . '/accurate/api/item/list.do', [
                        'fields' => 'quantity,upcNo', // Ambil stok & barcode       
                        'filter.no.op' => 'EQUAL',    
                        'filter.no.val' => $itemNo
                    ]);
                    $dataBarang = $stokRes->json()['d'][0] ?? [];
                    $stokAsli = $dataBarang['quantity'] ?? 0;
                    
                    // 1. Simpan Barcode (Logic sebelumnya)
                    $item['barcode_asli'] = $dataBarang['upcNo'] ?? $itemNo;

                    // 2. FIX: Gunakan stok asli, jangan diubah jadi 100
                    // Hapus logika "if ($stokAsli <= 0) ..."
                    $item['stok_gudang'] = $stokAsli;

                } catch (\Exception $e) {
                    // Jika error koneksi/API, set 0 (aman), jangan 100
                    $item['stok_gudang'] = 0; 
                    $item['barcode_asli'] = $itemNo;
                }
            }

            return view('warehouse.scan-process', ['so' => $so]);

        } catch (\Exception $e) {
            return redirect('/scan-so')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // 3. PROSES SUBMIT DO + MANUAL CLOSE
    // File: app/Http/Controllers/WarehouseController.php

public function submitDOWithLocalLookup(Request $request)
{
    $soNumber = $request->so_number;
    $itemsScanned = $request->items; // Format: ['ITEM-001' => 5, 'ITEM-002' => 0]
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

    // --- B. CEK APAKAH PERLU SPLIT ORDER (BACKORDER)? ---
    $itemsReady = [];     // Untuk SO Baru #1 (Yang akan di-DO)
    $itemsBackorder = []; // Untuk SO Baru #2 (Sisa)
    $needsSplit = false;

    foreach ($soData['detailItem'] as $line) {
        $sku = $line['item']['no'];
        // Ambil barcode jika ada (untuk matching), jika tidak pakai SKU
        $barcode = $line['item']['upcNo'] ?? $sku; 
        
        $qtyOrder = (float) $line['quantity'];
        
        // Cek qty dari input scanner (mapping barcode/sku)
        // Prioritas cek barcode, kalau gak ada cek SKU
        $qtyScan = 0;
        if (isset($itemsScanned[$barcode])) {
            $qtyScan = (float) $itemsScanned[$barcode];
        } elseif (isset($itemsScanned[$sku])) {
            $qtyScan = (float) $itemsScanned[$sku];
        }

        // 1. Masukkan ke keranjang "Ready" (yang discan)
        if ($qtyScan > 0) {
            $lineReady = $line; // Copy semua properti (harga, diskon, dll)
            $lineReady['quantity'] = $qtyScan; 
            // Hapus ID lama agar dianggap item baru saat create SO
            unset($lineReady['id']); 
            $itemsReady[] = $lineReady;
        }

        // 2. Masukkan ke keranjang "Backorder" (Sisanya)
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

    if (empty($itemsReady)) {
        return response()->json(['success' => false, 'message' => 'Tidak ada barang yang discan!']);
    }

    // --- C. EKSEKUSI SPLIT (JIKA ADA BACKORDER) ---
    if ($needsSplit) {
        try {
            // 1. BUAT SO BARU #1 (READY)
            $payloadReady = [
                'transDate' => $soData['transDate'],
                'customerNo' => $soData['customer']['customerNo'],
                'description' => 'Split (Ready) dari ' . $soNumber,
                'detailItem' => $this->formatDetailForSave($itemsReady)
            ];
            // Copy field header penting lainnya
            if(isset($soData['branch'])) $payloadReady['branch'] = ['id' => $soData['branch']['id']];
            if(isset($soData['poNumber'])) $payloadReady['poNumber'] = $soData['poNumber'];

            $resReady = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->post($auth['host'] . '/accurate/api/sales-order/save.do', $payloadReady);
            
            if (!isset($resReady->json()['r']['id'])) {
                return response()->json(['success' => false, 'message' => 'Gagal buat SO Ready: ' . json_encode($resReady->json())]);
            }
            $newSoReadyId = $resReady->json()['r']['id'];
            $newSoReadyNumber = $resReady->json()['r']['number'];

            // 2. BUAT SO BARU #2 (BACKORDER) - Jika ada isinya
            if (!empty($itemsBackorder)) {
                $payloadBack = $payloadReady;
                $payloadBack['description'] = 'Split (Backorder) dari ' . $soNumber;
                $payloadBack['detailItem'] = $this->formatDetailForSave($itemsBackorder);

                Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                    ->post($auth['host'] . '/accurate/api/sales-order/save.do', $payloadBack);
            }

            // 3. HAPUS SO LAMA (ORIGINAL)
            Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->post($auth['host'] . '/accurate/api/sales-order/delete.do', ['id' => $soId]);

            // 4. UPDATE DATA REFERENSI UNTUK PROSES DO
            // Kita harus menukar soData dengan data dari SO Baru #1 agar DO mengacu ke SO baru
            $soNumber = $newSoReadyNumber;
            
            // Fetch ulang detail SO Baru #1 untuk mendapatkan salesOrderDetailId yang baru
            $newSoDetailRes = Http::withHeaders(['Authorization' => 'Bearer ' . $auth['token'], 'X-Session-ID' => $auth['session']])
                ->get($auth['host'] . '/accurate/api/sales-order/detail.do', ['id' => $newSoReadyId]);
            
            $soData = $newSoDetailRes->json()['d']; // Ganti soData lama dengan yang baru
            
            // Reset itemsScanned agar cocok dengan logic DO di bawah (karena SO baru qty-nya sudah pas dengan scan)
            // Kita set ulang itemsScanned supaya logic looping DO di bawah mengambil SEMUA item di SO baru ini
            $itemsScanned = [];
            foreach($soData['detailItem'] as $ln) {
                $itemsScanned[$ln['item']['no']] = $ln['quantity'];
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal Proses Split: ' . $e->getMessage()]);
        }
    }

    // --- D. SUSUN PAYLOAD DELIVERY ORDER (Sama seperti sebelumnya) ---
    // Logic ini sekarang aman karena $soData sudah menunjuk ke SO yang benar (Entah SO asli jika full, atau SO Ready jika split)
    $detailItemPayload = [];
    
    foreach ($soData['detailItem'] as $accLine) {
        $accSku = $accLine['item']['no'];
        
        // Logic match quantity
        $qtyToShip = 0;
        
        // Jika Split, kita ambil semua qty di SO Ready (karena sudah dipotong pas)
        // Jika Normal, kita ambil berdasarkan input scan
        if ($needsSplit) {
            $qtyToShip = $accLine['quantity'];
        } else {
            // Cek mapping barcode/sku
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
                'salesOrderDetailId' => $accLine['id'], // ID Line penting untuk link DO ke SO
                'quantity' => $qtyToShip,
                'itemUnit' => $accLine['itemUnit'] ?? null,
            ];
            
            if (isset($accLine['warehouse'])) $linePayload['warehouse'] = ['id' => $accLine['warehouse']['id']];
            if (isset($accLine['department'])) $linePayload['department'] = ['id' => $accLine['department']['id']];
            if (isset($accLine['project'])) $linePayload['project'] = ['id' => $accLine['project']['id']];

            $detailItemPayload[] = $linePayload;
        }
    }

    // --- E. KIRIM DELIVERY ORDER ---
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
            
            // --- F. FORCE CLOSE SO (YANG SUDAH DI-DO) ---
            try {
                $closeUrl = $auth['host'] . '/accurate/api/sales-order/manual-close-order.do';
                Http::withHeaders([
                    'Authorization' => 'Bearer ' . $auth['token'], 
                    'X-Session-ID' => $auth['session']
                ])->post($closeUrl, ['number' => $soNumber, 'orderClosed' => true]);

            } catch (\Exception $ex) { /* Ignore */ }

            // Update DB Lokal
            DB::table('local_so_details')
                ->where('so_number', $request->so_number) // Gunakan no asli request utk log
                ->update(['status' => 'CLOSED', 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'do_number' => $result['r']['number'],
                'do_id' => $result['r']['id'],
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

// Helper untuk memformat item saat save SO baru
private function formatDetailForSave($items) {
    $formatted = [];
    foreach ($items as $item) {
        $row = [
            'itemNo' => $item['item']['no'],
            'unitPrice' => $item['unitPrice'],
            'quantity' => $item['quantity'],
            'itemUnit' => $item['itemUnit'] ?? null,
        ];
        // Copy field lain jika ada
        if(isset($item['itemDiscPercent'])) $row['itemDiscPercent'] = $item['itemDiscPercent'];
        if(isset($item['warehouse'])) $row['warehouse'] = ['id' => $item['warehouse']['id']];
        if(isset($item['department'])) $row['department'] = ['id' => $item['department']['id']];
        if(isset($item['project'])) $row['project'] = ['id' => $item['project']['id']];
        
        $formatted[] = $row;
    }
    return $formatted;
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