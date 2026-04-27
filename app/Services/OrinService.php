<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <--- WAJIB ADA UNTUK FITUR ANTI-BLOKIR 429

class OrinService
{
    // Base URL sesuai dokumen resmi ORIN (pakai /api/orin)
    protected string $baseUrl = 'https://api-v2.orin.id/api/orin';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('ORIN_API_KEY', '');
    }

    // ─── Header builder ────────────────────────────────────────────────────────
    private function headers(): array
    {
        return [
            // Auth wajib via Header sesuai PDF halaman 2
            'Authorization' => 'orin ' . $this->apiKey,
            'Accept'        => 'application/json', 
            'Content-Type'  => 'application/json',
        ];
    }

    // ─── Eksekusi Response & HTML Error Guard ──────────────────────────────────
    private function handleResponse($response, $method, $endpoint)
    {
        $isJson = str_contains($response->header('Content-Type'), 'application/json');

        if ($response->successful()) {
            if (!$isJson) {
                return [
                    'success' => false,
                    'message' => 'API ORIN merespons dengan HTML (Mungkin salah base URL / Endpoint).'
                ];
            }

            $json = $response->json();

            if (isset($json['success']) && $json['success'] === false) {
                $msg = $json['message'] ?? 'API merespons dengan kegagalan logika (200 OK).';
                return [
                    'success' => false,
                    'message' => is_array($msg) ? json_encode($msg) : (string) $msg
                ];
            }

            return ['success' => true, 'data' => $json];
        }

        $errorMsg = 'Menerima halaman HTML Error';
        if ($isJson) {
            $json = $response->json();
            $msg = $json['message'] ?? $response->body();
            $errorMsg = is_array($msg) ? json_encode($msg) : (string) $msg;
        }

        $bodyLog = $isJson ? json_encode($response->json()) : substr($response->body(), 0, 500) . '... [HTML Truncated]';

        Log::warning("ORIN {$method} Error", [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
            'body'     => $bodyLog,
        ]);

        return [
            'success' => false,
            'message' => 'ORIN API Error ' . $response->status() . ': ' . $errorMsg,
        ];
    }

    // ─── Generic GET ───────────────────────────────────────────────────────────
    private function get(string $endpoint, array $params = []): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'ORIN_API_KEY belum diset di .env'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->get($this->baseUrl . $endpoint, $params);

            return $this->handleResponse($response, 'GET', $endpoint);
        } catch (\Exception $e) {
            Log::error('ORIN GET Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage()];
        }
    }

    // ─── Generic POST ──────────────────────────────────────────────────────────
    private function post(string $endpoint, array $payload = []): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'ORIN_API_KEY belum diset di .env'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->post($this->baseUrl . $endpoint, $payload);

            return $this->handleResponse($response, 'POST', $endpoint);
        } catch (\Exception $e) {
            Log::error('ORIN POST Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. GET ALL DEVICES (DENGAN CACHE ANTI-BLOKIR 429)
    // ═══════════════════════════════════════════════════════════════════════════
    public function getAllDevices(): array
    {
        // Cek apakah data sudah ada di Cache Laravel (berlaku 60 detik)
        if (Cache::has('orin_all_devices')) {
            return Cache::get('orin_all_devices');
        }

        // Jika tidak ada di cache, kita panggil API ORIN
        $result = $this->get('/devices');

        // Jika berhasil, simpan di Cache selama 60 detik
        // Ini akan mencegah ORIN memblokir kita meskipun halaman direfresh berkali-kali
        if ($result['success']) {
            Cache::put('orin_all_devices', $result, 60);
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. GET SINGLE DEVICE (WORKAROUND FILTER DARI CACHE ALL DEVICES)
    // ═══════════════════════════════════════════════════════════════════════════
    public function getDevice(string $vehicleId): array
    {
        $result = $this->getAllDevices();

        if (!$result['success']) {
            return $result;
        }

        $raw = $result['data'];
        $devices = $raw['data'] ?? (is_array($raw) ? $raw : []);

        foreach ($devices as $dev) {
            if (!is_array($dev)) continue;

            $sn = $dev['sn'] ?? ($dev['device_sn'] ?? '');
            $nopol = $dev['nopol'] ?? '';

            if ($sn === $vehicleId || $nopol === $vehicleId) {
                return ['success' => true, 'data' => $dev];
            }
        }

        return [
            'success' => false,
            'message' => "Data kendaraan tidak ditemukan untuk ID/Nopol: {$vehicleId}"
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. GET RAW DATA 
    // ═══════════════════════════════════════════════════════════════════════════
    public function getRawData(string $vehicleId, string $startDate): array
    {
        return $this->get("/raw_datas/{$vehicleId}", ['start_date' => $startDate]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. GET RAW DATA FILTERED
    // ═══════════════════════════════════════════════════════════════════════════
    public function getRawDataFiltered(string $vehicleId, string $startDate, string $endDate, string $filter = ''): array 
    {
        $params = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];
        if (!empty($filter)) {
            $params['filter'] = $filter;
        }

        return $this->get("/raw_datas_filter/{$vehicleId}", $params);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. GET HISTORY ROUTES / TRAVEL REPORT (Sesuai PDF Halaman 13)
    // ═══════════════════════════════════════════════════════════════════════════
    public function getHistoryRoutes(string $vehicleId, string $startDate, string $endDate): array 
    {
        return $this->get("/history_routes/{$vehicleId}", [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. UPDATE DEVICE AVAILABLE STATUS
    // ═══════════════════════════════════════════════════════════════════════════
    public function updateDeviceAvailable(array $deviceIds, bool $available): array
    {
        return $this->post('/devices/available', [
            'devices'   => $deviceIds,
            'available' => $available,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 7. GET ALL ALERTS (Sesuai PDF Halaman 18)
    // ═══════════════════════════════════════════════════════════════════════════
    public function getAlerts(): array
    {
        return $this->get('/report/alerts');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 8. ADD DEVICE SCHEDULE
    // ═══════════════════════════════════════════════════════════════════════════
    public function addSchedule(string $nopol, string $scheduleDate, array $geofences): array
    {
        return $this->post('/device/add_schedule', [
            'nopol'       => $nopol,
            'schedule_dt' => $scheduleDate,
            'geofences'   => $geofences,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 9. ADD GEOFENCE (FAVORITE PLACE)
    // ═══════════════════════════════════════════════════════════════════════════
    public function addGeofence(string $name, int $radius, float $lat, float $lng): array
    {
        return $this->post('/add_geofence', [
            'geo_name'   => $name,
            'geo_radius' => $radius,
            'lat'        => $lat,
            'lng'        => $lng,
        ]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}