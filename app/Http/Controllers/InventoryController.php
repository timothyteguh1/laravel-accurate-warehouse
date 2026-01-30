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
        // 1. Siapkan Parameter Default
        $params = [
            'fields' => 'id,no,name,quantity,unitPrice,itemType,unit1Name',
            'sp.pageSize' => 50,
            'sp.page' => $request->query('page', 1)
        ];

        // 2. Logika Pencarian (Jika ada input search)
        if ($request->has('search') && !empty($request->search)) {
            // Filter 'keywords' di Accurate mencari di field No Item & Name sekaligus
            $params['filter.keywords.op'] = 'CONTAIN';
            $params['filter.keywords.val'] = $request->search;
        }

        // 3. Panggil API
        $response = $this->accurate->get('item/list.do', $params);

        // Cek Error Koneksi
        if (isset($response['status']) && $response['status'] === false) {
             if ($request->ajax()) {
                 return response()->json(['error' => 'Koneksi Accurate bermasalah'], 500);
             }
             return redirect('/accurate/auth')->with('warning', 'Koneksi Accurate terputus.');
        }

        $items = $response['d'] ?? [];
        $pagination = $response['sp'] ?? [];
        $currentPage = $pagination['page'] ?? 1;

        // 4. JIKA AJAX -> Return Partial View (Hanya Tabel)
        if ($request->ajax()) {
            return view('inventory.partials.table', ['items' => $items])->render();
        }

        // 5. JIKA BIASA -> Return Full View
        return view('inventory.index', [
            'items' => $items,
            'page' => $currentPage
        ]);
    }
}