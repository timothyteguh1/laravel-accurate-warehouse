<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
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

    public function index(Request $request)
    {
        $auth = $this->getAuthData();
        if (!$auth) return redirect('/accurate/login');

        $url = $auth['host'] . '/accurate/api/item/list.do';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $auth['token'], 
                'X-Session-ID' => $auth['session']
            ])->get($url, [
                // Field yang diminta: No, Nama, Stok, Harga Jual, Jenis Barang
                'fields' => 'id,no,name,quantity,unitPrice,itemType,unit1Name',
                'sp.pageSize' => 50,
                'sp.page' => $request->query('page', 1)
            ]);

            $data = $response->json();
            $items = $data['d'] ?? [];
            $pagination = $data['sp'] ?? [];

            return view('inventory.index', [
                'items' => $items,
                'page' => $pagination['page'] ?? 1
            ]);

        } catch (\Exception $e) {
            Log::error('Inventory Error: ' . $e->getMessage());
            return redirect('/dashboard')->with('error', 'Gagal memuat data inventori.');
        }
    }
}