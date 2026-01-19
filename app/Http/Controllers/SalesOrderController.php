<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // <--- WAJIB ADA

class SalesOrderController extends Controller
{
    // --- FUNGSI BANTUAN UNTUK BACA JSON DARI STORAGE ---
    private function getAuthData()
    {
        // 1. Cek Token di Storage
        if (!Storage::exists('accurate_token.json')) {
            return null;
        }
        $tokenData = json_decode(Storage::get('accurate_token.json'), true);
        $accessToken = $tokenData['access_token'] ?? null;

        // 2. Cek Session di Storage
        if (!Storage::exists('accurate_session.json')) {
            return null;
        }
        $sessionData = json_decode(Storage::get('accurate_session.json'), true);
        
        $sessionDb = $sessionData['session'] ?? null; 
        $hostUrl   = $sessionData['host'] ?? 'https://zeus.accurate.id'; 

        return [
            'access_token' => $accessToken,
            'session_db'   => $sessionDb,
            'host'         => $hostUrl
        ];
    }

    // 1. TAMPILKAN FORM (Ambil Data Barang)
    public function create()
    {
        $auth = $this->getAuthData();

        // Validasi Token
        if (!$auth || !$auth['access_token'] || !$auth['session_db']) {
            return redirect('/accurate/login')->with('error', 'Sesi habis. Silakan Login Accurate ulang.');
        }

        // Request List Barang ke Accurate
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $auth['access_token'],
            'X-Session-ID'  => $auth['session_db'] 
        ])->get($auth['host'] . '/accurate/api/item/list.do', [
            'fields' => 'no,name,unitPrice',
            // 'itemType' => 'INVENTORY' // Uncomment jika ingin filter hanya barang stok
        ]);
        
        $items = [];
        if ($response->successful()) {
            // Untuk list barang, datanya biasanya masih ada di 'd'
            $items = $response->json()['d'] ?? [];
        } else {
            return dd('Gagal ambil barang:', $response->json());
        }

        return view('sales_order.create', compact('items'));
    }

    // 2. PROSES SIMPAN SO & LINKING DATA
    public function store(Request $request)
    {
        $auth = $this->getAuthData();

        if (!$auth) {
            return redirect('/accurate/login')->with('error', 'Token Invalid.');
        }

        // Siapkan Data Payload
        $payload = [
            "transDate" => date('d/m/Y'),
            "customerNo" => "P.00001", // Pastikan No Pelanggan ini valid di Accurate kamu
            "detailItem" => [
                [
                    "itemNo" => $request->item_no,
                    "quantity" => (int)$request->quantity,
                    "unitPrice" => (float)$request->unit_price
                ]
            ]
        ];

        // Kirim Request POST ke Accurate
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $auth['access_token'],
            'X-Session-ID'  => $auth['session_db']
        ])->post($auth['host'] . '/accurate/api/sales-order/save.do', $payload);

        $result = $response->json();

        // Cek Error Jika Request Gagal (API Error)
        if (!isset($result['s']) || $result['s'] == false) {
            return dd('Error dari Accurate (Gagal Save):', $result);
        }

        // JIKA SUKSES
        if ($result['s'] == true) {
            
            // [FIX] Ambil data dari 'r', bukan 'd'
            $soNumber = $result['r']['number']; 

            
            // Loop items response juga ambil dari 'r'
            foreach ($result['r']['detailItem'] as $itemResponse) {
                
                // [PERBAIKAN DISINI]
                // Cek dulu apakah ada object 'item', lalu ambil 'no'-nya
                $itemNumber = $itemResponse['item']['no'] ?? 'UNKNOWN'; 

                DB::table('local_so_details')->insert([
                    'so_number'          => $soNumber,
                    'item_no'            => $itemNumber, // Pakai variabel baru tadi
                    'accurate_detail_id' => $itemResponse['id'], // Tiket Emas
                    'quantity'           => $itemResponse['quantity'],
                    'status'             => 'OPEN',
                    'created_at'         => now(),
                    'updated_at'         => now()
                ]);
            }

            return redirect()->back()->with('success', 'SO Berhasil dibuat & Terhubung! No: ' . $soNumber);
        }
    }
}