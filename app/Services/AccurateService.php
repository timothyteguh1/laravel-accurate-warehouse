<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccurateService
{
    protected $baseUrl = 'https://account.accurate.id';
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->clientId = env('ACCURATE_CLIENT_ID');
        $this->clientSecret = env('ACCURATE_CLIENT_SECRET');
        $this->redirectUri = env('ACCURATE_REDIRECT_URI');
    }

    /**
     * Mengambil data auth (Token & Session) dari Database
     */
    private function getAuthData()
    {
        // KITA GUNAKAN DB (Bukan Storage File lagi) agar konsisten dengan update terakhir
        $row = DB::table('accurate_tokens')->where('id', 1)->first();
        
        if (!$row) return null;

        return [
            'access_token' => $row->access_token,
            'refresh_token' => $row->refresh_token,
            'session' => $row->session,
            'host' => $row->host
        ];
    }

    /**
     * Request GET ke Accurate (Otomatis handle header & refresh token)
     */
    public function get($endpoint, $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Request POST ke Accurate
     */
    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Fungsi Inti: Mengirim Request + Auto Refresh Token
     */
    private function request($method, $endpoint, $data = [])
    {
        $auth = $this->getAuthData();

        // Cek apakah data auth lengkap di database
        if (!$auth || !$auth['access_token'] || !$auth['session']) {
            return [
                'status' => false, 
                'error_code' => 'NO_TOKEN',
                'message' => 'Token atau Session belum ada. Silakan hubungkan Accurate kembali.'
            ];
        }

        // Susun URL: Host dari session + endpoint (misal: /sales-order/list.do)
        // Endpoint tidak perlu pakai '/accurate/api/' lagi kalau passingnya 'sales-order/list.do'
        // Tapi kita standarkan input endpoint harus bersih (tanpa host)
        $url = $auth['host'] . '/accurate/api/' . $endpoint;
        
        // Lakukan Request
        $response = Http::withToken($auth['access_token'])
            ->withHeaders(['X-Session-ID' => $auth['session']])
            ->$method($url, $data);

        // Jika Token Expired (401), Lakukan Refresh Otomatis
        if ($response->status() === 401) {
            Log::info('Accurate Token Expired. Mencoba Refresh...');

            $newToken = $this->refreshToken($auth['refresh_token']);
            
            if ($newToken) {
                // Update Database dengan Token Baru
                DB::table('accurate_tokens')->where('id', 1)->update([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $newToken['refresh_token'],
                    'updated_at' => now(),
                ]);
                
                // Retry Request dengan Token Baru
                $response = Http::withToken($newToken['access_token'])
                    ->withHeaders(['X-Session-ID' => $auth['session']])
                    ->$method($url, $data);
            } else {
                return ['status' => false, 'error_code' => 'REFRESH_FAILED', 'message' => 'Sesi habis. Login ulang diperlukan.'];
            }
        }

        // Kembalikan dalam bentuk array (JSON decoded)
        return $response->json();
    }

    /**
     * Helper: Refresh Token (Public agar bisa dipanggil controller jika perlu)
     */
    public function refreshToken($refreshToken)
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->baseUrl . '/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Helper: Generate Login URL (Untuk AccurateTestController)
     */
    public function getLoginUrl()
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => 'sales_order_view sales_order_save delivery_order_save delivery_order_view item_view item_save customer_view item_category_view', 
        ]);
        return $this->baseUrl . '/oauth/authorize?' . $query;
    }

    /**
     * Helper: Tukar Code jadi Token (Untuk Callback)
     */
    public function exchangeCode($code)
    {
        return Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()->post($this->baseUrl . '/oauth/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);
    }
}