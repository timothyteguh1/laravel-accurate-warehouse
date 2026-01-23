<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService; // Import Service Baru
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    protected $accurate;

    // Inject Service agar otomatis handle Token Database
    public function __construct(AccurateService $accurate)
    {
        $this->accurate = $accurate;
    }

    // 1. TAMPILKAN FORM (Ambil Data Barang)
    public function create()
    {
        // Panggil API via Service
        $response = $this->accurate->get('item/list.do', [
            'fields' => 'no,name,unitPrice',
            // 'itemType' => 'INVENTORY' // Uncomment jika ingin filter hanya barang stok
        ]);

        // Cek jika koneksi putus
        if (isset($response['status']) && $response['status'] === false) {
            return redirect('/accurate/auth')->with('warning', 'Koneksi Accurate terputus. Silakan hubungkan ulang.');
        }

        $items = $response['d'] ?? [];

        return view('sales_order.create', compact('items'));
    }

    // 2. PROSES SIMPAN SO & LINKING DATA
    public function store(Request $request)
    {
        // Siapkan Data Payload
        $payload = [
            "transDate" => date('d/m/Y'),
            "customerNo" => "P.00001", // Pastikan No Pelanggan ini valid/dinamis sesuai kebutuhan
            "detailItem" => [
                [
                    "itemNo" => $request->item_no,
                    "quantity" => (int)$request->quantity,
                    "unitPrice" => (float)$request->unit_price
                ]
            ]
        ];

        // Kirim Request POST via Service
        $result = $this->accurate->post('sales-order/save.do', $payload);

        // Cek Error Jika Request Gagal (API Error)
        if (!isset($result['s']) || $result['s'] == false) {
            // Tampilkan error (bisa dd atau redirect with error)
            return redirect()->back()->with('error', 'Gagal Simpan ke Accurate: ' . json_encode($result['d'] ?? 'Unknown Error'));
        }

        // JIKA SUKSES
        if ($result['s'] == true) {
            
            $soNumber = $result['r']['number']; 

            // Loop response untuk simpan 'Tiket Emas' (ID Detail) ke Database Lokal
            foreach ($result['r']['detailItem'] as $itemResponse) {
                
                $itemNumber = $itemResponse['item']['no'] ?? 'UNKNOWN'; 

                DB::table('local_so_details')->insert([
                    'so_number'          => $soNumber,
                    'item_no'            => $itemNumber, 
                    'accurate_detail_id' => $itemResponse['id'], // ID Penting untuk DO nanti
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