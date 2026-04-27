@extends('layouts.master')

@section('header', 'ORIN — Alert & Notifikasi')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ url('/orin') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Fleet Monitor
        </a>
    </div>

    @if($error)
    <div class="alert alert-warning">{{ is_array($error) ? json_encode($error) : $error }}</div>
    @endif

    @php
        $alertTypeMap = [
            'notif_geofence_inside'          => ['label' => 'Masuk Geofence',         'color' => 'success', 'icon' => 'fa-location-dot'],
            'notif_geofence_outside'         => ['label' => 'Keluar Geofence',        'color' => 'warning', 'icon' => 'fa-location-xmark'],
            'notif_schedule_geofence_inside' => ['label' => 'Jadwal Masuk Geofence',  'color' => 'info',    'icon' => 'fa-calendar-check'],
            'notif_schedule_geofence_outside'=> ['label' => 'Jadwal Keluar Geofence', 'color' => 'info',    'icon' => 'fa-calendar-xmark'],
            'notif_route_inside'             => ['label' => 'Dalam Rute',             'color' => 'primary', 'icon' => 'fa-road'],
            'notif_route_outside'            => ['label' => 'Keluar Rute',            'color' => 'danger',  'icon' => 'fa-triangle-exclamation'],
            'notif_speed_alert'              => ['label' => 'Overspeed',              'color' => 'danger',  'icon' => 'fa-gauge-high'],
            'notif_sos'                      => ['label' => 'SOS Alert',              'color' => 'danger',  'icon' => 'fa-sos'],
            'notif_cut_off'                  => ['label' => 'Power Cut',              'color' => 'dark',    'icon' => 'fa-plug-circle-xmark'],
        ];
    @endphp

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-bell me-2 text-danger"></i>Semua Alert</h6>
            <span class="badge bg-secondary rounded-pill">{{ count($alerts) }} notifikasi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Kendaraan</th>
                            <th class="px-4 py-3">Tipe Alert</th>
                            <th class="px-4 py-3">Pesan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($alerts as $alert)
                        @php
                            $type = $alert['alert_type'] ?? '';
                            $info = $alertTypeMap[$type] ?? ['label' => $type, 'color' => 'secondary', 'icon' => 'fa-bell'];
                        @endphp
                        <tr>
                            <td class="px-4 py-3 small text-muted">
                                {{ $alert['dt_display'] ?? $alert['dt'] ?? '-' }}<br>
                                <span style="font-size:.7rem;">{{ $alert['relative_time'] ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="fw-bold small">{{ $alert['device_name'] ?? '-' }}</div>
                                <div class="text-muted" style="font-size:.72rem;">{{ $alert['device_type'] ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge bg-{{ $info['color'] }} bg-opacity-15 text-{{ $info['color'] }} border border-{{ $info['color'] }}">
                                    <i class="fa-solid {{ $info['icon'] }} me-1"></i>{{ $info['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 small">
                                {{ is_array($alert['message'] ?? null) ? json_encode($alert['message']) : ($alert['message'] ?? '-') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="fa-solid fa-bell-slash fs-2 mb-2 opacity-25 d-block"></i>
                                Tidak ada alert saat ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection