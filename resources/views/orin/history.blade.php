@extends('layouts.master')

@section('header', 'Riwayat Perjalanan')

@section('content')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid">
    <div class="mb-3 d-flex align-items-center gap-2">
        <a href="{{ url('/orin/device/' . urlencode($vehicleId)) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
        </a>
        <h6 class="mb-0 text-muted fw-normal">/ {{ $device['device_name'] ?? $vehicleId }}</h6>
    </div>

    {{-- ── Filter Form ── --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}" max="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tanggal Selesai <span class="text-muted">(max 3 hari)</span></label>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}" max="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-search me-1"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($error)
    <div class="alert alert-warning">{{ is_array($error) ? json_encode($error) : $error }}</div>
    @endif

    @if(!empty($history))

    {{-- ── Summary Stats ── --}}
    @php
        $lastItem = end($history);
        reset($history);
    @endphp
    <div class="row g-3 mb-3">
        @foreach([
            ['label' => 'Total Jalan', 'val' => $lastItem['total_moving_time'] ?? '-', 'icon' => 'fa-clock', 'color' => 'success'],
            ['label' => 'Total Berhenti', 'val' => $lastItem['total_stop_time'] ?? '-', 'icon' => 'fa-circle-pause', 'color' => 'warning'],
            ['label' => 'Jarak Tempuh', 'val' => number_format(($lastItem['total_mileage'] ?? 0), 1) . ' km', 'icon' => 'fa-route', 'color' => 'primary'],
            ['label' => 'Kec. Maks', 'val' => ($lastItem['max_speed'] ?? '0') . ' km/h', 'icon' => 'fa-gauge-high', 'color' => 'danger'],
        ] as $stat)
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-{{ $stat['color'] }} bg-opacity-10 p-2 rounded-3 text-{{ $stat['color'] }}">
                        <i class="fa-solid {{ $stat['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ $stat['label'] }}</div>
                        <div class="fw-bold">{{ $stat['val'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- ── Peta Rute ── --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-map me-2 text-primary"></i>Rute Perjalanan</h6>
                </div>
                <div class="card-body p-0">
                    <div id="historyMap" style="height: 450px;"></div>
                </div>
            </div>
        </div>

        {{-- ── Timeline ── --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-timeline me-2 text-primary"></i>Timeline</h6>
                </div>
                <div class="card-body p-0" style="overflow-y: auto; max-height: 450px;">
                    @foreach($history as $segment)
                    @if(isset($segment['status']))
                    @php
                        $isMoving = $segment['status'] === 'moving';
                        $startLoc = $segment['start_row'] ?? [];
                        $endLoc   = $segment['end_row'] ?? [];
                    @endphp
                    <div class="d-flex gap-3 p-3 border-bottom">
                        <div class="text-center" style="min-width: 28px;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto
                                {{ $isMoving ? 'bg-success' : 'bg-warning' }}"
                                 style="width:28px; height:28px;">
                                <i class="fa-solid {{ $isMoving ? 'fa-truck-fast' : 'fa-circle-pause' }} text-white" style="font-size:0.65rem;"></i>
                            </div>
                        </div>
                        <div class="small flex-grow-1">
                            <div class="fw-bold {{ $isMoving ? 'text-success' : 'text-warning' }}">
                                {{ $segment['index'] ?? '' }}
                            </div>
                            <div class="text-muted">
                                {{ \Carbon\Carbon::parse($startLoc['dt'] ?? '')->format('H:i') }}
                                @if(!empty($endLoc['dt']))
                                → {{ \Carbon\Carbon::parse($endLoc['dt'])->format('H:i') }}
                                @endif
                            </div>
                            @if($isMoving)
                            <div class="mt-1">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success">{{ $segment['moving_min'] ?? '-' }}</span>
                                <span class="badge bg-light text-dark border ms-1">{{ number_format($segment['mileage'] ?? 0, 1) }} km</span>
                                @if(!empty($segment['max_speed']))
                                <span class="badge bg-light text-dark border ms-1">{{ $segment['max_speed'] }} km/h maks</span>
                                @endif
                            </div>
                            @if(!empty($segment['events']))
                            <div class="mt-1">
                                @foreach($segment['events'] as $ev)
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="font-size:.62rem;">⚠️ {{ is_array($ev) ? json_encode($ev) : $ev }}</span>
                                @endforeach
                            </div>
                            @endif
                            @else
                            <div class="mt-1">
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">{{ $segment['stop_string'] ?? '-' }}</span>
                            </div>
                            @if(!empty($startLoc['poi']))
                            <div class="text-muted mt-1" style="font-size:.68rem;"><i class="fa-solid fa-location-dot me-1"></i>{{ is_array($startLoc['poi']) ? json_encode($startLoc['poi']) : $startLoc['poi'] }}</div>
                            @endif
                            @endif
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @elseif(!$error)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa-solid fa-route fs-2 mb-3 opacity-25 d-block"></i>
            Tidak ada data perjalanan untuk rentang tanggal ini.
        </div>
    </div>
    @endif
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('historyMap').setView([-7.2575, 112.7521], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    @php
        $tripPoints = [];
        foreach($history as $seg) {
            if (($seg['status'] ?? '') === 'moving') {
                $s = $seg['start_row'] ?? [];
                $e = $seg['end_row'] ?? [];
                if (!empty($s['lat']) && !empty($s['lng'])) {
                    $tripPoints[] = [(float)$s['lat'], (float)$s['lng'], 'start', $seg['index'] ?? ''];
                }
                if (!empty($e['lat']) && !empty($e['lng'])) {
                    $tripPoints[] = [(float)$e['lat'], (float)$e['lng'], 'end', $seg['index'] ?? ''];
                }
            } elseif (($seg['status'] ?? '') === 'stop') {
                $s = $seg['start_row'] ?? [];
                if (!empty($s['lat']) && !empty($s['lng'])) {
                    $tripPoints[] = [(float)$s['lat'], (float)$s['lng'], 'stop', $seg['index'] ?? ''];
                }
            }
        }
    @endphp

    const points = @json($tripPoints);

    if (points.length > 0) {
        const latLngs = points.map(p => [p[0], p[1]]);

        // Garis rute
        L.polyline(latLngs, { color: '#3b82f6', weight: 5, opacity: 0.85 }).addTo(map);

        // Marker tiap titik
        points.forEach((p, i) => {
            const [lat, lng, type, label] = p;
            const color = type === 'stop' ? '#f59e0b' : (type === 'start' ? '#22c55e' : '#3b82f6');
            const icon  = type === 'stop' ? '🅿' : (type === 'start' ? '▶' : '⏹');

            L.circleMarker([lat, lng], {
                radius: 8, fillColor: color, color: 'white',
                weight: 2, fillOpacity: 1
            }).bindPopup(`<b>${label}</b><br>${type.toUpperCase()}`).addTo(map);
        });

        // Fit bounds
        map.fitBounds(L.polyline(latLngs).getBounds(), { padding: [30, 30] });
    }
</script>
@endsection