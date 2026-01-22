<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB; // WAJIB: Pakai DB Facade

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

    // --- TAHAP A: Connect ke Accurate (Pintu Masuk Admin) ---
    public function login()
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => 'sales_order_view sales_order_save delivery_order_save delivery_order_view item_view item_save customer_view item_category_view', 
        ]);

        return redirect('https://account.accurate.id/oauth/authorize?' . $query);
    }

    // --- TAHAP B: Callback & Simpan ke DATABASE ---
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/dashboard')->with('error', 'Login Accurate Gagal: ' . $request->error);
        }
        if (!$request->has('code')) {
            return redirect('/dashboard')->with('error', 'Gagal: Tidak ada Authorization Code.');
        }

        // Tukar Code dengan Token
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()->post('https://account.accurate.id/oauth/token', [
                'code' => $request->code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

        if ($response->failed()) {
            return redirect('/dashboard')->with('error', 'Gagal tukar token Accurate.');
        }

        $tokenData = $response->json();

        // [PENTING] Simpan Token ke Database (Selalu ID 1)
        DB::table('accurate_tokens')->updateOrInsert(
            ['id' => 1], // Kunci: Kita cuma pakai baris ID 1
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_type' => $tokenData['token_type'] ?? 'Bearer',
                'scope' => $tokenData['scope'] ?? '',
                'updated_at' => now(),
            ]
        );

        // Lanjut buka database
        return $this->openDatabase();
    }

    // --- TAHAP C: Buka Database & Simpan Session ---
    public function openDatabase()
    {
        // Ambil token dari Database
        $tokenRow = DB::table('accurate_tokens')->where('id', 1)->first();

        if (!$tokenRow) {
            return redirect('/dashboard')->with('warning', 'Sistem belum terhubung ke Accurate. Silakan klik tombol Connect.');
        }

        $accessToken = $tokenRow->access_token;
        $databaseId = '2335871'; // ID Database Accurate Anda

        $response = Http::withToken($accessToken)->get('https://account.accurate.id/api/open-db.do', [
            'id' => $databaseId
        ]);

        // Auto Refresh Token jika Expired (401)
        if ($response->status() === 401) {
            $newTokenData = $this->refreshToken($tokenRow->refresh_token);
            if (!$newTokenData) {
                return redirect('/dashboard')->with('error', 'Koneksi Accurate Expired. Mohon Connect ulang.');
            }
            
            // Simpan token baru ke Database
            DB::table('accurate_tokens')->where('id', 1)->update([
                'access_token' => $newTokenData['access_token'],
                'refresh_token' => $newTokenData['refresh_token'],
                'updated_at' => now(),
            ]);
            
            $accessToken = $newTokenData['access_token'];
            
            // Retry Request
            $response = Http::withToken($accessToken)->get('https://account.accurate.id/api/open-db.do', [
                'id' => $databaseId
            ]);
        }

        if ($response->failed()) {
            return redirect('/dashboard')->with('error', 'Gagal membuka Database Accurate.');
        }

        $data = $response->json();
        
        // [PENTING] Simpan Session & Host ke Database
        DB::table('accurate_tokens')->where('id', 1)->update([
            'session' => $data['session'],
            'host' => $data['host'],
            'updated_at' => now(),
        ]);

        return redirect('/dashboard')->with('success', 'Koneksi Accurate Berhasil & Stabil!');
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
}