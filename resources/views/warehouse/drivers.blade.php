@extends('layouts.master')

@section('header', 'Monitor Armada & Sopir')

@section('content')

{{-- ── Tambahkan CSS untuk Peta Leaflet ── --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid">
    <div class="alert alert-primary border-0 shadow-sm mb-4">
        <i class="fa-solid fa-truck-fast me-2"></i>
        Pantau Rute Optimasi AI (OSRM) dan Posisi Truk Asli (ORIN) secara Real-Time dalam satu layar.
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
                                $activeDOs = $driver->deliveries->where('waktu_kembali', null)->where('waktu_berangkat', '!=', null);
                                $activeCount = $activeDOs->count();

                                $routeData = $driver->deliveries->where('waktu_kembali', null)->filter(function($d) {
                                    return !empty($d->latitude) && !empty($d->longitude);
                                })->map(function($d) {
                                    return [
                                        'do_number' => $d->accurate_do_number,
                                        'lat'       => $d->latitude,
                                        'lng'       => $d->longitude
                                    ];
                                })->values()->toJson();
                            @endphp

                            @if($activeCount > 0)
                                <div class="spinner-grow text-warning spinner-grow-sm" role="status"></div>
                                <span class="fw-bold text-warning ms-1">{{ $activeCount }} Jalan</span>
                            @else
                                <span class="badge bg-success rounded-pill">Standby</span>
                            @endif
                        </div>
                    </div>

                    @if($activeCount > 0 && $routeData != '[]')
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary w-100 fw-bold" 
                                    data-rute="{{ $routeData }}" 
                                    onclick="tampilkanRute(this, '{{ $driver->name }}')">
                                <i class="fa-solid fa-map-location-dot me-1"></i> Rute Optimasi AI
                            </button>
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <hr class="text-muted opacity-25">
                    <h6 class="text-muted small fw-bold mb-3">DAFTAR PENGIRIMAN:</h6>
                    
                    @if($driver->deliveries->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($driver->deliveries as $delivery)
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start border-0 border-bottom">
                                    <div class="pe-2">
                                        <div class="fw-bold text-primary">{{ $delivery->accurate_do_number }}</div>
                                        
                                        @if($delivery->alamat_tujuan)
                                            <div class="small mt-1 text-secondary" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-location-dot me-1"></i> {{ \Illuminate\Support\Str::limit($delivery->alamat_tujuan, 30) }}
                                                @if(empty($delivery->waktu_kembali))
                                                <button class="btn btn-sm btn-link p-0 ms-1" onclick="editAlamat('{{ $delivery->id }}', '{{ $delivery->accurate_do_number }}')">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="text-end" style="min-width: 90px;">
                                        @if(empty($delivery->waktu_berangkat))
                                            <form action="{{ route('delivery.start', $delivery->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary mt-1 w-100 shadow-sm">Mulai</button>
                                            </form>
                                        @elseif(empty($delivery->waktu_kembali))
                                            <div class="d-flex flex-column gap-1 mt-1">
                                                
                                                @if(empty($driver->orin_device_sn))
                                                    <button type="button" class="btn btn-sm btn-secondary text-white shadow-sm w-100" 
                                                            onclick="setDriverSN('{{ $driver->id }}', '{{ $driver->name }}')">
                                                        <i class="fa-solid fa-plus"></i> Set SN GPS
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-info text-white shadow-sm w-100" 
                                                            data-rute="{{ $routeData }}"
                                                            onclick="showLiveTrack(this, '{{ $driver->orin_device_sn }}', '{{ $driver->name }}')">
                                                        <i class="fa-solid fa-satellite"></i> Live
                                                    </button>
                                                @endif

                                                <form action="{{ route('delivery.end', $delivery->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger shadow-sm w-100" onclick="return confirm('Selesai?')">Selesai</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success mt-1 mb-1 d-block">
                                                {{ $delivery->status === 'Selesai & Diaudit' ? '✅ Diaudit' : 'Selesai' }}
                                            </span>
                                            <button class="btn btn-sm w-100 {{ $delivery->status === 'Selesai & Diaudit' ? 'btn-success' : 'btn-warning text-dark' }}"
                                                    onclick="bukaAudit('{{ $delivery->id }}', '{{ $delivery->accurate_do_number }}')">
                                                <i class="fa-solid fa-{{ $delivery->status === 'Selesai & Diaudit' ? 'check-double' : 'clipboard-check' }} me-1"></i>
                                                {{ $delivery->status === 'Selesai & Diaudit' ? 'Lihat Audit' : 'Audit' }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
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

{{-- ── Modal Pusat Komando (LIVE + OSRM) ── --}}
<div class="modal fade" id="modalLiveTrack" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold" id="liveTrackLabel"><i class="fa-solid fa-satellite-dish me-2"></i>Pusat Komando</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="liveMapArea" style="height: 600px; width: 100%; z-index: 1;"></div>
                <div id="liveInfoBox" class="position-absolute top-0 end-0 m-3 p-3 bg-white shadow rounded border-start border-info border-4" style="z-index: 1000; display:none;">
                    <span class="small text-muted fw-bold d-block">Satelit ORIN</span>
                    <span id="liveStatusText" class="badge bg-success mb-2">MOVING</span><br>
                    <span class="fw-bold fs-4"><i class="fa-solid fa-gauge-high text-info me-1"></i> <span id="liveSpeedText">0</span> <span class="fs-6 text-muted">km/h</span></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Modal Audit Pasca-Pengiriman ── --}}
<div class="modal fade" id="modalAudit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e293b, #334155);">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fa-solid fa-clipboard-check me-2"></i>Audit Pasca-Pengiriman
                    </h5>
                    <small id="auditDoLabel" class="opacity-75"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                {{-- Loading State --}}
                <div id="auditLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>

                {{-- Form Audit --}}
                <form id="formAudit" style="display:none;">
                    <input type="hidden" id="auditDeliveryId">
                    <input type="hidden" id="auditId">

                    {{-- ── STEP 1: POD ── --}}
                    <div class="p-4 border-bottom">
                        <h6 class="fw-bold text-primary mb-3">
                            <span class="badge bg-primary me-2">1</span> Verifikasi Proof of Delivery (POD)
                        </h6>
                        <label class="form-label fw-semibold small text-muted">Status DO Fisik</label>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <div class="form-check form-check-inline border rounded px-3 py-2 flex-fill text-center audit-radio-card">
                                <input class="form-check-input" type="radio" name="pod_status" id="pod_lengkap" value="lengkap">
                                <label class="form-check-label w-100" for="pod_lengkap">
                                    <i class="fa-solid fa-circle-check text-success d-block fs-4 mb-1"></i>
                                    <span class="small fw-bold">Lengkap</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">TTD & Stempel</span>
                                </label>
                            </div>
                            <div class="form-check form-check-inline border rounded px-3 py-2 flex-fill text-center audit-radio-card">
                                <input class="form-check-input" type="radio" name="pod_status" id="pod_belum" value="belum_lengkap" checked>
                                <label class="form-check-label w-100" for="pod_belum">
                                    <i class="fa-solid fa-circle-exclamation text-warning d-block fs-4 mb-1"></i>
                                    <span class="small fw-bold">Belum Lengkap</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">Perlu Tindak Lanjut</span>
                                </label>
                            </div>
                            <div class="form-check form-check-inline border rounded px-3 py-2 flex-fill text-center audit-radio-card">
                                <input class="form-check-input" type="radio" name="pod_status" id="pod_tidak_ada" value="tidak_ada">
                                <label class="form-check-label w-100" for="pod_tidak_ada">
                                    <i class="fa-solid fa-circle-xmark text-danger d-block fs-4 mb-1"></i>
                                    <span class="small fw-bold">Tidak Ada</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">DO Hilang</span>
                                </label>
                            </div>
                        </div>
                        <textarea id="pod_catatan" class="form-control form-control-sm" rows="2"
                                  placeholder="Catatan kondisi DO fisik (opsional)..."></textarea>
                    </div>

                    {{-- ── STEP 2: RETUR ── --}}
                    <div class="p-4 border-bottom">
                        <h6 class="fw-bold text-warning mb-3">
                            <span class="badge bg-warning text-dark me-2">2</span> Pencatatan Retur Barang
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="ada_retur"
                                   onchange="document.getElementById('returDetail').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label fw-semibold" for="ada_retur">Ada barang yang diretur / dibawa pulang</label>
                        </div>
                        <div id="returDetail" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Persentase Retur</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="retur_persen" class="form-control" min="1" max="100" value="0"
                                               oninput="document.getElementById('returPersenLabel').innerText = this.value">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="mt-2">
                                        <input type="range" class="form-range" min="1" max="100" value="0" id="returSlider"
                                               oninput="document.getElementById('retur_persen').value = this.value; document.getElementById('returPersenLabel').innerText = this.value">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">0%</small>
                                            <small class="fw-bold text-danger"><span id="returPersenLabel">0</span>% diretur</small>
                                            <small class="text-muted">100%</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Alasan Retur</label>
                                    <select id="retur_alasan" class="form-select form-select-sm">
                                        <option value="">— Pilih Alasan —</option>
                                        <option value="barang_rusak">Barang Rusak / Cacat</option>
                                        <option value="salah_kirim">Salah Kirim / Salah Item</option>
                                        <option value="ditolak_pelanggan">Ditolak Pelanggan</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                    <textarea id="retur_catatan" class="form-control form-control-sm mt-2" rows="2"
                                              placeholder="Keterangan retur..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div id="returOk" class="text-success small mt-1">
                            <i class="fa-solid fa-circle-check me-1"></i>Pengiriman 100% sukses, tidak ada retur.
                        </div>
                    </div>

                    {{-- ── STEP 3: UANG JALAN ── --}}
                    <div class="p-4 border-bottom">
                        <h6 class="fw-bold text-info mb-3">
                            <span class="badge bg-info text-dark me-2">3</span> Rekonsiliasi Uang Jalan
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Uang yang Diberikan ke Sopir</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="uang_diberikan" class="form-control" min="0" value="0"
                                           oninput="hitungSisaUang()">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Biaya BBM (Rp)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="biaya_bbm" class="form-control" min="0" value="0"
                                           oninput="hitungSisaUang()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Biaya Tol (Rp)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="biaya_tol" class="form-control" min="0" value="0"
                                           oninput="hitungSisaUang()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Biaya Kuli (Rp)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="biaya_kuli" class="form-control" min="0" value="0"
                                           oninput="hitungSisaUang()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Biaya Lain-lain (Rp)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="biaya_lain" class="form-control" min="0" value="0"
                                           oninput="hitungSisaUang()">
                                </div>
                            </div>
                            <div class="col-12">
                                <textarea id="catatan_biaya" class="form-control form-control-sm" rows="1"
                                          placeholder="Keterangan biaya (opsional)..."></textarea>
                            </div>
                        </div>

                        {{-- Ringkasan Keuangan --}}
                        <div class="mt-3 p-3 rounded-3" style="background:#f8fafc;">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Total Biaya Dikeluarkan</span>
                                <span class="fw-bold" id="totalBiayaLabel">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Sisa Uang Jalan</span>
                                <span class="fw-bold fs-5" id="sisaUangLabel">Rp 0</span>
                            </div>
                            <div id="sisaUangBadge" class="mt-1"></div>
                        </div>
                    </div>

                    {{-- ── STEP 4: AKSI ACCURATE ── --}}
                    <div class="p-4">
                        <h6 class="fw-bold text-dark mb-3">
                            <span class="badge bg-dark me-2">4</span> Finalisasi di Accurate
                        </h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="form-check border rounded px-3 py-2 flex-fill audit-radio-card">
                                <input class="form-check-input" type="radio" name="accurate_action" id="acc_none" value="none" checked>
                                <label class="form-check-label w-100" for="acc_none">
                                    <i class="fa-solid fa-ban text-muted me-1"></i>
                                    <span class="fw-bold small">Tidak Ada</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">Hanya simpan audit lokal</span>
                                </label>
                            </div>
                            <div class="form-check border rounded px-3 py-2 flex-fill audit-radio-card">
                                <input class="form-check-input" type="radio" name="accurate_action" id="acc_close" value="close_do">
                                <label class="form-check-label w-100" for="acc_close">
                                    <i class="fa-solid fa-lock text-secondary me-1"></i>
                                    <span class="fw-bold small">Tutup DO</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">Ubah status DO → Closed</span>
                                </label>
                            </div>
                            <div class="form-check border rounded px-3 py-2 flex-fill audit-radio-card">
                                <input class="form-check-input" type="radio" name="accurate_action" id="acc_invoice" value="create_invoice">
                                <label class="form-check-label w-100" for="acc_invoice">
                                    <i class="fa-solid fa-file-invoice-dollar text-success me-1"></i>
                                    <span class="fw-bold small">Buat Invoice</span><br>
                                    <span class="text-muted" style="font-size:0.7rem">Auto-buat Sales Invoice</span>
                                </label>
                            </div>
                        </div>

                        <div id="auditApprovedBanner" class="alert alert-success mt-3 mb-0" style="display:none;">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            <strong>Audit sudah di-approve.</strong> Data terkunci dan tidak bisa diubah.
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer" id="auditFooter" style="display:none;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-secondary" id="btnSimpanDraft" onclick="simpanAudit()">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Draft
                </button>
                <button type="button" class="btn btn-success fw-bold" id="btnApprove" onclick="approveAudit()">
                    <i class="fa-solid fa-circle-check me-1"></i> Approve & Finalisasi
                </button>
            </div>

        </div>
    </div>
</div>

<style>
.audit-radio-card { cursor: pointer; transition: all .2s; }
.audit-radio-card:has(input:checked) { border-color: #3b82f6 !important; background: #eff6ff; }
.audit-radio-card .form-check-input { display: none; }
</style>

{{-- ── Modal Rute OSRM Murni ── --}}
<div class="modal fade" id="modalRute" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalRuteLabel">Rute Optimasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="position: relative;">
                <div id="mapArea" style="height: 600px; width: 100%; z-index: 1;"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const WAREHOUSE_LAT = -7.332500; 
    const WAREHOUSE_LNG = 112.774900;

    // ==========================================
    // LOGIKA PUSAT KOMANDO (LIVE ORIN + OSRM)
    // ==========================================
    let liveMap, liveMarker, liveInterval, plannedRouteLayer;
    let currentLiveSn = null;
    let currentRouteData = [];

    // ─── STATE GPS UNTUK AUTO-ROUTE SEPERTI GOOGLE MAPS ───
    let lastGpsLat = null;
    let lastGpsLng = null;
    const ROUTE_REDRAW_THRESHOLD = 0.0005; // ~55 meter, re-route jika bergerak sejauh ini

    // FUNGSI INI SEKARANG DIJAMIN AMAN DARI ERROR "LENGTH"
    function showLiveTrack(btnEl, sn, name) {
        const cleanSn = sn ? sn.trim() : '';
        if (cleanSn === '') {
            Swal.fire('Error!', 'Sopir ini belum memiliki SN GPS!', 'error');
            return;
        }

        currentLiveSn = cleanSn;
        const rawData = btnEl.getAttribute('data-rute');
        currentRouteData = rawData ? JSON.parse(rawData) : [];

        document.getElementById('liveTrackLabel').innerHTML = `<i class="fa-solid fa-satellite-dish me-2"></i>Pusat Komando: ${name}`;
        
        const modal = new bootstrap.Modal(document.getElementById('modalLiveTrack'));
        modal.show();
    }

    document.getElementById('modalLiveTrack').addEventListener('shown.bs.modal', function () {
        if (!liveMap) {
            liveMap = L.map('liveMapArea').setView([WAREHOUSE_LAT, WAREHOUSE_LNG], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(liveMap);
            plannedRouteLayer = L.featureGroup().addTo(liveMap);
        }
        
        setTimeout(() => {
            liveMap.invalidateSize();
            plannedRouteLayer.clearLayers();
            lastGpsLat = null; // Reset state, paksa redraw rute dari GPS pertama kali
            lastGpsLng = null;

            // Langsung ambil posisi GPS — rute akan digambar dari sana
            updateLivePosition(currentLiveSn);
        }, 300);

        clearInterval(liveInterval);
        liveInterval = setInterval(() => updateLivePosition(currentLiveSn), 15000);
    });

    document.getElementById('modalLiveTrack').addEventListener('hidden.bs.modal', function () {
        clearInterval(liveInterval);
        lastGpsLat = null;
        lastGpsLng = null;
        if (liveMarker) { liveMap.removeLayer(liveMarker); liveMarker = null; }
    });

    // ─── GAMBAR RUTE OSRM — START DARI GPS MOBIL (BUKAN GUDANG) ───
    // startLat/startLng = posisi GPS mobil saat ini (default ke gudang jika GPS belum tersedia)
    function drawPlannedRoute(routeData, startLat, startLng) {
        const fromLat = startLat ?? WAREHOUSE_LAT;
        const fromLng = startLng ?? WAREHOUSE_LNG;

        // Titik awal = posisi GPS mobil sekarang
        let coordsString = `${fromLng},${fromLat}`;
        routeData.forEach(doItem => {
            coordsString += `;${doItem.lng},${doItem.lat}`;
        });

        // Pakai /route/ bukan /trip/ — satu arah saja, tidak balik ke awal (seperti Google Maps)
        const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${coordsString}?geometries=geojson&overview=full`;

        fetch(osrmUrl)
            .then(res => res.json())
            .then(data => {
                if (data.code === 'Ok') {
                    const geometry = data.routes[0].geometry;
                    const latLngs = geometry.coordinates.map(c => [c[1], c[0]]);
                    
                    L.polyline(latLngs, { 
                        color: '#3b82f6', weight: 6, opacity: 0.7, dashArray: '10, 10' 
                    }).addTo(plannedRouteLayer);

                    // Marker titik awal = posisi GPS mobil (bukan gudang)
                    L.marker([fromLat, fromLng], {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background:#22c55e; color:white; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; font-size:16px; border:2px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.4);">📍</div>`,
                            iconSize: [30, 30], iconAnchor: [15, 15]
                        })
                    }).bindPopup('<b>Posisi GPS Sekarang</b>').addTo(plannedRouteLayer);

                    routeData.forEach((doItem, index) => {
                        L.marker([doItem.lat, doItem.lng], {
                            icon: L.divIcon({
                                className: 'custom-div-icon',
                                html: `<div style="background-color:#0f172a; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-weight:bold; border:2px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.4);">${index+1}</div>`,
                                iconSize: [26, 26], iconAnchor: [13, 13]
                            })
                        }).bindPopup(`<b>Tujuan ${index+1}:</b> ${doItem.do_number}`).addTo(plannedRouteLayer);
                    });

                    // Fit bounds hanya saat pertama kali (lastGpsLat masih sama dengan fromLat)
                    liveMap.fitBounds(plannedRouteLayer.getBounds(), { padding: [50, 50] });
                }
            })
            .catch(err => console.error('OSRM Error:', err));
    }

    function updateLivePosition(sn) {
        fetch(`/api/track-driver/${sn}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const lat = parseFloat(res.data.lat);
                    const lng = parseFloat(res.data.lng);
                    const speed = res.data.speed;
                    const status = res.data.status; 

                    if (isNaN(lat) || isNaN(lng)) return;

                    const pos = [lat, lng];
                    const color = status === 'MOVING' ? '#22c55e' : '#f59e0b';
                    const iconEmoji = status === 'MOVING' ? '🚛' : '🅿️';

                    const icon = L.divIcon({
                        html: `<div style="background:${color}; width:40px; height:40px; border-radius:50%; border:3px solid white; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 4px 10px rgba(0,0,0,0.5); z-index:9999;">${iconEmoji}</div>`,
                        iconSize: [40, 40], iconAnchor: [20, 20]
                    });

                    if (!liveMarker) { 
                        liveMarker = L.marker(pos, { icon: icon }).addTo(liveMap);
                    } else { 
                        liveMarker.setLatLng(pos); 
                        liveMarker.setIcon(icon); 
                    }

                    // ─── AUTO-CENTER SEPERTI GOOGLE MAPS ───
                    liveMap.panTo(pos, { animate: true, duration: 1.0 });

                    // ─── RE-ROUTE DARI POSISI GPS JIKA SUDAH BERGERAK ───
                    const distMoved = (lastGpsLat !== null)
                        ? Math.abs(lat - lastGpsLat) + Math.abs(lng - lastGpsLng)
                        : Infinity; // Pertama kali = anggap bergerak jauh

                    if (distMoved > ROUTE_REDRAW_THRESHOLD && currentRouteData && currentRouteData.length > 0) {
                        // Bersihkan rute lama, gambar ulang dari posisi GPS sekarang
                        plannedRouteLayer.clearLayers();
                        drawPlannedRoute(currentRouteData, lat, lng);
                        lastGpsLat = lat;
                        lastGpsLng = lng;
                    } else if (lastGpsLat === null) {
                        // GPS pertama kali, tidak ada tujuan (hanya marker truk)
                        lastGpsLat = lat;
                        lastGpsLng = lng;
                    }

                    document.getElementById('liveInfoBox').style.display = 'block';
                    document.getElementById('liveStatusText').innerText = status;
                    document.getElementById('liveStatusText').className = `badge bg-${status === 'MOVING' ? 'success' : 'warning'} mb-1`;
                    document.getElementById('liveSpeedText').innerText = speed;
                }
            })
            .catch(err => console.error("Error Tracking:", err));
    }

    // ==========================================
    // FUNGSI INPUT SN (UI)
    // ==========================================
    function setDriverSN(driverId, driverName) {
        Swal.fire({
            title: 'Hubungkan GPS ORIN',
            text: `Masukkan Serial Number (SN) perangkat untuk armada ${driverName}`,
            input: 'text',
            inputPlaceholder: 'Contoh: 86300xxxx',
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            preConfirm: (sn) => {
                if (!sn) Swal.showValidationMessage('SN tidak boleh kosong!');
                return sn;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ url("/driver/update-sn") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ driver_id: driverId, sn: result.value })
                })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
            }
        });
    }

    // ==========================================
    // LOGIKA OSRM & NOMINATIM LAMA (TETAP AMAN)
    // ==========================================
    let map;
    let routeLayer;
    let markersLayer = L.layerGroup(); 

    function tampilkanRute(btnEl, driverName) {
        const rawData = btnEl.getAttribute('data-rute');
        const routeData = rawData ? JSON.parse(rawData) : [];
        document.getElementById('modalRuteLabel').innerHTML = `Rute Kurir: ${driverName}`;
        const modal = new bootstrap.Modal(document.getElementById('modalRute'));
        modal.show();

        document.getElementById('modalRute').addEventListener('shown.bs.modal', function () {
            if (!map) {
                map = L.map('mapArea').setView([WAREHOUSE_LAT, WAREHOUSE_LNG], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                markersLayer.addTo(map);
            }
            
            if (routeLayer) map.removeLayer(routeLayer);
            markersLayer.clearLayers();
            
            const gudangIcon = L.divIcon({
                className: 'custom-icon',
                html: `<div style='background:#10b981; padding:8px; border-radius:50%; border:3px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.3);'><i class=\"fa-solid fa-warehouse text-white\"></i></div>`,
                iconSize: [40, 40], iconAnchor: [20, 20]
            });
            L.marker([WAREHOUSE_LAT, WAREHOUSE_LNG], {icon: gudangIcon}).addTo(markersLayer);

            let coordsString = `${WAREHOUSE_LNG},${WAREHOUSE_LAT}`;
            routeData.forEach(doItem => {
                coordsString += `;${doItem.lng},${doItem.lat}`;
            });

            // Pakai /route/ — satu arah saja, tidak ada rute balik
            const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${coordsString}?geometries=geojson&overview=full`;

            Swal.fire({title: 'Meracik Rute...', allowOutsideClick: false, didOpen: () => {Swal.showLoading()}});

            fetch(osrmUrl)
                .then(res => res.json())
                .then(data => {
                    Swal.close();
                    if (data.code === 'Ok') {
                        const geometry = data.routes[0].geometry;
                        const latLngs = geometry.coordinates.map(c => [c[1], c[0]]);

                        // Satu garis biru saja — tidak ada garis merah balik
                        routeLayer = L.polyline(latLngs, {
                            color: '#3b82f6', weight: 6, opacity: 0.9
                        }).addTo(map);

                        map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

                        // Marker tujuan — nomor urut dari 1
                        routeData.forEach((doData, index) => {
                            const urutanIcon = L.divIcon({
                                html: `<div style='background:#e11d48; color:white; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-weight:900; border:2px solid white; box-shadow:0 3px 6px rgba(0,0,0,0.4);'>${index + 1}</div>`,
                                iconSize: [32, 32], iconAnchor: [16, 32]
                            });
                            L.marker([doData.lat, doData.lng], {icon: urutanIcon})
                             .bindPopup(`<b>${doData.do_number}</b>`)
                             .addTo(markersLayer);
                        });
                    }
                });
        }, { once: true }); 
    }

    // ==========================================
    // FITUR AUDIT PASCA-PENGIRIMAN
    // ==========================================
    let currentAuditId = null;
    let isAuditApproved = false;

    function bukaAudit(deliveryId, doNumber) {
        document.getElementById('auditLoading').style.display = 'block';
        document.getElementById('formAudit').style.display = 'none';
        document.getElementById('auditFooter').style.display = 'none';
        document.getElementById('auditDoLabel').innerText = 'DO: ' + doNumber;
        document.getElementById('auditId').value = '';
        document.getElementById('auditDeliveryId').value = deliveryId;
        isAuditApproved = false;
        // Reset retur toggle
        document.getElementById('ada_retur').checked = false;
        document.getElementById('returDetail').style.display = 'none';
        document.getElementById('returOk').style.display = 'block';

        const modal = new bootstrap.Modal(document.getElementById('modalAudit'));
        modal.show();

        fetch(`/audit/data/${deliveryId}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('auditLoading').style.display = 'none';
                if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }

                const a = res.existing_audit;
                if (a) {
                    currentAuditId = a.id;
                    document.getElementById('auditId').value = a.id;
                    isAuditApproved = (a.status === 'approved');

                    const podRadio = document.querySelector(`input[name="pod_status"][value="${a.pod_status}"]`);
                    if (podRadio) podRadio.checked = true;
                    document.getElementById('pod_catatan').value = a.pod_catatan ?? '';

                    if (a.ada_retur) {
                        document.getElementById('ada_retur').checked = true;
                        document.getElementById('returDetail').style.display = 'block';
                        document.getElementById('returOk').style.display = 'none';
                        document.getElementById('retur_persen').value = a.retur_persen ?? 0;
                        document.getElementById('returSlider').value  = a.retur_persen ?? 0;
                        document.getElementById('returPersenLabel').innerText = a.retur_persen ?? 0;
                        if (a.retur_alasan) document.getElementById('retur_alasan').value = a.retur_alasan;
                        document.getElementById('retur_catatan').value = a.retur_catatan ?? '';
                    }

                    document.getElementById('uang_diberikan').value = a.uang_diberikan ?? 0;
                    document.getElementById('biaya_bbm').value      = a.biaya_bbm  ?? 0;
                    document.getElementById('biaya_tol').value      = a.biaya_tol  ?? 0;
                    document.getElementById('biaya_kuli').value     = a.biaya_kuli ?? 0;
                    document.getElementById('biaya_lain').value     = a.biaya_lain ?? 0;
                    document.getElementById('catatan_biaya').value  = a.catatan_biaya ?? '';
                    hitungSisaUang();

                    const accRadio = document.querySelector(`input[name="accurate_action"][value="${a.accurate_action ?? 'none'}"]`);
                    if (accRadio) accRadio.checked = true;
                }

                if (isAuditApproved) {
                    document.getElementById('auditApprovedBanner').style.display = 'block';
                    document.getElementById('btnSimpanDraft').style.display = 'none';
                    document.getElementById('btnApprove').style.display = 'none';
                    document.querySelectorAll('#formAudit input, #formAudit textarea, #formAudit select')
                            .forEach(el => el.disabled = true);
                } else {
                    document.getElementById('auditApprovedBanner').style.display = 'none';
                    document.getElementById('btnSimpanDraft').style.display = '';
                    document.getElementById('btnApprove').style.display = '';
                }

                document.getElementById('formAudit').style.display = 'block';
                document.getElementById('auditFooter').style.display = 'flex';
            })
            .catch(() => {
                document.getElementById('auditLoading').style.display = 'none';
                Swal.fire('Error', 'Gagal memuat data audit.', 'error');
            });
    }

    function hitungSisaUang() {
        const diberikan = parseFloat(document.getElementById('uang_diberikan').value) || 0;
        const total = ['biaya_bbm','biaya_tol','biaya_kuli','biaya_lain']
                      .reduce((s, id) => s + (parseFloat(document.getElementById(id).value) || 0), 0);
        const sisa  = diberikan - total;
        const fmt   = n => 'Rp ' + n.toLocaleString('id-ID');
        document.getElementById('totalBiayaLabel').innerText = fmt(total);
        document.getElementById('sisaUangLabel').innerText   = fmt(sisa);
        const badge = document.getElementById('sisaUangBadge');
        badge.innerHTML = sisa > 0
            ? `<span class="badge bg-success">Sisa kembalikan: ${fmt(sisa)}</span>`
            : sisa < 0
            ? `<span class="badge bg-danger">Kurang (reimburse): ${fmt(Math.abs(sisa))}</span>`
            : `<span class="badge bg-secondary">Pas / Nihil</span>`;
    }

    function kumpulkanDataAudit() {
        return {
            delivery_id:     document.getElementById('auditDeliveryId').value,
            pod_status:      document.querySelector('input[name="pod_status"]:checked')?.value ?? 'belum_lengkap',
            pod_catatan:     document.getElementById('pod_catatan').value,
            ada_retur:       document.getElementById('ada_retur').checked ? 1 : 0,
            retur_persen:    document.getElementById('retur_persen').value,
            retur_alasan:    document.getElementById('retur_alasan').value,
            retur_catatan:   document.getElementById('retur_catatan').value,
            uang_diberikan:  document.getElementById('uang_diberikan').value,
            biaya_bbm:       document.getElementById('biaya_bbm').value,
            biaya_tol:       document.getElementById('biaya_tol').value,
            biaya_kuli:      document.getElementById('biaya_kuli').value,
            biaya_lain:      document.getElementById('biaya_lain').value,
            catatan_biaya:   document.getElementById('catatan_biaya').value,
            accurate_action: document.querySelector('input[name="accurate_action"]:checked')?.value ?? 'none',
        };
    }

    function simpanAudit() {
        const btn = document.getElementById('btnSimpanDraft');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
        fetch('{{ url("/audit/save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(kumpulkanDataAudit()),
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Simpan Draft';
            if (res.success) {
                document.getElementById('auditId').value = res.audit_id;
                currentAuditId = res.audit_id;
                Swal.fire({ icon: 'success', title: 'Tersimpan!', text: res.message, timer: 1800, showConfirmButton: false });
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Simpan Draft';
            Swal.fire('Error', 'Koneksi gagal.', 'error');
        });
    }

    function approveAudit() {
        Swal.fire({
            title: 'Approve & Finalisasi?',
            html: `<div class="text-start small"><p>Setelah di-approve, data audit <strong>tidak bisa diubah lagi</strong>.</p>
                   <p>Aksi ke Accurate yang dipilih akan langsung dijalankan.</p></div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Approve!',
            confirmButtonColor: '#16a34a',
            cancelButtonText: 'Batal',
        }).then(async result => {
            if (!result.isConfirmed) return;

            const saveRes = await fetch('{{ url("/audit/save") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(kumpulkanDataAudit()),
            }).then(r => r.json());

            if (!saveRes.success) { Swal.fire('Error', saveRes.message, 'error'); return; }

            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(`/audit/approve/${saveRes.audit_id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Audit Disetujui!', text: res.message })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            });
        });
    }

    function editAlamat(deliveryId, doNumber) {
        Swal.fire({
            title: 'Edit Alamat Tujuan',
            html: `<input type=\"text\" id=\"edit_search_alamat\" class=\"form-control\" placeholder=\"Cari alamat...\">
                   <ul id=\"edit_hasil_pencarian\" class=\"dropdown-menu w-100\" style=\"display:none; position:relative;\"></ul>
                   <input type=\"hidden\" id=\"edit_latitude\"><input type=\"hidden\" id=\"edit_longitude\">`,
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            didOpen: () => {
                const inputAlamat = document.getElementById('edit_search_alamat');
                const listHasil = document.getElementById('edit_hasil_pencarian');
                inputAlamat.addEventListener('input', function() {
                    const query = this.value;
                    if (query.length < 3) return;
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&countrycodes=id&limit=5`)
                        .then(res => res.json())
                        .then(hasil => {
                            listHasil.innerHTML = ''; 
                            if (hasil.length > 0) {
                                listHasil.style.display = 'block';
                                hasil.forEach(item => {
                                    const li = document.createElement('li');
                                    li.className = 'dropdown-item text-wrap';
                                    li.textContent = item.display_name;
                                    li.addEventListener('click', function() {
                                        inputAlamat.value = item.display_name;
                                        document.getElementById('edit_latitude').value = item.lat;
                                        document.getElementById('edit_longitude').value = item.lon;
                                        listHasil.style.display = 'none';
                                    });
                                    listHasil.appendChild(li);
                                });
                            }
                        });
                });
            },
            preConfirm: () => {
                return { delivery_id: deliveryId, alamat_tujuan: document.getElementById('edit_search_alamat').value, 
                         lat: document.getElementById('edit_latitude').value, lng: document.getElementById('edit_longitude').value };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ url("/delivery/update-alamat") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify(result.value)
                })
                .then(r => r.json())
                .then(res => {
                    if(res.success) location.reload();
                });
            }
        });
    }
</script>
@endsection