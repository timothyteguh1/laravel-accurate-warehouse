@extends('layouts.master')

@section('header', 'ORIN Fleet Monitor')

@section('content')

{{-- Leaflet CSS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid">

    {{-- ── Alert: API Key belum terset ── --}}
    @if(!$isConfigured)
    <div class="alert alert-danger d-flex align-items-start gap-3 shadow-sm mb-4">
        <i class="fa-solid fa-circle-xmark fs-4 mt-1 text-danger"></i>
        <div>
            <strong>ORIN_API_KEY belum diset!</strong><br>
            <span class="small">Tambahkan <code>ORIN_API_KEY=your_key_here</code> di file <code>.env</code> kamu, lalu jalankan <code>php artisan config:clear</code>.</span>
        </div>
    </div>
    @endif

    {{-- ── Alert: Error dari API ── --}}
    @if($error)
    <div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm mb-4">
        <i class="fa-solid fa-triangle-exclamation fs-4 mt-1"></i>
        <div>
            <strong>Gagal memuat data ORIN:</strong><br>
            <span class="small">{{ is_array($error) ? json_encode($error) : $error }}</span>
        </div>
    </div>
    @endif

    {{-- ── Stats Bar ── --}}
    <div class="row g-3 mb-4">
        @php
            $total   = count($devices);
            
            $moving = collect($devices)->filter(function($d) {
                $s = $d['device_status'] ?? $d['status'] ?? $d['device status'] ?? null;
                if (!$s || !in_array(strtoupper($s), ['MOVING', 'PARKING', 'IDLE'])) {
                    return ((float)($d['last_location']['speed'] ?? 0)) > 0;
                }
                return strtoupper($s) === 'MOVING';
            })->count();

            $parking = collect($devices)->filter(function($d) {
                $s = $d['device_status'] ?? $d['status'] ?? $d['device status'] ?? null;
                if (!$s || !in_array(strtoupper($s), ['MOVING', 'PARKING', 'IDLE'])) {
                    return ((float)($d['last_location']['speed'] ?? 0)) == 0;
                }
                return strtoupper($s) === 'PARKING';
            })->count();

            $offline = collect($devices)->filter(fn($d) => $d['is_offline'] ?? false)->count();
        @endphp

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3 text-primary">
                        <i class="fa-solid fa-car fa-lg"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Total Armada</div>
                        <div class="fw-bold fs-4">{{ $total }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 p-3 rounded-3 text-success">
                        <i class="fa-solid fa-truck-fast fa-lg"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Sedang Jalan</div>
                        <div class="fw-bold fs-4 text-success">{{ $moving }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-3 text-warning">
                        <i class="fa-solid fa-circle-pause fa-lg"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Parkir</div>
                        <div class="fw-bold fs-4 text-warning">{{ $parking }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-3 text-danger">
                        <i class="fa-solid fa-signal-slash fa-lg"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Offline</div>
                        <div class="fw-bold fs-4 text-danger">{{ $offline }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main layout: Peta + List ── --}}
    <div class="row g-3">

        {{-- PETA --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i>Peta Armada Live</h6>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshMap()">
                        <i class="fa-solid fa-rotate me-1"></i> Refresh
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="orinMap" style="height: 480px; width: 100%;"></div>
                </div>
            </div>
        </div>

        {{-- LIST ARMADA --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-list me-2 text-primary"></i>Daftar Armada</h6>
                </div>
                <div class="card-body p-0" style="overflow-y: auto; max-height: 480px;">
                    @forelse($devices as $dev)
                    @php
                        $offline  = $dev['is_offline'] ?? false;
                        $loc      = $dev['last_location'] ?? [];

                        // SMART FALLBACK STATUS
                        $rawStatus = $dev['device_status'] ?? $dev['status'] ?? $dev['device status'] ?? null;
                        if (!$rawStatus || !in_array(strtoupper($rawStatus), ['MOVING', 'PARKING', 'IDLE'])) {
                            $speed = (float) ($loc['speed'] ?? 0);
                            $rawStatus = $speed > 0 ? 'MOVING' : 'PARKING';
                        }
                        $status = strtoupper($rawStatus);

                        $statusColor = match($status) {
                            'MOVING'  => 'success',
                            'PARKING' => 'warning',
                            default   => 'secondary',
                        };
                        if ($offline) $statusColor = 'danger';
                    @endphp
                    <a href="{{ url('/orin/device/' . urlencode($dev['device_sn'] ?? '')) }}"
                       class="d-flex align-items-start gap-3 p-3 border-bottom text-decoration-none text-dark hover-bg"
                       style="transition: background 0.15s;"
                       onmouseover="this.style.background='#f8f9fa'"
                       onmouseout="this.style.background=''">
                        <div class="bg-{{ $statusColor }} bg-opacity-10 p-2 rounded-3 mt-1 text-{{ $statusColor }}" style="min-width:36px; text-align:center;">
                            <i class="fa-solid fa-{{ $offline ? 'signal-slash' : ($status === 'MOVING' ? 'truck-fast' : 'circle-pause') }}"></i>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bold small text-truncate">{{ $dev['device_name'] ?? '-' }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">SN: {{ $dev['device_sn'] ?? '-' }}</div>
                            <div class="d-flex gap-2 mt-1">
                                <span class="badge bg-{{ $statusColor }} bg-opacity-15 text-{{ $statusColor }}" style="font-size:.65rem;">
                                    {{ $offline ? 'OFFLINE' : $status }}
                                </span>
                                @if(!empty($loc['speed']))
                                <span class="badge bg-light text-dark border" style="font-size:.65rem;">
                                    {{ $loc['speed'] }} km/h
                                </span>
                                @endif
                            </div>
                            @if(!empty($loc['relative_time']))
                            <div class="text-muted mt-1" style="font-size:.65rem;">
                                <i class="fa-regular fa-clock me-1"></i>{{ $loc['relative_time'] }}
                            </div>
                            @endif
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small mt-2"></i>
                    </a>
                    @empty
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-car-burst fs-2 mb-2 opacity-25 d-block"></i>
                        Tidak ada data armada.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── Quick Links ── --}}
    <div class="row g-3 mt-2">
        <div class="col-md-4">
            <a href="{{ url('/orin/alerts') }}" class="card border-0 shadow-sm text-decoration-none text-dark">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-3 text-danger">
                        <i class="fa-solid fa-bell fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Alert & Notifikasi</div>
                        <div class="small text-muted">Geofence, overspeed, SOS</div>
                    </div>
                    <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                </div>
            </a>
        </div>
    </div>

</div>

{{-- Leaflet JS --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // ── Inisialisasi Peta ──
    const map = L.map('orinMap').setView([-7.2575, 112.7521], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let markers = [];

    // ── Icon Factory ──
    function buildIcon(status, offline) {
        const color = offline ? '#ef4444' : (status === 'MOVING' ? '#22c55e' : '#f59e0b');
        const icon  = offline ? '📵' : (status === 'MOVING' ? '🚛' : '🅿️');
        return L.divIcon({
            className: '',
            html: `<div style="background:${color}; color:white; border-radius:50%; width:32px; height:32px;
                        display:flex; align-items:center; justify-content:center; font-size:16px;
                        border:2px solid white; box-shadow:0 2px 6px rgba(0,0,0,0.4);">${icon}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -16],
        });
    }

    // ── Load markers dari API ──
    function loadMarkers() {
        fetch('{{ url("/orin/api/devices") }}')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;

                // Hapus marker lama
                markers.forEach(m => map.removeLayer(m));
                markers = [];

                res.data.forEach(d => {
                    const lat = parseFloat(d.lat);
                    const lng = parseFloat(d.lng);
                    if (isNaN(lat) || isNaN(lng)) return;

                    const status  = (d.status || '').toUpperCase();
                    const offline = d.is_offline;

                    const m = L.marker([lat, lng], { icon: buildIcon(status, offline) })
                        .bindPopup(`
                            <div style="min-width:180px;">
                                <b class="text-primary">${d.name}</b><br>
                                <small class="text-muted">SN: ${d.sn}</small><br>
                                <span class="badge bg-${offline ? 'danger' : (status === 'MOVING' ? 'success' : 'warning')} mt-1">${offline ? 'OFFLINE' : status}</span>
                                <div class="mt-2 small">
                                    🚀 <b>${d.speed} km/h</b><br>
                                    🕐 ${d.relative}<br>
                                </div>
                                <a href="/orin/device/${encodeURIComponent(d.sn)}" class="btn btn-sm btn-primary w-100 mt-2">Detail</a>
                            </div>
                        `)
                        .addTo(map);

                    markers.push(m);
                });
            })
            .catch(err => console.error('ORIN map error:', err));
    }

    // ── Initial load & auto-refresh tiap 60 detik ──
    loadMarkers();
    setInterval(loadMarkers, 60000);

    function refreshMap() {
        loadMarkers();
    }
</script>
@endsection