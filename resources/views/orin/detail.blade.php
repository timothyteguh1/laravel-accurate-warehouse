@extends('layouts.master')

@section('header', 'Detail Kendaraan')

@section('content')

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="container-fluid">
        <div class="mb-3">
            <a href="{{ url('/orin') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        @if ($error)
            <div class="alert alert-warning">{{ is_array($error) ? json_encode($error) : $error }}</div>
        @endif

        @if ($device)
            @php
                // ─── ANTI-CRASH HELPER ───
                // Fungsi untuk memastikan apapun tipe data dari ORIN (array/objek/string),
                // akan dicetak sebagai string sehingga Blade tidak meledak (htmlspecialchars error).
                $safeVal = function ($val, $default = '-') {
                    if (is_array($val) || is_object($val)) {
                        // Jika isinya array kosong, tampilkan default saja
                        return empty($val) ? $default : json_encode($val);
                    }
                    return (string) ($val ?? $default);
                };

                $offline = $device['is_offline'] ?? false;
                $loc = $device['last_location'] ?? ($device ?? []);
                $lastData = $device['last_data'] ?? [];

                // SMART FALLBACK STATUS
                $rawStatus = $device['device_status'] ?? $device['status'] ?? $device['device status'] ?? null;
                if (!$rawStatus || !in_array(strtoupper($rawStatus), ['MOVING', 'PARKING', 'IDLE'])) {
                    $speed = (float) ($loc['speed'] ?? 0);
                    $rawStatus = $speed > 0 ? 'MOVING' : 'PARKING';
                }
                $status = strtoupper($rawStatus);

                $statusColor = match ($status) {
                    'MOVING' => 'success',
                    'PARKING' => 'warning',
                    default => 'secondary',
                };
                if ($offline) {
                    $statusColor = 'danger';
                }
            @endphp

            <div class="row g-3">

                {{-- ── Info Card ── --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                                <div class="bg-{{ $statusColor }} bg-opacity-10 p-3 rounded-3 text-{{ $statusColor }}">
                                    <i class="fa-solid fa-car fa-2x"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0">
                                        {{ $safeVal($device['device_name'] ?? ($device['name'] ?? null)) }}</h5>
                                    <span
                                        class="badge bg-{{ $statusColor }} mt-1">{{ $offline ? 'OFFLINE' : $status }}</span>
                                </div>
                            </div>

                            <table class="table table-sm table-borderless small">
                                <tr>
                                    <td class="text-muted">Tipe</td>
                                    <td class="fw-bold">
                                        {{ is_array($device['device_type'] ?? null) ? $device['device_type']['name'] ?? '-' : $safeVal($device['device_type'] ?? null) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">SN/IMEI</td>
                                    <td class="fw-bold">{{ $safeVal($device['device_sn'] ?? ($device['sn'] ?? null)) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">GSM</td>
                                    <td>{{ $safeVal($device['gsm'] ?? null) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Batas Speed</td>
                                    <td>{{ $safeVal($device['speed_limit'] ?? null) }} km/h</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Langganan</td>
                                    <td><span
                                            class="badge bg-primary">{{ $safeVal($device['subscription_status'] ?? null) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Sisa</td>
                                    <td>{{ $safeVal($device['subscription_remaining_days'] ?? null) }}</td>
                                </tr>
                            </table>

                            <hr>
                            <h6 class="fw-bold text-muted small">LOKASI TERAKHIR</h6>
                            <table class="table table-sm table-borderless small">
                                <tr>
                                    <td class="text-muted">Waktu</td>
                                    <td>{{ $safeVal($loc['gps_date'] ?? null) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Relative</td>
                                    <td>{{ $safeVal($loc['relative_time'] ?? ($loc['relative'] ?? null)) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Kecepatan</td>
                                    <td><b>{{ $safeVal($loc['speed'] ?? '0') }} km/h</b></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Satelit</td>
                                    <td>{{ $safeVal($loc['satellite'] ?? null) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Koordinat</td>
                                    <td>
                                        @if (!empty($loc['lat']) && !is_array($loc['lat']) && !empty($loc['lng']) && !is_array($loc['lng']))
                                            <a href="https://maps.google.com/?q={{ $loc['lat'] }},{{ $loc['lng'] }}"
                                                target="_blank" class="small">
                                                {{ $loc['lat'] }}, {{ $loc['lng'] }} <i
                                                    class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            {{-- Quick Actions --}}
                            <hr>
                            <h6 class="fw-bold text-muted small">AKSI CEPAT</h6>
                            <div class="d-grid gap-2">
                                <a href="{{ url('/orin/device/' . urlencode($vehicleId) . '/history') }}"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-route me-1"></i> Riwayat Perjalanan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Peta ── --}}
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="fw-bold mb-0"><i class="fa-solid fa-map-pin me-2 text-danger"></i>Posisi Kendaraan
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div id="devMap" style="height: 400px;"></div>
                        </div>
                    </div>

                    {{-- Status Data ──  --}}
                    @if (!empty($lastData))
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-header bg-white">
                                <h6 class="fw-bold mb-0 small"><i class="fa-solid fa-microchip me-2 text-info"></i>Data
                                    Sensor Terakhir</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    @php
                                        $sensors = [
                                            ['label' => 'Status Oil', 'key' => 'status_oil', 'icon' => 'fa-oil-can'],
                                            ['label' => 'Status GPS', 'key' => 'status_gps', 'icon' => 'fa-satellite'],
                                            ['label' => 'Status Charge', 'key' => 'status_charge', 'icon' => 'fa-bolt'],
                                            ['label' => 'Status ACC', 'key' => 'status_acc', 'icon' => 'fa-key'],
                                            ['label' => 'Alarm', 'key' => 'status_alarm', 'icon' => 'fa-bell'],
                                            [
                                                'label' => 'Voltage',
                                                'key' => 'status_voltage',
                                                'icon' => 'fa-battery-half',
                                            ],
                                            ['label' => 'Signal', 'key' => 'status_signal', 'icon' => 'fa-signal'],
                                        ];
                                    @endphp
                                    @foreach ($sensors as $s)
                                        <div class="col-6 col-md-4">
                                            <div class="bg-light rounded-3 p-2 text-center small">
                                                <div class="text-muted mb-1"><i
                                                        class="fa-solid {{ $s['icon'] }} me-1"></i>{{ $s['label'] }}
                                                </div>
                                                <div class="fw-bold">{{ $safeVal($lastData[$s['key']] ?? null) }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="col-6 col-md-4">
                                        <div class="bg-light rounded-3 p-2 text-center small">
                                            <div class="text-muted mb-1"><i class="fa-solid fa-gauge me-1"></i>Baterai</div>
                                            <div class="fw-bold">
                                                {{ $safeVal($lastData['status_voltage_percentage'] ?? null) }}{{ !empty($lastData['status_voltage_percentage']) && !is_array($lastData['status_voltage_percentage']) ? '%' : '' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-info">Data kendaraan tidak ditemukan untuk ID: <b>{{ $vehicleId }}</b></div>
        @endif
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        @if ($device && !empty($loc['lat']) && !is_array($loc['lat']) && !empty($loc['lng']) && !is_array($loc['lng']))
            const lat = parseFloat("{{ $loc['lat'] }}");
            const lng = parseFloat("{{ $loc['lng'] }}");

            const map = L.map('devMap').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

            const status = "{{ $status }}";
            const offline = {{ $offline ? 'true' : 'false' }};
            const color = offline ? '#ef4444' : (status === 'MOVING' ? '#22c55e' : '#f59e0b');

            L.circleMarker([lat, lng], {
                    radius: 14,
                    fillColor: color,
                    color: 'white',
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.9
                })
                .bindPopup(
                    `<b>{{ $device['device_name'] ?? ($device['name'] ?? '') }}</b><br>{{ $status }}<br>{{ $loc['relative_time'] ?? ($loc['relative'] ?? '') }}`
                    )
                .addTo(map)
                .openPopup();

            // Lingkaran akurasi
            L.circle([lat, lng], {
                radius: 50,
                color: color,
                fillColor: color,
                fillOpacity: 0.1
            }).addTo(map);
        @else
            const map = L.map('devMap').setView([-7.2575, 112.7521], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        @endif
    </script>
@endsection