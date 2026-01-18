<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AccurateTestController extends Controller
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct()
    {
        $this->clientId = env('ACCURATE_CLIENT_ID');
        $this->clientSecret = env('ACCURATE_CLIENT_SECRET');
        $this->redirectUri = env('ACCURATE_REDIRECT_URI');
    }

    // --- TAHAP A: Login ---
    public function login()
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            // [PENTING] Scope ini menentukan izin apa saja yang kita minta
            // delivery_order_view WAJIB ADA untuk fitur Print
            'scope' => 'sales_order_view sales_order_save delivery_order_save delivery_order_view item_view item_save customer_view item_category_view', 
        ]);

        return redirect('https://account.accurate.id/oauth/authorize?' . $query);
    }

    // --- TAHAP B: Callback & Simpan Token ---
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return response()->json(['error' => 'Login Gagal', 'msg' => $request->error]);
        }
        if (!$request->has('code')) {
            return response()->json(['error' => 'Gagal: Tidak ada Authorization Code.']);
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()->post('https://account.accurate.id/oauth/token', [
                'code' => $request->code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Gagal tukar token', 'details' => $response->json()]);
        }

        $tokenData = $response->json();
        // Simpan token baru ke file, menimpa yang lama
        Storage::put('accurate_token.json', json_encode($tokenData));

        return response()->json([
            'status' => 'LOGIN BERHASIL & TOKEN DISIMPAN! ✅',
            'pesan' => 'Token baru dengan izin Print sudah aktif. Silakan akses /accurate/open-db',
            'data_token' => $tokenData
        ]);
    }

    // --- TAHAP C: Buka Database ---
    public function openDatabase()
    {
        if (!Storage::exists('accurate_token.json')) {
            return response()->json(['error' => 'Belum ada token. Login dulu.'], 401);
        }

        $tokenData = json_decode(Storage::get('accurate_token.json'), true);
        $accessToken = $tokenData['access_token'];
        
        // ID Database Anda
        $databaseId = '2335871'; 

        $response = Http::withToken($accessToken)->get('https://account.accurate.id/api/open-db.do', [
            'id' => $databaseId
        ]);

        // Auto Refresh Token jika Expired
        if ($response->status() === 401) {
            $newTokenData = $this->refreshToken($tokenData['refresh_token']);
            if (!$newTokenData) {
                return response()->json(['error' => 'Token Expired & Gagal Refresh. Silakan Login Ulang.'], 401);
            }
            
            // Simpan token hasil refresh
            Storage::put('accurate_token.json', json_encode($newTokenData));
            $accessToken = $newTokenData['access_token'];
            
            // Coba request ulang
            $response = Http::withToken($accessToken)->get('https://account.accurate.id/api/open-db.do', [
                'id' => $databaseId
            ]);
        }

        if ($response->failed()) {
            return response()->json(['status' => 'Gagal Buka DB', 'error' => $response->json()]);
        }

        $data = $response->json();
        
        // Simpan Session ID & Host
        Storage::put('accurate_session.json', json_encode([
            'session' => $data['session'], 
            'host' => $data['host']
        ]));

        // Redirect ke Dashboard setelah sukses buka DB
        return redirect('/dashboard')->with('success', 'Database Terbuka!');
    }

    // --- HELPER: Refresh Token ---
    private function refreshToken($refreshToken)
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()->post('https://account.accurate.id/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);
        return $response->successful() ? $response->json() : null;
    }

    // --- HELPER: Get Token (Internal Use) ---
    private function getAccessToken()
    {
        if (Storage::exists('accurate_token.json')) {
            return json_decode(Storage::get('accurate_token.json'), true)['access_token'];
        }
        return '';
    }

    // --- FITUR LAIN (Sales Order) ---
    public function indexSalesOrder()
    {
        $listSO = $this->fetchSalesOrders();
        return view('accurate-so', ['orders' => $listSO]);
    }

    private function fetchSalesOrders()
    {
        if (!Storage::exists('accurate_session.json')) {
            return [];
        }
        
        $sessionData = json_decode(Storage::get('accurate_session.json'), true);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'X-Session-ID' => $sessionData['session']
        ])->get($sessionData['host'] . '/accurate/api/sales-order/list.do', [
            'fields' => 'number,customer,transDate,totalAmount',
            'sort' => 'number desc'
        ]);

        return $response->json()['d'] ?? [];
    }

    public function createSalesOrder(Request $request)
    {
        if (!Storage::exists('accurate_session.json')) {
            return "ERROR: Session belum dibuat. Silakan login ulang.";
        }
        
        $sessionData = json_decode(Storage::get('accurate_session.json'), true);
        
        $payload = [
            'customerNo' => $request->customerNo, 
            'detailItem' => [
                [
                    'itemNo' => $request->itemNo, 
                    'unitPrice' => $request->price,
                    'quantity' => $request->qty
                ]
            ],
            'transDate' => date('d/m/Y'),
            'description' => 'Input dari Laravel Jastip'
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'X-Session-ID' => $sessionData['session']
        ])->post($sessionData['host'] . '/accurate/api/sales-order/save.do', $payload);

        $result = $response->json();

        // Debugging Error Accurate
        if (isset($result['s']) && $result['s'] === false) {
            return dd([
                'STATUS' => 'GAGAL DARI ACCURATE',
                'PESAN' => $result['d'],
                'PAYLOAD' => $payload
            ]);
        }

        return back()->with('success', 'Berhasil! No SO: ' . ($result['r']['number'] ?? 'Unknown'));
    }
}