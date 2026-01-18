<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AccurateService
{
    protected $baseUrl = 'https://account.accurate.id';
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->clientId = env('ACCURATE_CLIENT_ID');
        $this->clientSecret = env('ACCURATE_CLIENT_SECRET');
    }

    public function get($endpoint, $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Fungsi Inti: Mengirim Request + Auto Refresh Token + Session ID
     */
    private function request($method, $endpoint, $data = [])
    {
        // 1. Cek Apakah Token & Session Ada di File
        if (!Storage::exists('accurate_token.json') || !Storage::exists('accurate_session.json')) {
            return [
                'status' => false, 
                'message' => 'Token atau Session belum ada. Silakan buka /accurate/open-db dulu.'
            ];
        }

        // Ambil data dari file
        $tokenData = json_decode(Storage::get('accurate_token.json'), true);
        $sessionData = json_decode(Storage::get('accurate_session.json'), true);
        
        // Susun URL lengkap (Host dari session + endpoint)
        $url = $sessionData['host'] . '/accurate/api/' . $endpoint;
        
        // 2. Coba Request Pertama
        $response = Http::withToken($tokenData['access_token'])
            ->withHeaders(['X-Session-ID' => $sessionData['session']])
            ->$method($url, $data);

        // 3. Jika Error 401 (Token Expired), Lakukan Logika Refresh (Dari kode lama Anda)
        if ($response->status() === 401) {
            Log::info('Accurate Token Expired. Mencoba Refresh...');

            $newToken = $this->refreshToken($tokenData['refresh_token']);
            
            if ($newToken) {
                // Simpan Token Baru ke File
                Storage::put('accurate_token.json', json_encode($newToken));
                
                // Coba Request Ulang (Retry) dengan Token Baru
                $response = Http::withToken($newToken['access_token'])
                    ->withHeaders(['X-Session-ID' => $sessionData['session']])
                    ->$method($url, $data);
            } else {
                return ['status' => false, 'message' => 'Gagal Refresh Token. Login ulang diperlukan.'];
            }
        }

        return $response->json();
    }

    private function refreshToken($refreshToken)
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->baseUrl . '/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Gagal Refresh Token Accurate: ' . $response->body());
        return null;
    }
}