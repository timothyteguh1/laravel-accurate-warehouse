<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WarehouseController extends Controller
{
    // --- HELPER: GET TOKEN & SESSION ---
    private function getStoredToken()
    {
        if (Storage::exists('accurate_token.json')) {
            return json_decode(Storage::get('accurate_token.json'), true)['access_token'] ?? null;
        }
        return null;
    }

    private function getStoredSession()
    {
        if (Storage::exists('accurate_session.json')) {
            return json_decode(Storage::get('accurate_session.json'), true);
        }
        return null;
    }

    // --- DASHBOARD ---
    public function dashboard()
    {
        return view('warehouse.dashboard');
    }

    // --- LIST SALES ORDER ---
    public function scanSO()
    {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();

        if (!$token)
            return redirect('/accurate/login');
        if (!$session)
            return redirect('/accurate/open-db');

        // 1. AMBIL LIST SO
        $urlSO = $session['host'] . '/accurate/api/sales-order/list.do';
        $resSO = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])
            ->timeout(60) // Tambahan: Anti Timeout
            ->get($urlSO, [
                'fields' => 'id,number,transDate,customer,totalAmount,status',
                'sort' => 'transDate desc'
            ]);
        $listSO = $resSO->json()['d'] ?? [];

        // 2. AMBIL LIST DO TERBARU (Untuk Cek Status Lokal)
        $urlDO = $session['host'] . '/accurate/api/delivery-order/list.do';
        $resDO = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])
            ->timeout(60)
            ->get($urlDO, [
                'fields' => 'salesOrderDocNo',
                'sort' => 'transDate desc',
                'pageSize' => 50
            ]);
        $listDO = $resDO->json()['d'] ?? [];

        // Kumpulkan Nomor SO yang sudah ada DO-nya
        $processedSONumbers = [];
        foreach ($listDO as $do) {
            if (!empty($do['salesOrderDocNo'])) {
                $processedSONumbers[] = $do['salesOrderDocNo'];
            }
        }

        return view('warehouse.scan-so', [
            'orders' => $listSO,
            'processed_numbers' => $processedSONumbers
        ]);
    }

    // --- PROSES SCANNING ---
    public function scanProcess($id)
    {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();
        if (!$token || !$session)
            return redirect('/scan-so')->with('error', 'Sesi Habis');

        $url = $session['host'] . '/accurate/api/sales-order/detail.do';
        $so = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Session-ID' => $session['session']
        ])
            ->timeout(60)
            ->get($url, ['id' => $id])->json()['d'] ?? null;

        if (!$so)
            return redirect('/scan-so')->with('error', 'Gagal load data SO');
        // Backdoor: Tetap izinkan masuk meski status CLOSED/PROCESSED untuk pengecekan
        // if (($so['status'] ?? '') === 'CLOSED') return redirect('/scan-so')->with('error', 'SO Sudah Selesai (Closed)');

        // Cek Stok Gudang
        foreach ($so['detailItem'] as &$item) {
            $itemNo = $item['item']['no'];
            $stokRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $session['session']
            ])
                ->timeout(30)
                ->get($session['host'] . '/accurate/api/item/list.do', [
                    'fields' => 'quantity',
                    'filter.no.op' => 'EQUAL',
                    'filter.no.val' => $itemNo
                ])->json()['d'][0] ?? [];

            $item['stok_gudang'] = $stokRes['quantity'] ?? 0;
        }

        return view('warehouse.scan-process', ['so' => $so]);
    }

    public function submitDeliveryOrder(Request $request)
    {
        $token = $this->getStoredToken();
        $session = $this->getStoredSession();
        if (!$token || !$session)
            return response()->json(['success' => false, 'message' => 'Sesi Invalid']);

        $host = $session['host'];
        $sessionId = $session['session']; // Perbaikan: Ambil session ID dengan benar

        try {
            // 1. Validasi Input
            if (empty($request->items)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada barang yang di-scan.']);
            }

            // 2. Cari ID SO (Ambil ID aslinya dulu)
            $urlList = $host . '/accurate/api/sales-order/list.do';
            $resList = Http::withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Session-ID' => $sessionId])
                ->get($urlList, ['filter.number.op' => 'EQUAL', 'filter.number.val' => $request->so_number, 'fields' => 'id']);

            $soId = $resList->json()['d'][0]['id'] ?? null;
            if (!$soId)
                return response()->json(['success' => false, 'message' => 'SO Tidak Ditemukan.']);

            // 3. Ambil Detail SO Lengkap
            $urlDetail = $host . '/accurate/api/sales-order/detail.do';
            $resDetail = Http::withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Session-ID' => $sessionId])
                ->get($urlDetail, ['id' => $soId]);

            $soData = $resDetail->json()['d'];

            // ============================================================
            // PERBAIKAN LOGIC DI SINI (Sistem Antrean / Queue)
            // ============================================================

            // A. Kelompokkan baris SO ke dalam Array Bertingkat (Supaya item kembar tidak tertimpa)
            $soLinesMap = [];
            foreach ($soData['detailItem'] as $line) {
                $sku = trim($line['item']['no']);
                // Kita "push" ke dalam array, bukan menimpanya
                $soLinesMap[$sku][] = $line;
            }

            $detailItemPayload = [];
            $itemsScanned = $request->items; // Contoh: ['ITEM-A' => 10]

            // B. Proses Pencocokan Barang Scan vs Jatah di SO
            foreach ($itemsScanned as $sku => $qtyScan) {
                $qtyScan = (int) $qtyScan;
                $cleanSku = trim($sku);

                // Cek apakah barang ini ada di SO?
                if ($qtyScan > 0 && isset($soLinesMap[$cleanSku])) {

                    // Ambil daftar antrean baris untuk item ini
                    // (Reference "&" agar kita bisa update antrean jika perlu, tapi logic break cukup aman)
                    $availableLines = $soLinesMap[$cleanSku];

                    // Loop baris-baris SO untuk memenuhi permintaan Scan
                    foreach ($availableLines as $index => $targetLine) {
                        if ($qtyScan <= 0)
                            break; // Jika scan sudah habis dialokasikan, stop

                        // Hitung kapasitas baris ini (Quantity SO)
                        $maxQtyLine = (int) $targetLine['quantity'];

                        // Berapa yang mau kita ambil dari baris ini?
                        // Ambil yang paling kecil: Sisa Scan atau Jatah Baris SO
                        $qtyToTake = min($qtyScan, $maxQtyLine);

                        if ($qtyToTake > 0) {
                            $detailItemPayload[] = [
                                'itemNo' => $cleanSku,
                                'quantity' => $qtyToTake,

                                // KUNCI UTAMA LINKING
                                'salesOrderDetailId' => (int) $targetLine['id'],

                                // [WAJIB TAMBAH 1] HARGA (Penting untuk validasi nilai persediaan)
                                'unitPrice' => $targetLine['unitPrice'] ?? 0,

                                // [WAJIB TAMBAH 2] PAJAK (Sangat Krusial! Tanpa ini link sering putus)
                                'tax1' => isset($targetLine['tax1']) ? ['id' => $targetLine['tax1']['id']] : null,
                                'tax2' => isset($targetLine['tax2']) ? ['id' => $targetLine['tax2']['id']] : null,
                                'tax3' => isset($targetLine['tax3']) ? ['id' => $targetLine['tax3']['id']] : null,

                                // Copy Satuan
                                'itemUnit' => ['name' => $targetLine['itemUnit']['name'] ?? 'PCS'],

                                // Copy Gudang & Dept
                                'warehouse' => isset($targetLine['warehouse']) ? ['id' => $targetLine['warehouse']['id']] : null,
                                'department' => isset($targetLine['department']) ? ['id' => $targetLine['department']['id']] : null,
                                'project' => isset($targetLine['project']) ? ['id' => $targetLine['project']['id']] : null,

                                // [OPSIONAL] Serial Number (Jika barang pakai SN)
                                // 'detailSerialNumber' => ... (Logic ini butuh penanganan khusus jika ada SN)
                            ];

                            $qtyScan -= $qtyToTake;
                        }
                    }
                }
            }

            // Validasi Akhir: Apakah ada barang yang berhasil diproses?
            if (empty($detailItemPayload)) {
                return response()->json(['success' => false, 'message' => 'Gagal Proses: Item scan tidak cocok dengan SO (atau stok SO sudah habis).']);
            }

            // 4. Kirim DO ke Accurate
            $urlSave = $host . '/accurate/api/delivery-order/save.do';
            $payloadDO = [
                'transDate' => date('d/m/Y'),
                'customerNo' => $soData['customer']['customerNo'], // Pakai Customer dari SO asli
                'detailItem' => $detailItemPayload,
                'description' => 'DO Auto-Link Scan: ' . $request->so_number,

                // [TAMBAHAN] Pastikan Cabang Sesuai SO (Penting utk Multi-Branch)
                'branch' => isset($soData['branch']) ? ['id' => $soData['branch']['id']] : null,
            ];

            $res = Http::withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Session-ID' => $sessionId])
                ->post($urlSave, $payloadDO);

            $result = $res->json();

            if (isset($result['r'])) {
                return response()->json([
                    'success' => true,
                    'do_number' => $result['r']['number'],
                    'do_id' => $result['r']['id']
                ]);
            } else {
                // Handling Error Detail dari Accurate
                $errMsg = $result['d'] ?? 'Gagal Unknown';
                if (is_array($errMsg))
                    $errMsg = implode(', ', $errMsg);
                return response()->json(['success' => false, 'message' => 'Accurate Error: ' . $errMsg]);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
        }
    }
    // --- PRINT DO ---
    public function printDeliveryOrder($id)
    {
        $token = $this->getStoredToken();
        $sessionData = $this->getStoredSession();

        if (!$token || !$sessionData)
            return response("Login ulang diperlukan.", 401);

        $host = $sessionData['host'];
        $sessionId = $sessionData['session'];

        $url = $host . '/accurate/api/delivery-order/detail.do';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $sessionId
            ])
                ->timeout(60)
                ->get($url, ['id' => $id]);

            if ($response->successful()) {
                $data = $response->json();
                if (empty($data['d']))
                    return response("Data DO tidak ditemukan.", 404);

                return view('warehouse.print-do', ['do' => $data['d']]);
            } else {
                return response("Gagal ambil data DO: " . $response->body(), 500);
            }
        } catch (\Exception $e) {
            return response("Server Error: " . $e->getMessage(), 500);
        }
    }

    // Tools Dummy
    public function findAndPrintDO($soNumber)
    {
        return response("Fitur butuh DB.", 404);
    }
    public function generateDummyData()
    {
        dd("Dummy");
    }
    public function fillDummyStock()
    {
        dd("Stock");
    }
}