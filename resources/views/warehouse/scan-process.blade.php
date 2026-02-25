@extends('layouts.master')

@section('header', 'Smart Scanner (Final)')

@section('content')

{{-- ── Inject data untuk JS ── --}}
@php
    $isWaiting = $isWaiting ?? false;
@endphp

<div class="row">
    {{-- ── PANEL KIRI ── --}}
    <div class="col-md-4">

        {{-- Info SO --}}
        <div class="card-custom mb-3 border-0 shadow-sm text-white"
             style="background: {{ $isWaiting ? 'linear-gradient(135deg,#f59e0b,#d97706)' : 'linear-gradient(135deg,#2563eb,#1d4ed8)' }}">
            <h6 class="opacity-75 mb-1">Sales Order</h6>
            <h3 class="fw-bold mb-0">{{ $so['number'] }}</h3>
            <div class="mt-2 small d-flex justify-content-between">
                <span>{{ $so['customer']['name'] ?? 'Pelanggan Umum' }}</span>
                <span>{{ $so['transDate'] }}</span>
            </div>
            <div class="mt-2 d-flex justify-content-between align-items-center">
                <span class="badge bg-white fw-bold
                    {{ $isWaiting ? 'text-warning' : 'text-primary' }}">
                    {{ $so['status'] }}
                </span>
                @if($isWaiting)
                <span class="small opacity-75">
                    <i class="fa-solid fa-circle-half-stroke me-1"></i> Lanjut kirim sisa
                </span>
                @endif
            </div>
        </div>

        {{-- Scan area --}}
        <div class="card-custom mb-3 shadow-sm"
             style="border: 2px solid {{ $isWaiting ? '#f59e0b' : '#2563eb' }}">
            <div id="statusHeader" class="p-2 mb-2 rounded text-center fw-bold bg-dark text-white">
                <i class="fa-solid fa-barcode animate__animated animate__pulse animate__infinite"></i>
                SIAP SCAN
            </div>

            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-qrcode"></i>
                </span>
                <input type="text" class="form-control fw-bold border-start-0 fs-4"
                       id="barcodeInput" placeholder="Scan Barcode..."
                       autofocus autocomplete="off">
            </div>

            <div id="pesanFeedback" class="mt-2 fw-bold small p-2 rounded d-none"></div>

            @if($isWaiting)
            <div class="alert alert-warning mt-3 mb-0 small border-start border-4 border-warning fst-italic">
                <i class="fa-solid fa-circle-half-stroke me-1"></i>
                <strong>Mode Lanjut Sisa:</strong><br>
                Kolom <b>Target</b> = sisa yang harus dikirim hari ini.
                Kolom <b>Sdh Kirim</b> = sudah terkirim di DO sebelumnya.
            </div>
            @else
            <div class="alert alert-warning mt-3 mb-0 small border-start border-4 border-warning fst-italic">
                <i class="fa-solid fa-lock me-1"></i>
                <strong>Sistem Terkunci:</strong><br>
                Scan barang untuk membuka kunci input, lalu ketik jumlahnya.
            </div>
            @endif
        </div>

        {{-- Tombol Kirim --}}
        <button type="button"
                class="btn btn-secondary w-100 py-3 fw-bold fs-5 shadow-sm"
                id="btnSelesai" disabled
                onclick="kirimKeAccurate()">
            <i class="fa-solid fa-paper-plane me-2"></i> PROSES KIRIM
        </button>
        <a href="{{ url('/scan-so') }}" class="btn btn-outline-secondary w-100 mt-2">Kembali</a>
    </div>

    {{-- ── PANEL KANAN: Tabel Monitor ── --}}
    <div class="col-md-8">
        <div class="card-custom p-0 overflow-hidden shadow-sm" style="min-height: 500px;">
            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Monitor Stok & Progress</h6>
                <span class="badge bg-secondary rounded-pill fs-6" id="progressText">
                    0 / {{ count($so['detailItem']) }} Item
                </span>
            </div>

            <table class="table table-hover align-middle mb-0" id="tabelBarang">
                <thead class="text-secondary small bg-light">
                    <tr>
                        <th class="ps-4">Barang</th>
                        <th class="text-center">Target</th>
                        @if($isWaiting)
                        <th class="text-center bg-success bg-opacity-10 border-start border-end"
                            style="color:#16a34a">Sdh Kirim</th>
                        @endif
                        <th class="text-center bg-warning bg-opacity-10 text-dark border-start border-end">
                            Stok Real
                        </th>
                        <th class="text-center">Input Qty</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($so['detailItem'] as $item)
                    @php
                        $qtyOrdered   = $item['quantity'];
                        $qtyShipped   = $item['qty_shipped']   ?? 0;
                        $qtyRemaining = $item['qty_remaining'] ?? $qtyOrdered;
                        $stok         = $item['stok_gudang']   ?? 0;

                        // QUEUE  → maxQty dibatasi stok (tidak bisa kirim lebih dari stok tersedia)
                        // WAITING → maxQty = qty_remaining saja, TANPA batasi stok
                        //           karena stok sudah berkurang otomatis dari DO sebelumnya
                        $maxQty = $isWaiting ? $qtyRemaining : min($qtyRemaining, $stok);
                    @endphp

                    <tr id="row-{{ $item['item']['no'] }}" class="barang-row
                        {{ $qtyRemaining <= 0 ? 'table-success opacity-75' : '' }}"
                        data-code="{{ $item['item']['no'] }}"
                        data-barcode="{{ $item['barcode_asli'] ?? $item['item']['no'] }}"
                        data-name="{{ $item['item']['name'] }}"
                        data-target="{{ $qtyRemaining }}"
                        data-ordered="{{ $qtyOrdered }}"
                        data-shipped="{{ $qtyShipped }}"
                        data-stock="{{ $stok }}"
                        data-max="{{ $maxQty }}">

                        {{-- Kolom Barang --}}
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $item['item']['name'] }}</div>
                            <div class="badge bg-light text-dark border mt-1">
                                SKU: {{ $item['item']['no'] }}
                            </div>
                            @if(isset($item['barcode_asli']) && $item['barcode_asli'] != $item['item']['no'])
                            <div class="badge bg-info text-white border mt-1">
                                BC: {{ $item['barcode_asli'] }}
                            </div>
                            @endif
                            @if($qtyRemaining <= 0)
                            <div class="badge bg-success mt-1">
                                <i class="fa-solid fa-check me-1"></i>Sudah selesai
                            </div>
                            @endif
                        </td>

                        {{-- Kolom Target (= sisa / qty_remaining) --}}
                        <td class="text-center fw-bold fs-5 text-secondary">
                            {{ number_format($qtyRemaining) }}
                            @if($isWaiting && $qtyOrdered != $qtyRemaining)
                            <div class="text-muted" style="font-size:.65rem;">
                                dari {{ number_format($qtyOrdered) }} total
                            </div>
                            @endif
                        </td>

                        {{-- Kolom Sdh Kirim (hanya untuk WAITING) --}}
                        @if($isWaiting)
                        <td class="text-center fw-bold bg-success bg-opacity-10 border-start border-end text-success">
                            {{ number_format($qtyShipped) }}
                        </td>
                        @endif

                        {{-- Kolom Stok Real --}}
                        {{-- WAITING: stok 0 wajar (sudah berkurang dari DO sebelumnya), tidak ditampilkan merah --}}
                        <td class="text-center fw-bold fs-5 bg-warning bg-opacity-10 border-start border-end
                            {{ (!$isWaiting && $stok <= 0) ? 'text-danger' : 'text-dark' }}">
                            {{ $stok }}
                            @if($isWaiting && $stok <= 0)
                            <div class="text-muted" style="font-size:.65rem;">dari DO lama</div>
                            @elseif(!$isWaiting && $stok < $qtyRemaining && $qtyRemaining > 0)
                            <div class="text-danger" style="font-size:.65rem;">stok kurang</div>
                            @endif
                        </td>

                        {{-- Kolom Input Qty --}}
                        <td class="text-center">
                            @if($qtyRemaining <= 0)
                            {{-- Item sudah fully shipped sebelumnya — disabled permanen --}}
                            {{-- Tampilkan qty_ordered (bukan qty_shipped) karena shipped bisa over-count --}}
                            <input type="number"
                                   id="qty-{{ $item['item']['no'] }}"
                                   class="form-control text-center fw-bold border-success shadow-sm mx-auto"
                                   style="width: 80px; font-size: 1.2rem; background-color: #d1fae5;"
                                   value="{{ $qtyOrdered }}" disabled>
                            @else
                            <input type="number"
                                   id="qty-{{ $item['item']['no'] }}"
                                   class="form-control text-center fw-bold border-primary shadow-sm mx-auto"
                                   style="width: 80px; font-size: 1.2rem; background-color: #e9ecef;"
                                   value="0"
                                   min="0"
                                   max="{{ $maxQty }}"
                                   disabled
                                   onchange="manualUpdate('{{ $item['item']['no'] }}')"
                                   onkeydown="checkInputEnter(event)">
                            @endif
                        </td>

                        {{-- Kolom Status icon --}}
                        <td class="text-center">
                            @if($qtyRemaining <= 0)
                            <i class="fa-solid fa-circle-check text-success fs-4 status-icon"
                               id="icon-{{ $item['item']['no'] }}"></i>
                            @else
                            <i class="fa-regular fa-circle text-muted fs-4 status-icon"
                               id="icon-{{ $item['item']['no'] }}"></i>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ── Data dari Blade ──
    const soNumber  = "{{ $so['number'] }}";
    const soId      = "{{ $so['id'] }}";
    const isWaiting = {{ $isWaiting ? 'true' : 'false' }};
    const drivers = @json($drivers);

    // itemCounts: qty BARU yang discan sesi ini (tidak termasuk qty yang sudah shipped sebelumnya)
    let itemCounts = {};

    @foreach($so['detailItem'] as $item)
    @if(($item['qty_remaining'] ?? $item['quantity']) > 0)
        itemCounts['{{ $item['item']['no'] }}'] = 0;
    @endif
    @endforeach

    const audioSuccess = new Audio('https://actions.google.com/sounds/v1/cartoon/pop.ogg');
    const audioError   = new Audio('https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg');
    const audioLimit   = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');

    const inputEl   = document.getElementById('barcodeInput');
    const statusEl  = document.getElementById('statusHeader');
    const feedbackEl= document.getElementById('pesanFeedback');
    const btnSelesai= document.getElementById('btnSelesai');

    window.onload = () => { if (inputEl) inputEl.focus(); };

    inputEl.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = this.value.trim();
            this.value = '';
            if (val) prosesScan(val);
        }
    });

    // ── LOGIKA SCAN UTAMA ──
    function prosesScan(scannedValue) {
        // Cari row berdasarkan barcode
        let row = document.querySelector(`.barang-row[data-barcode="${scannedValue}"]`);

        // Fallback: cari berdasarkan SKU (code)
        if (!row) {
            row = document.querySelector(`.barang-row[data-code="${scannedValue}"]`);
        }

        if (!row) {
            showError('SALAH BARANG!', `Barcode <b>${scannedValue}</b> tidak ditemukan.`);
            return;
        }

        const code   = row.getAttribute('data-code');
        const name   = row.getAttribute('data-name');
        const maxQty = parseInt(row.getAttribute('data-max')) || 0;
        const target = parseInt(row.getAttribute('data-target')) || 0;

        // Item sudah selesai (remaining = 0)
        if (target <= 0) {
            showWarning('SUDAH SELESAI', `Item <b>${name}</b> sudah fully terkirim.`);
            return;
        }

        const inputQty   = document.getElementById(`qty-${code}`);
        const currentVal = parseInt(inputQty.value) || 0;

        // KASUS 1: Masih terkunci → buka kunci, set ke 1
        if (inputQty.disabled) {
            inputQty.disabled          = false;
            inputQty.style.backgroundColor = '#fff';
            inputQty.value             = 1;
            itemCounts[code]           = 1;

            updateUI(code, 1, target);
            audioSuccess.play();

            statusEl.className = 'p-2 mb-2 rounded text-center fw-bold bg-success text-white';
            statusEl.innerHTML = `TERBUKA: ${name}`;
            tampilkanPesan('success', `🔓 <b>${name}</b> terbuka. Ketik jumlah lalu Enter.`);

            setTimeout(() => { inputQty.focus(); inputQty.select(); }, 50);
            return;
        }

        // KASUS 2: Sudah terbuka, scan lagi = +1
        if (currentVal >= maxQty) {
            showWarning('SUDAH LENGKAP', `Item <b>${name}</b> sudah mencapai batas.`);
            inputQty.focus(); inputQty.select();
            return;
        }

        const newVal     = currentVal + 1;
        inputQty.value   = newVal;
        itemCounts[code] = newVal;
        updateUI(code, newVal, target);
        audioSuccess.play();
        inputQty.focus(); inputQty.select();
    }

    // ── Validasi input manual ──
    function manualUpdate(code) {
        const row    = document.getElementById(`row-${code}`);
        const maxQty = parseInt(row.getAttribute('data-max')) || 0;
        const target = parseInt(row.getAttribute('data-target')) || 0;
        const inputQty = document.getElementById(`qty-${code}`);

        let val = parseInt(inputQty.value);
        if (isNaN(val) || val < 0) val = 0;

        if (val > maxQty) {
            audioLimit.play();
            val = maxQty;
            Swal.fire({
                icon: 'warning',
                title: 'Melebihi Batas',
                text: `Maksimal: ${maxQty} (stok tersedia)`,
                timer: 1500,
                showConfirmButton: false
            });
        }

        inputQty.value   = val;
        itemCounts[code] = val;
        updateUI(code, val, target);
    }

    function checkInputEnter(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.target.blur();
            inputEl.focus();
        }
    }

    // ── Update warna row & ikon status ──
    function updateUI(code, current, target) {
        const row  = document.getElementById(`row-${code}`);
        const icon = document.getElementById(`icon-${code}`);

        if (current >= target && target > 0) {
            row.classList.add('table-success');
            row.classList.remove('table-warning');
            icon.className = 'fa-solid fa-circle-check text-success fs-4';
        } else if (current > 0) {
            row.classList.add('table-warning');
            row.classList.remove('table-success');
            icon.className = 'fa-solid fa-pen-to-square text-warning fs-4';
        } else {
            row.classList.remove('table-success', 'table-warning');
            icon.className = 'fa-regular fa-circle text-muted fs-4';
        }

        cekTombolKirim();
    }

    function cekTombolKirim() {
        const hasScan = Object.values(itemCounts).some(v => v > 0);
        if (hasScan) {
            btnSelesai.disabled   = false;
            btnSelesai.className  = 'btn btn-warning w-100 py-3 fw-bold fs-5 shadow-sm';
            btnSelesai.innerHTML  = '<i class="fa-solid fa-truck-fast me-2"></i> PROSES KIRIM';
        } else {
            btnSelesai.disabled   = true;
            btnSelesai.className  = 'btn btn-secondary w-100 py-3 fw-bold fs-5 shadow-sm';
            btnSelesai.innerHTML  = '<i class="fa-solid fa-paper-plane me-2"></i> PROSES KIRIM';
        }
    }

    function showError(title, msg) {
        audioError.play();
        statusEl.className = 'p-2 mb-2 rounded text-center fw-bold bg-danger text-white animate__animated animate__shakeX';
        statusEl.innerHTML = title;
        tampilkanPesan('error', `⛔ ${msg}`);
        setTimeout(() => inputEl.focus(), 100);
    }

    function showWarning(title, msg) {
        audioLimit.play();
        statusEl.className = 'p-2 mb-2 rounded text-center fw-bold bg-warning text-dark';
        statusEl.innerHTML = title;
        tampilkanPesan('error', `⚠️ ${msg}`);
    }

    function tampilkanPesan(type, html) {
        feedbackEl.innerHTML = html;
        feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        feedbackEl.classList.add('alert', type === 'success' ? 'alert-success' : 'alert-danger');
    }

    // ── Submit ke Accurate ──
    function kirimKeAccurate() {
        if (inputEl) inputEl.blur();

        const originalBtnText = btnSelesai.innerHTML;
        btnSelesai.disabled   = true;
        btnSelesai.innerHTML  = '<i class="fa-solid fa-spinner fa-spin me-2"></i> MENGIRIM...';

        Swal.fire({
            title: 'Memproses DO...',
            html: 'Menghubungkan ke API Accurate...<br><small>Mohon tunggu sebentar</small>',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // Tembak Data ke Endpoint Pembuat DO
        fetch('{{ url("/scan-process/submit") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept':       'application/json'
            },
            body: JSON.stringify({
                so_id:      soId,
                so_number:  soNumber,
                items:      itemCounts,
                is_waiting: isWaiting
            })
        })
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Server Error (${res.status}): ${text.substring(0, 200)}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                // 1. Buat elemen Dropdown Sopir
                let driverOptions = '<select id="driverSelect" class="form-select form-select-lg border-primary shadow-sm mt-1">';
                driverOptions += '<option value="">-- Pilih Armada / Sopir --</option>';
                drivers.forEach(d => {
                    driverOptions += `<option value="${d.id}">${d.name} (${d.license_plate})</option>`;
                });
                driverOptions += '</select>';

                // 2. Munculkan Pop-up Pilihan Sopir & Alamat
                Swal.fire({
                    icon: 'success',
                    title: 'DO Berhasil Dibuat!',
                    html: `
                        <div class="text-start bg-light p-3 rounded small mb-3 border">
                            <strong>No DO:</strong> <span class="text-primary">${data.do_number}</span><br>
                            <span class="text-muted">${data.message}</span>
                        </div>
                        
                        <div class="text-start mb-3" style="position: relative;">
                            <label class="fw-bold mb-1 text-dark"><i class="fa-solid fa-map-location-dot me-1"></i> Alamat Tujuan:</label>
                            <input type="text" id="search_alamat" class="form-control form-control-lg" placeholder="Ketik nama jalan / daerah..." autocomplete="off">
                            <ul id="hasil_pencarian" class="dropdown-menu w-100 shadow-lg" style="display: none; position: absolute; max-height: 200px; overflow-y: auto; z-index: 9999; margin-top: 2px;"></ul>
                            <input type="hidden" id="latitude">
                            <input type="hidden" id="longitude">
                        </div>

                        <div class="text-start">
                            <label class="fw-bold mb-1 text-dark"><i class="fa-solid fa-truck-fast me-1"></i> Tugaskan Pengiriman:</label>
                            ${driverOptions}
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Tugaskan & Cetak Surat Jalan',
                    cancelButtonText: 'Lewati (Tanpa Sopir)',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    
                    // TAMBAHAN: TAHAP 2 (Script Geocoding saat Modal Terbuka)
                    didOpen: () => {
                        const inputAlamat = Swal.getPopup().querySelector('#search_alamat');
                        const listHasil = Swal.getPopup().querySelector('#hasil_pencarian');
                        const inputLat = Swal.getPopup().querySelector('#latitude');
                        const inputLng = Swal.getPopup().querySelector('#longitude');
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

                        // Tutup dropdown jika klik di luar
                        document.addEventListener('click', function(e) {
                            if (e.target !== inputAlamat && e.target !== listHasil) {
                                listHasil.style.display = 'none';
                            }
                        });
                    },

                    preConfirm: () => {
                        const driverId = document.getElementById('driverSelect').value;
                        const alamat = document.getElementById('search_alamat').value;
                        const lat = document.getElementById('latitude').value;
                        const lng = document.getElementById('longitude').value;
                        
                        if (!driverId) {
                            Swal.showValidationMessage('Pilih sopir dulu, atau klik Lewati!');
                            return false;
                        }

                        // Mengembalikan data sebagai object
                        return { driver_id: driverId, alamat_tujuan: alamat, lat: lat, lng: lng };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const dataInput = result.value;
                        
                        Swal.fire({ title: 'Menugaskan Sopir...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                        // 3. Tembak data ke database lokal (Sudah menyertakan alamat & kordinat)
                        fetch('{{ url("/assign-driver") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                do_id: data.do_id,
                                do_number: data.do_number,
                                driver_id: dataInput.driver_id,
                                alamat_tujuan: dataInput.alamat_tujuan,
                                latitude: dataInput.lat,
                                longitude: dataInput.lng
                            })
                        })
                        .then(r => r.json())
                        .then(resAssign => {
                            if(resAssign.success) {
                                window.open(`{{ url('/print-do') }}/${data.do_id}`, '_blank');
                                setTimeout(() => { window.location.href = "{{ url('/scan-so') }}"; }, 1500);
                            } else {
                                Swal.fire('Error', resAssign.message, 'error');
                            }
                        });

                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // Jika klik "Lewati", tetap cetak DO tapi tanpa assign sopir
                        window.open(`{{ url('/print-do') }}/${data.do_id}`, '_blank');
                        setTimeout(() => { window.location.href = "{{ url('/scan-so') }}"; }, 1500);
                    }
                });
            } else {
                // Jika Accurate menolak pembuatan DO
                Swal.fire('Gagal!', data.message, 'error');
                btnSelesai.disabled  = false;
                btnSelesai.innerHTML = originalBtnText;
                if (inputEl) inputEl.focus();
            }
        })
        .catch(err => {
            console.error('DEBUG ERROR:', err);
            Swal.fire({ icon: 'error', title: 'Gagal Kirim', text: err.message });
            btnSelesai.disabled  = false;
            btnSelesai.innerHTML = originalBtnText;
            if (inputEl) inputEl.focus();
        });
    }
</script>
@endsection