@extends('layouts.master')

@section('header', 'Monitor Armada & Sopir')

@section('content')

{{-- ── Tambahkan CSS untuk Peta Leaflet ── --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid">
    <div class="alert alert-primary border-0 shadow-sm mb-4">
        <i class="fa-solid fa-truck-fast me-2"></i>
        Pantau pergerakan armada dan Surat Jalan (DO) yang sedang dibawa oleh masing-masing sopir.
    </div>

    <div class="row">
        @forelse($drivers as $driver)
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100 border-0 shadow-sm">
                
                {{-- Header Card Sopir --}}
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-user-tie text-primary me-2"></i>{{ $driver->name }}
                            </h5>
                            <span class="badge bg-dark mt-2 fs-6">
                                <i class="fa-solid fa-car-rear me-1"></i> {{ $driver->license_plate }}
                            </span>
                        </div>
                        <div class="text-end">
                            @php
                                // Ambil DO yang statusnya Di Perjalanan atau sedang dihitung (waktu_berangkat ada tapi belum kembali)
                                $activeDOs = $driver->deliveries->whereNull('waktu_kembali')->whereNotNull('waktu_berangkat');
                                $activeCount = $activeDOs->count();

                                // Filter data DO yang punya kordinat, lalu ubah jadi format JSON
                                $routeData = $driver->deliveries->whereNull('waktu_kembali')->filter(function($d) {
                                    return !empty($d->latitude) && !empty($d->longitude);
                                })->map(function($d) {
                                    return [
                                        'do_number' => $d->accurate_do_number,
                                        'alamat'    => $d->alamat_tujuan,
                                        'lat'       => $d->latitude,
                                        'lng'       => $d->longitude
                                    ];
                                })->values()->toJson();
                            @endphp

                            @if($activeCount > 0)
                                <div class="spinner-grow text-warning spinner-grow-sm" role="status"></div>
                                <span class="fw-bold text-warning ms-1">{{ $activeCount }} Jalan</span>
                            @else
                                <span class="badge bg-success rounded-pill"><i class="fa-solid fa-mug-hot me-1"></i> Standby</span>
                            @endif
                        </div>
                    </div>

                    {{-- Tombol Lihat Rute (OSRM AI) --}}
                    @if($activeCount > 0 && $routeData != '[]')
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary w-100 fw-bold" 
                                    data-rute="{{ $routeData }}" 
                                    onclick="tampilkanRute(this, '{{ $driver->name }}')">
                                <i class="fa-solid fa-map-location-dot me-1"></i> Lihat Rute Optimasi AI
                            </button>
                        </div>
                    @endif
                </div>

                {{-- List DO yang Dibawa --}}
                <div class="card-body">
                    <hr class="text-muted opacity-25">
                    <h6 class="text-muted small fw-bold mb-3">DAFTAR PENGIRIMAN:</h6>
                    
                    @if($driver->deliveries->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($driver->deliveries as $delivery)
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start border-0 border-bottom">
                                    <div class="pe-2">
                                        <div class="fw-bold text-primary">{{ $delivery->accurate_do_number }}</div>
                                        <small class="text-muted">{{ $delivery->created_at->format('d M Y, H:i') }}</small>
                                        
                                        @if($delivery->alamat_tujuan)
                                            <div class="small mt-1 text-secondary d-flex align-items-center" style="font-size: 0.75rem;">
                                                <span><i class="fa-solid fa-location-dot me-1"></i> {{ \Illuminate\Support\Str::limit($delivery->alamat_tujuan, 35) }}</span>
                                                @if(empty($delivery->waktu_kembali))
                                                <button class="btn btn-sm btn-link text-primary p-0 ms-2" onclick="editAlamat('{{ $delivery->id }}', '{{ $delivery->accurate_do_number }}')" title="Edit Alamat">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                @endif
                                            </div>
                                        @else
                                            <div class="small mt-1 text-danger d-flex align-items-center" style="font-size: 0.75rem;">
                                                <span><i class="fa-solid fa-triangle-exclamation me-1"></i> Alamat belum diisi</span>
                                                @if(empty($delivery->waktu_kembali))
                                                <button class="btn btn-sm btn-link text-primary p-0 ms-2" onclick="editAlamat('{{ $delivery->id }}', '{{ $delivery->accurate_do_number }}')" title="Isi Alamat">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    {{-- ── TAHAP 2: KENDALI TRACKING TIAP DO ── --}}
                                    <div class="text-end" style="min-width: 90px;">
                                        @if(empty($delivery->waktu_berangkat))
                                            {{-- BELUM BERANGKAT --}}
                                            <form action="{{ route('delivery.start', $delivery->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary mt-1 shadow-sm w-100">
                                                    <i class="fa-solid fa-truck-fast"></i> Mulai
                                                </button>
                                            </form>
                                        @elseif(!empty($delivery->waktu_berangkat) && empty($delivery->waktu_kembali))
                                            {{-- DALAM PERJALANAN (LIVE) --}}
                                            <div class="d-flex flex-column gap-1 mt-1">
                                                <button class="btn btn-sm btn-info text-white shadow-sm w-100" onclick="Swal.fire('Info', 'Fitur Live Tracking (Tahap 3) sedang disiapkan.', 'info')">
                                                    <i class="fa-solid fa-location-crosshairs"></i> Live
                                                </button>
                                                <form action="{{ route('delivery.end', $delivery->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger shadow-sm w-100" onclick="return confirm('Akhiri pengiriman DO ini?')">
                                                        <i class="fa-solid fa-flag-checkered"></i> Selesai
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            {{-- SELESAI --}}
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success mt-1 mb-1 d-block w-100">
                                                <i class="fa-solid fa-check-double me-1"></i> Selesai
                                            </span>
                                            <button class="btn btn-sm btn-outline-dark shadow-sm w-100" onclick="Swal.fire('Info', 'Fitur Audit Riwayat ORIN (Tahap 4) sedang disiapkan.', 'info')">
                                                <i class="fa-solid fa-clock-rotate-left"></i> Audit
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fa-solid fa-box-open fs-3 mb-2 opacity-50"></i><br>
                            <small>Belum ada tugas pengiriman.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <h5 class="text-muted">Data Sopir belum tersedia.</h5>
        </div>
        @endforelse
    </div>
</div>

{{-- ── Modal Peta Rute (TETAP AMAN) ── --}}
<div class="modal fade" id="modalRute" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalRuteLabel"><i class="fa-solid fa-map"></i> Rute Optimasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="position: relative;">
                <div id="mapArea" style="height: 600px; width: 100%; z-index: 1;"></div>
            </div>
            <div class="modal-footer bg-light">
                <small class="text-muted me-auto"><i class="fa-solid fa-circle-info"></i> OSRM AI: Urutan pengiriman telah dioptimalkan secara otomatis.</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Script Peta (Leaflet + OSRM) (TETAP AMAN) ── --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let map;
    let routeLayer;
    let markersLayer = L.layerGroup(); 

    const WAREHOUSE_LAT = -7.332500; 
    const WAREHOUSE_LNG = 112.774900;

    function formatWaktu(detik) {
        const m = Math.round(detik / 60);
        if (m < 60) return `${m} mnt`;
        const h = Math.floor(m / 60);
        const rm = m % 60;
        return `${h} jam ${rm} mnt`;
    }

    function formatJarak(meter) {
        return (meter / 1000).toFixed(1) + ' km';
    }

    function tampilkanRute(btnEl, driverName) {
        const routeData = JSON.parse(btnEl.getAttribute('data-rute'));
        document.getElementById('modalRuteLabel').innerHTML = `<i class="fa-solid fa-route me-2"></i>Rute Kurir: ${driverName}`;
        
        const modal = new bootstrap.Modal(document.getElementById('modalRute'));
        modal.show();

        document.getElementById('modalRute').addEventListener('shown.bs.modal', function () {
            
            if (!map) {
                map = L.map('mapArea').setView([WAREHOUSE_LAT, WAREHOUSE_LNG], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                markersLayer.addTo(map);
            }
            
            if (routeLayer) map.removeLayer(routeLayer);
            markersLayer.clearLayers();
            
            const oldInfo = document.getElementById('infoTotalRute');
            if(oldInfo) oldInfo.remove();

            const gudangIcon = L.divIcon({
                className: 'custom-icon',
                html: `<div style='background:#10b981; padding:8px; border-radius:50%; border:3px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.3);'><i class="fa-solid fa-warehouse text-white"></i></div>`,
                iconSize: [40, 40], iconAnchor: [20, 20]
            });
            const markerGudang = L.marker([WAREHOUSE_LAT, WAREHOUSE_LNG], {icon: gudangIcon}).addTo(markersLayer);

            let coordsString = `${WAREHOUSE_LNG},${WAREHOUSE_LAT}`;
            routeData.forEach(doItem => {
                const clng = parseFloat(doItem.lng);
                const clat = parseFloat(doItem.lat);
                coordsString += `;${clng},${clat}`;
            });

            const osrmUrl = `https://router.project-osrm.org/trip/v1/driving/${coordsString}?source=first&geometries=geojson&overview=full&steps=true`;

            Swal.fire({title: 'Meracik Rute Terbaik...', allowOutsideClick: false, didOpen: () => {Swal.showLoading()}});

            fetch(osrmUrl)
                .then(res => res.json())
                .then(data => {
                    Swal.close();
                    if (data.code === 'Ok') {
                        const trip = data.trips[0];

                        let boxInfo = document.createElement('div');
                        boxInfo.id = 'infoTotalRute';
                        boxInfo.className = 'bg-white p-2 px-3 shadow rounded position-absolute top-0 end-0 m-3';
                        boxInfo.style.zIndex = '1000';
                        boxInfo.style.borderLeft = '4px solid #10b981';
                        boxInfo.innerHTML = `
                            <span class="text-muted small fw-bold">TOTAL ESTIMASI (PP)</span><br>
                            <span class="text-dark fs-5 fw-bold"><i class="fa-solid fa-stopwatch text-warning me-1"></i> ${formatWaktu(trip.duration)}</span><br>
                            <span class="text-muted small"><i class="fa-solid fa-route me-1"></i> ${formatJarak(trip.distance)}</span>
                        `;
                        document.getElementById('mapArea').appendChild(boxInfo);

                        let outboundCoords = [];
                        let returnCoords = [];

                        trip.legs.forEach((leg, index) => {
                            const isReturnTrip = (index === trip.legs.length - 1);
                            leg.steps.forEach(step => {
                                if(step.geometry && step.geometry.coordinates) {
                                    const latLngs = step.geometry.coordinates.map(c => [c[1], c[0]]);
                                    if (isReturnTrip) {
                                        returnCoords.push(...latLngs);
                                    } else {
                                        outboundCoords.push(...latLngs);
                                    }
                                }
                            });
                        });

                        routeLayer = L.featureGroup([
                            L.polyline(outboundCoords, { color: '#3b82f6', weight: 6, opacity: 0.9 }),
                            L.polyline(returnCoords, { color: '#f43f5e', weight: 5, opacity: 0.8, dashArray: '10, 10' })
                        ]).addTo(map);

                        map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

                        const returnLeg = trip.legs[trip.legs.length - 1]; 
                        markerGudang.bindPopup(`
                            <div class="text-center">
                                <b class="text-success fs-6">📍 GUDANG (AWAL & AKHIR)</b>
                                <div class="mt-2 bg-light p-2 rounded small border text-center">
                                    <span class="text-muted" style="font-size: 0.7rem;">Rute Pulang (dari titik terakhir):</span><br>
                                    <span class="fw-bold text-dark"><i class="fa-solid fa-arrow-rotate-left text-danger me-1"></i> ${formatWaktu(returnLeg.duration)}</span> 
                                    <span class="text-muted ms-1">(${formatJarak(returnLeg.distance)})</span>
                                </div>
                            </div>
                        `);

                        data.waypoints.forEach((wp, index) => {
                            if (index === 0) return;
                            
                            const doData = routeData[index - 1]; 
                            const urutanOptimasi = wp.waypoint_index; 
                            
                            const arrivingLeg = trip.legs[urutanOptimasi - 1];

                            const urutanIcon = L.divIcon({
                                className: 'urutan-icon',
                                html: `<div style='background:#e11d48; color:white; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; border:2px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.4);'>${urutanOptimasi}</div>`,
                                iconSize: [32, 32],
                                iconAnchor: [16, 32],
                                popupAnchor: [0, -32]
                            });

                            L.marker([doData.lat, doData.lng], {icon: urutanIcon})
                             .bindPopup(`
                                <div class="text-center mb-2"><span class="badge bg-danger">Pengiriman ke-${urutanOptimasi}</span></div>
                                <b class="text-primary">${doData.do_number}</b><br>
                                <span class="small text-muted d-block mb-2">${doData.alamat}</span>
                                <div class="bg-light p-2 rounded small border mt-2 text-center">
                                    <span class="text-muted" style="font-size: 0.7rem;">Estimasi dari titik sebelumnya:</span><br>
                                    <span class="fw-bold text-dark"><i class="fa-solid fa-clock text-primary me-1"></i> ${formatWaktu(arrivingLeg.duration)}</span> 
                                    <span class="text-muted ms-1">(${formatJarak(arrivingLeg.distance)})</span>
                                </div>
                             `)
                             .addTo(markersLayer);
                        });
                    } else {
                        Swal.fire('Gagal', 'Tidak dapat menemukan jalan raya menuju kordinat tersebut.', 'error');
                    }
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('Error', 'Server OSRM gagal merespons. Pastikan koneksi internet stabil.', 'error');
                    console.error("OSRM Error:", err);
                });
        }, { once: true }); 
    }

    // ── PENCARIAN NOMINATIM (TETAP AMAN) ──
    function editAlamat(deliveryId, doNumber) {
        Swal.fire({
            title: 'Edit Alamat Tujuan',
            html: `
                <div class="text-start mb-3" style="position: relative;">
                    <label class="fw-bold mb-1 text-dark">No DO: <span class="text-primary">${doNumber}</span></label>
                    <input type="text" id="edit_search_alamat" class="form-control form-control-lg" placeholder="Ketik nama jalan / daerah baru..." autocomplete="off">
                    <ul id="edit_hasil_pencarian" class="dropdown-menu w-100 shadow-lg" style="display: none; position: absolute; max-height: 200px; overflow-y: auto; z-index: 9999; margin-top: 2px;"></ul>
                    <input type="hidden" id="edit_latitude">
                    <input type="hidden" id="edit_longitude">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Simpan Perubahan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0d6efd',
            didOpen: () => {
                const inputAlamat = Swal.getPopup().querySelector('#edit_search_alamat');
                const listHasil = Swal.getPopup().querySelector('#edit_hasil_pencarian');
                const inputLat = Swal.getPopup().querySelector('#edit_latitude');
                const inputLng = Swal.getPopup().querySelector('#edit_longitude');
                let timeoutId;

                inputAlamat.addEventListener('input', function() {
                    const query = this.value;
                    if (query.length < 3) {
                        listHasil.style.display = 'none';
                        return;
                    }

                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&countrycodes=id&limit=5`)
                            .then(res => res.json())
                            .then(hasil => {
                                listHasil.innerHTML = ''; 
                                if (hasil.length > 0) {
                                    listHasil.style.display = 'block';
                                    hasil.forEach(item => {
                                        const li = document.createElement('li');
                                        li.className = 'dropdown-item text-wrap border-bottom';
                                        li.style.cursor = 'pointer';
                                        li.style.fontSize = '0.85rem';
                                        li.textContent = item.display_name;
                                        
                                        li.addEventListener('click', function() {
                                            inputAlamat.value = item.display_name;
                                            inputLat.value = item.lat;
                                            inputLng.value = item.lon;
                                            listHasil.style.display = 'none';
                                        });

                                        listHasil.appendChild(li);
                                    });
                                } else {
                                    listHasil.style.display = 'none';
                                }
                            });
                    }, 500);
                });

                document.addEventListener('click', function(e) {
                    if (e.target !== inputAlamat && e.target !== listHasil) {
                        listHasil.style.display = 'none';
                    }
                });
            },
            preConfirm: () => {
                const alamat = document.getElementById('edit_search_alamat').value;
                const lat = document.getElementById('edit_latitude').value;
                const lng = document.getElementById('edit_longitude').value;
                
                if (!lat || !lng) {
                    Swal.showValidationMessage('Silakan cari dan klik alamat dari daftar dropdown!');
                    return false;
                }

                return { delivery_id: deliveryId, alamat_tujuan: alamat, lat: lat, lng: lng };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const dataInput = result.value;
                Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                fetch('{{ url("/delivery/update-alamat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        delivery_id: dataInput.delivery_id,
                        alamat_tujuan: dataInput.alamat_tujuan,
                        latitude: dataInput.lat,
                        longitude: dataInput.lng
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        Swal.fire({icon: 'success', title: 'Berhasil', text: res.message, timer: 1500, showConfirmButton: false})
                        .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                });
            }
        });
    }
</script>
@endsection