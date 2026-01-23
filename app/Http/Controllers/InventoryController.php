<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService; // PENTING: Panggil Service Baru
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    protected $accurate;

    // Inject Service agar otomatis handle Token dari Database
    public function __construct(AccurateService $accurate)
    {
        $this->accurate = $accurate;
    }

    public function index(Request $request)
    {
        // 1. Panggil API via Service (Otomatis handle header & token)
        $response = $this->accurate->get('item/list.do', [
            // Field yang diminta: No, Nama, Stok, Harga Jual, Jenis Barang
            'fields' => 'id,no,name,quantity,unitPrice,itemType,unit1Name',
            'sp.pageSize' => 50,
            'sp.page' => $request->query('page', 1)
        ]);

        // 2. Cek apakah Token Expired / Koneksi Putus
        if (isset($response['status']) && $response['status'] === false) {
             // Opsional: Redirect ke auth jika benar-benar putus, tapi dengan pesan jelas
             return redirect('/accurate/auth')->with('warning', 'Koneksi Accurate terputus. Silakan hubungkan ulang.');
        }

        $items = $response['d'] ?? [];
        $pagination = $response['sp'] ?? [];

        // 3. Tampilkan View (Tidak akan mental ke dashboard lagi)
        return view('inventory.index', [
            'items' => $items,
            'page' => $pagination['page'] ?? 1
        ]);
    }
}