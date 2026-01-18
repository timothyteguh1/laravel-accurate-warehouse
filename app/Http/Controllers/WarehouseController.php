<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WarehouseController extends Controller
{
    // --- HELPER: GET TOKEN & SESSION ---
    private function getStoredToken() {
        if (Storage::exists('accurate_token.json')) {
            return json_decode(Storage::get('accurate_token.json'), true)['access_token'] ?? null;
        }
        return null;
    }

    private function getStoredSession() {
        if (Storage::exists('accurate_session.json')) {
            return json_decode(Storage::get('accurate_session.json'), true);
        }
        return null;
    }

    // --- DASHBOARD ---
    public function dashboard() { return view('warehouse.dashboard'); }

    // --- LIST SALES ORDER (DENGAN CROSS-CHECK DO) ---
    public function scanSO() {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();
        
        if (!$token) return redirect('/accurate/login');
        if (!$session) return redirect('/accurate/open-db');

        // 1. AMBIL LIST SO
        $urlSO = $session['host'] . '/accurate/api/sales-order/list.do';
        $resSO = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])->get($urlSO, [
            'fields' => 'id,number,transDate,customer,totalAmount,status',
            'sort' => 'transDate desc'
        ]);
        $listSO = $resSO->json()['d'] ?? [];

        // 2. AMBIL LIST DO TERBARU (Untuk "Kunci Jawaban" Status)
        // Ini trik agar status di Laravel langsung berubah "Diproses" meskipun API Accurate delay
        $urlDO = $session['host'] . '/accurate/api/delivery-order/list.do';
        $resDO = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])->get($urlDO, [
            'fields' => 'salesOrderDocNo', 
            'sort' => 'transDate desc',
            'pageSize' => 50 
        ]);
        $listDO = $resDO->json()['d'] ?? [];

        // Kumpulkan Nomor SO yang sudah punya DO
        $processedSONumbers = [];
        foreach ($listDO as $do) {
            if (!empty($do['salesOrderDocNo'])) {
                $processedSONumbers[] = $do['salesOrderDocNo'];
            }
        }

        return view('warehouse.scan-so', [
            'orders' => $listSO,
            'processed_numbers' => $processedSONumbers // Kirim data ini ke View
        ]);
    }

    // --- PROSES SCANNING ---
    public function scanProcess($id) {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();
        if (!$token || !$session) return redirect('/scan-so')->with('error', 'Sesi Habis');

        $url = $session['host'] . '/accurate/api/sales-order/detail.do';
        $so = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])->get($url, ['id' => $id])->json()['d'] ?? null;

        if (!$so) return redirect('/scan-so')->with('error', 'Gagal load data SO');
        if (($so['status'] ?? '') === 'CLOSED') return redirect('/scan-so')->with('error', 'SO Sudah Selesai (Closed)');

        // Cek Stok Gudang
        foreach ($so['detailItem'] as &$item) {
            $itemNo = $item['item']['no'];
            $stokRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $session['session']
            ])->get($session['host'] . '/accurate/api/item/list.do', [
                'fields' => 'quantity', 'filter.no.op' => 'EQUAL', 'filter.no.val' => $itemNo
            ])->json()['d'][0] ?? [];
            
            $item['stok_gudang'] = $stokRes['quantity'] ?? 0;
        }

        return view('warehouse.scan-process', ['so' => $so]);
    }

    // --- SUBMIT DO (LOGIKA "AMBIL PESANAN" - FIX FINAL) ---
    public function submitDeliveryOrder(Request $request) {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();
        if (!$token || !$session) return response()->json(['success' => false, 'message' => 'Sesi Invalid']);

        $host = $session['host'];
        $sessionId = $session['session'];
        
        // 1. Cari ID SO dulu
        $urlList = $host . '/accurate/api/sales-order/list.do';
        $resList = Http::withHeaders(['Authorization' => 'Bearer '.$token, 'X-Session-ID' => $sessionId])
            ->get($urlList, ['filter.number.op' => 'EQUAL', 'filter.number.val' => $request->so_number, 'fields' => 'id']);
        
        $soId = $resList->json()['d'][0]['id'] ?? null;
        if (!$soId) return response()->json(['success' => false, 'message' => 'SO Tidak Ditemukan.']);

        // 2. Ambil Detail SO Lengkap (Parent)
        $urlDetail = $host . '/accurate/api/sales-order/detail.do';
        $resDetail = Http::withHeaders(['Authorization' => 'Bearer '.$token, 'X-Session-ID' => $sessionId])
            ->get($urlDetail, ['id' => $soId]);
        
        $soData = $resDetail->json()['d'];
        
        // 3. Mapping Data: Simpan baris SO lengkap sebagai referensi
        $soLines = [];
        foreach($soData['detailItem'] as $line) {
            $itemCode = trim($line['item']['no']);
            $soLines[$itemCode] = $line;
        }

        $itemsScanned = $request->items; 
        $detailItemPayload = [];

        foreach ($itemsScanned as $sku => $qty) {
            if ((int)$qty > 0) {
                $cleanSku = trim($sku);

                // Cek: Apakah barang scan ada di SO?
                if (isset($soLines[$cleanSku])) {
                    $targetLine = $soLines[$cleanSku];

                    $detailItemPayload[] = [
                        'itemNo' => $cleanSku,
                        'quantity' => (int)$qty,
                        
                        // [RAHASIA SUKSES] 
                        // Kirim ID Baris SO (Seperti tombol "Ambil")
                        'salesOrderDetailId' => $targetLine['id'],
                        
                        // [EXTRA PROTEKSI]
                        // Kirim No SO di setiap baris agar Surat Jalan muncul No SO-nya
                        'salesOrderDocNo' => $request->so_number,

                        // [COPY PASTE]
                        // Salin Satuan dari SO agar Accurate tidak bingung
                        'itemUnit' => [
                            'name' => $targetLine['itemUnit']['name'] ?? 'PCS'
                        ]
                    ];
                } else {
                    return response()->json(['success' => false, 'message' => "Barang '$sku' tidak ada di SO ini!"]);
                }
            }
        }

        if (empty($detailItemPayload)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada barang valid untuk diproses.']);
        }

        // 4. Kirim DO
        $urlSave = $host . '/accurate/api/delivery-order/save.do';
        $res = Http::withHeaders(['Authorization' => 'Bearer '.$token, 'X-Session-ID' => $sessionId])
            ->post($urlSave, [
                'transDate' => date('d/m/Y'), 
                'customerNo' => $request->customer_no,
                'detailItem' => $detailItemPayload,
                'description' => 'DO Laravel (Link SO: ' . $request->so_number . ')'
            ])->json();

        if (isset($res['r'])) {
            return response()->json([
                'success' => true, 
                'do_number' => $res['r']['number'], 
                'do_id' => $res['r']['id']
            ]);
        } else {
            $errMsg = $res['d'] ?? 'Gagal Unknown';
            if(is_array($errMsg)) $errMsg = implode(', ', $errMsg);
            return response()->json(['success' => false, 'message' => $errMsg]);
        }
    }

    // --- PRINT DO ---
    public function printDeliveryOrder($id)
    {
        $token = $this->getStoredToken();
        $sessionData = $this->getStoredSession();

        if (!$token || !$sessionData) return response("Login ulang diperlukan.", 401);

        $host = $sessionData['host'];
        $sessionId = $sessionData['session'];
        
        $url = $host . '/accurate/api/delivery-order/detail.do';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $sessionId
            ])->get($url, ['id' => $id]);

            if ($response->successful()) {
                $data = $response->json();
                if(empty($data['d'])) return response("Data DO tidak ditemukan.", 404);
                
                return view('warehouse.print-do', ['do' => $data['d']]);
            } else {
                return response("Gagal ambil data DO: " . $response->body(), 500);
            }
        } catch (\Exception $e) {
            return response("Server Error: " . $e->getMessage(), 500);
        }
    }
    
    // Tools Dummy
    public function findAndPrintDO($soNumber) { return response("Fitur butuh DB.", 404); }
    public function generateDummyData() { dd("Dummy"); }
    public function fillDummyStock() { dd("Stock"); }
}