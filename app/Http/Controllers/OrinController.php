<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrinService;
use Illuminate\Support\Facades\Log;

class OrinController extends Controller
{
    protected OrinService $orin;

    public function __construct(OrinService $orin)
    {
        $this->orin = $orin;
    }

    // ─── 1. HALAMAN UTAMA: Daftar & Peta Semua Armada ─────────────────────────
    public function index()
    {
        $result = $this->orin->getAllDevices();
        $devices = [];
        $error = null;

        if ($result['success']) {
            $raw = $result['data'];

            // Amankan struktur array agar Blade tidak menerima struktur yang salah
            if (isset($raw['data']) && is_array($raw['data'])) {
                $devices = $raw['data'];
            } elseif (is_array($raw)) {
                // Filter hanya ambil elemen array (mencegah bool/string masuk ke loop blade)
                $devices = array_filter($raw, fn($item) => is_array($item));
            }
        } else {
            // Pengaman array to string
            $msg = $result['message'];
            $error = is_array($msg) ? json_encode($msg) : (string) $msg;
        }

        return view('orin.index', [
            'devices' => $devices,
            'error' => $error,
            'isConfigured' => $this->orin->isConfigured(),
        ]);
    }

    // ─── 2. DETAIL SATU KENDARAAN ──────────────────────────────────────────────
    public function show(string $vehicleId)
    {
        $result = $this->orin->getDevice($vehicleId);
        $device = null;
        $error = null;

        if ($result['success']) {
            $device = $result['data']; // Sudah otomatis berbentuk array detail dari Service
        } else {
            $msg = $result['message'];
            $error = is_array($msg) ? json_encode($msg) : (string) $msg;
        }

        return view('orin.detail', [
            'device' => $device,
            'vehicleId' => $vehicleId,
            'error' => $error,
        ]);
    }

    // ─── 3. RIWAYAT PERJALANAN ─────────────────────────────────────────────────
    public function historyRoutes(Request $request, string $vehicleId)
    {
        $startDate = $request->query('start_date', now()->subDays(1)->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->format('Y-m-d'));

        $result = $this->orin->getHistoryRoutes($vehicleId, $startDate, $endDate);
        $history = [];
        $device = [];
        $error = null;

        if ($result['success']) {
            $raw = $result['data'];
            $history = $raw['data'] ?? [];
            $device = $raw['device'] ?? [];
        } else {
            $msg = $result['message'];
            $error = is_array($msg) ? json_encode($msg) : (string) $msg;
        }

        return view('orin.history', [
            'history' => $history,
            'device' => $device,
            'vehicleId' => $vehicleId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'error' => $error,
        ]);
    }

    // ─── 4. ALERTS / NOTIFIKASI ────────────────────────────────────────────────
    public function alerts()
    {
        $result = $this->orin->getAlerts();
        $alerts = [];
        $error = null;

        if ($result['success']) {
            $raw = $result['data'];
            $alerts = $raw['data'] ?? (is_array($raw) ? $raw : []);
        } else {
            $msg = $result['message'];
            $error = is_array($msg) ? json_encode($msg) : (string) $msg;
        }

        return view('orin.alerts', [
            'alerts' => $alerts,
            'error' => $error,
        ]);
    }

    // ─── 5. API: Get All Devices (JSON untuk peta live) ───────────────────────
    public function apiDevices()
    {
        $result = $this->orin->getAllDevices();

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 500);
        }

        $raw = $result['data'];
        $devices = $raw['data'] ?? (is_array($raw) ? $raw : []);

        // Ekstrak data penting untuk peta
        $mapped = collect($devices)->map(function ($d) {
            $loc = $d['last_location'] ?? [];
            
            // SMART FALLBACK: Jika API tidak mengirim status, hitung dari kecepatan
            $rawStatus = $d['device_status'] ?? $d['status'] ?? $d['device status'] ?? null;
            if (!$rawStatus || !in_array(strtoupper($rawStatus), ['MOVING', 'PARKING', 'IDLE'])) {
                $rawStatus = ((float)($loc['speed'] ?? 0)) > 0 ? 'MOVING' : 'PARKING';
            }

            return [
                'sn' => $d['device_sn'] ?? '',
                'name' => $d['device_name'] ?? '-',
                'nopol' => $d['nopol'] ?? '',
                'status' => strtoupper($rawStatus),
                'is_offline' => $d['is_offline'] ?? false,
                'lat' => $loc['lat'] ?? null,
                'lng' => $loc['lng'] ?? null,
                'speed' => $loc['speed'] ?? '0',
                'gps_date' => $loc['gps_date'] ?? '-',
                'relative' => $loc['relative_time'] ?? '-',
            ];
        })->filter(fn($d) => $d['lat'] && $d['lng'])->values();

        return response()->json(['success' => true, 'data' => $mapped]);
    }

    // ─── 6. API: Raw Data (JSON) ───────────────────────────────────────────────
    public function apiRawData(Request $request, string $vehicleId)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $result = $this->orin->getRawData($vehicleId, $date);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 500);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    // ─── 7. API: Update Device Available ─────────────────────────────────────
    public function apiUpdateAvailable(Request $request)
    {
        $request->validate([
            'devices' => 'required|array',
            'available' => 'required|boolean',
        ]);

        $result = $this->orin->updateDeviceAvailable(
            $request->devices,
            (bool) $request->available
        );

        return response()->json($result);
    }

    // ─── 0. DEBUG: TEST KONEKSI ORIN ───────────────────────────────────────────
    public function testConnection()
    {
        $result = $this->orin->getAllDevices();

        if ($result['success']) {
            return response()->json([
                'status' => 'Berhasil!',
                'message' => 'Koneksi ke ORIN API V2 terkoneksi dengan mantap.',
                'raw_response' => $result['data']
            ]);
        }

        return response()->json([
            'status' => 'Gagal!',
            'message' => 'Wah, ORIN API masih menolak koneksi kita.',
            'error_detail' => $result['message']
        ], 500);
    }
}