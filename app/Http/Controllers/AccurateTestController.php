<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AccurateService; // Import Service
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AccurateTestController extends Controller
{
    protected $accurate;

    // Inject Service
    public function __construct(AccurateService $accurate)
    {
        $this->accurate = $accurate;
    }

    // --- TAHAP A: Connect ke Accurate ---
    public function login()
    {
        // Generate URL Login via Service
        return redirect($this->accurate->getLoginUrl());
    }

    // --- TAHAP B: Callback ---
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/dashboard')->with('error', 'Login Gagal: ' . $request->error);
        }

        // Tukar Code jadi Token via Service
        $response = $this->accurate->exchangeCode($request->code);

        if ($response->failed()) {
            return redirect('/dashboard')->with('error', 'Gagal tukar token.');
        }

        $tokenData = $response->json();

        // Simpan Token ke Database
        DB::table('accurate_tokens')->updateOrInsert(
            ['id' => 1],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_type' => $tokenData['token_type'] ?? 'Bearer',
                'scope' => $tokenData['scope'] ?? '',
                'updated_at' => now(),
            ]
        );

        return $this->openDatabase();
    }

    // --- TAHAP C: Buka Database ---
    public function openDatabase()
    {
        $tokenRow = DB::table('accurate_tokens')->where('id', 1)->first();
        if (!$tokenRow) return redirect('/dashboard')->with('warning', 'Belum terhubung.');

        $databaseId = '2335871'; // ID Database Accurate Anda

        // Kita pakai HTTP biasa di sini karena ini request inisialisasi session
        $response = Http::withToken($tokenRow->access_token)
            ->get('https://account.accurate.id/api/open-db.do', ['id' => $databaseId]);

        // Handle Token Expired saat Open DB
        if ($response->status() === 401) {
            // Refresh via Service
            $newToken = $this->accurate->refreshToken($tokenRow->refresh_token);
            
            if ($newToken) {
                // Update DB
                DB::table('accurate_tokens')->where('id', 1)->update([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $newToken['refresh_token'],
                    'updated_at' => now(),
                ]);
                
                // Retry Request
                $response = Http::withToken($newToken['access_token'])
                    ->get('https://account.accurate.id/api/open-db.do', ['id' => $databaseId]);
            } else {
                return redirect('/dashboard')->with('error', 'Sesi habis, silakan connect ulang.');
            }
        }

        if ($response->failed()) return redirect('/dashboard')->with('error', 'Gagal buka database.');

        $data = $response->json();

        // Simpan Session ke Database
        DB::table('accurate_tokens')->where('id', 1)->update([
            'session' => $data['session'],
            'host' => $data['host'],
            'updated_at' => now(),
        ]);

        return redirect('/dashboard')->with('success', 'Terkoneksi ke Accurate!');
    }
}