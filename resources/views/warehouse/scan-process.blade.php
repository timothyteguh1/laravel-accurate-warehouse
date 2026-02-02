@extends('layouts.master')

@section('header', 'Smart Scanner (Final)')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card-custom mb-3 bg-primary text-white border-0 shadow-sm">
            <h6 class="opacity-75 mb-1">Sales Order</h6>
            <h3 class="fw-bold mb-0">{{ $so['number'] }}</h3>
            <div class="mt-2 small d-flex justify-content-between">
                <span>{{ $so['customer']['name'] ?? 'Pelanggan Umum' }}</span>
                <span>{{ $so['transDate'] }}</span>
            </div>
            <div class="mt-2 text-end">
                <span class="badge bg-white text-primary fw-bold">{{ $so['status'] }}</span>
            </div>
        </div>

        <div class="card-custom mb-3 border-primary border-3 shadow-lg">
            <div id="statusHeader" class="p-2 mb-2 rounded text-center fw-bold bg-dark text-white">
                <i class="fa-solid fa-barcode animate__animated animate__pulse animate__infinite"></i> SIAP SCAN
            </div>
            
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-qrcode"></i></span>
                <input type="text" class="form-control fw-bold border-start-0 fs-4" id="barcodeInput" 
                       placeholder="Scan Barcode..." autofocus autocomplete="off">
            </div>
            
            <div id="pesanFeedback" class="mt-2 fw-bold small p-2 rounded d-none"></div>

            <div class="alert alert-warning mt-3 mb-0 small border-start border-4 border-warning fst-italic">
                <i class="fa-solid fa-lock"></i> <strong>Sistem Terkunci:</strong><br>
                Scan barang untuk membuka kunci input, lalu ketik jumlahnya.
            </div>
        </div>

        <button type="button" class="btn btn-secondary w-100 py-3 fw-bold fs-5 shadow-sm" id="btnSelesai" disabled onclick="kirimKeAccurate()">
            <i class="fa-solid fa-paper-plane me-2"></i> PROSES KIRIM
        </button>
        <a href="{{ url('/scan-so') }}" class="btn btn-outline-secondary w-100 mt-2">Kembali</a>
    </div>

    <div class="col-md-8">
        <div class="card-custom p-0 overflow-hidden shadow-sm" style="min-height: 500px;">
            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Monitor Stok & Progress</h6>
                <span class="badge bg-secondary rounded-pill fs-6" id="progressText">0 / {{ count($so['detailItem']) }} Item</span>
            </div>
            
            <table class="table table-hover align-middle mb-0" id="tabelBarang">
                <thead class="text-secondary small bg-light">
                    <tr>
                        <th class="ps-4">Barang</th>
                        <th class="text-center">Target</th>
                        <th class="text-center bg-warning bg-opacity-10 text-dark border-start border-end">Stok Real</th> 
                        <th class="text-center">Input Qty</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($so['detailItem'] as $item)
                    @php
                        $maxQty = min($item['quantity'], $item['stok_gudang'] ?? 0);
                    @endphp
                    <tr id="row-{{ $item['item']['no'] }}" class="barang-row"
                        data-code="{{ $item['item']['no'] }}" 
                        data-barcode="{{ $item['barcode_asli'] ?? $item['item']['no'] }}" 
                        data-name="{{ $item['item']['name'] }}"
                        data-target="{{ $item['quantity'] }}"
                        data-stock="{{ $item['stok_gudang'] ?? 0 }}"
                        data-max="{{ $maxQty }}"> 
                        
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
                        </td>
                        
                        <td class="text-center fw-bold fs-5 text-secondary">
                            {{ number_format($item['quantity'], 0) }}
                        </td>

                        <td class="text-center fw-bold fs-5 bg-warning bg-opacity-10 border-start border-end text-dark">
                            {{ $item['stok_gudang'] ?? 0 }}
                        </td>

                        {{-- Input Default: DISABLED (Terkunci & Abu-abu) --}}
                        <td class="text-center">
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
                        </td>
                        
                        <td class="text-center">
                            <i class="fa-regular fa-circle text-muted fs-4 status-icon" id="icon-{{ $item['item']['no'] }}"></i>
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
    let itemCounts = {}; 
    const soNumber = "{{ $so['number'] }}";
    
    @foreach($so['detailItem'] as $item)
        itemCounts['{{ $item['item']['no'] }}'] = 0;
    @endforeach

    const audioSuccess = new Audio('https://actions.google.com/sounds/v1/cartoon/pop.ogg');
    const audioError   = new Audio('https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg');
    const audioLimit   = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');

    const inputEl = document.getElementById('barcodeInput');
    const statusEl = document.getElementById('statusHeader');
    const feedbackEl = document.getElementById('pesanFeedback');
    const btnSelesai = document.getElementById('btnSelesai');

    // Fokus ke Barcode saat awal load
    window.onload = function() { 
        if(inputEl) inputEl.focus(); 
    }

    inputEl.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let scannedValue = this.value.trim();
            this.value = '';
            if (scannedValue === "") return;
            prosesScan(scannedValue);
        }
    });

    // LOGIC UTAMA
    function prosesScan(scannedValue) {
        let row = document.querySelector(`.barang-row[data-barcode="${scannedValue}"]`);

        if (!row) {
            showError('SALAH BARANG!', `Barcode <b>${scannedValue}</b> tidak ditemukan.`);
            return;
        }

        let code = row.getAttribute('data-code');
        let name = row.getAttribute('data-name');
        let maxQty = parseInt(row.getAttribute('data-max')) || 0;
        
        let inputQty = document.getElementById(`qty-${code}`);
        let currentVal = parseInt(inputQty.value) || 0;

        // KASUS 1: Masih Terkunci (Belum Inisialisasi)
        if (inputQty.disabled) {
            // 1. Buka Kunci
            inputQty.disabled = false;
            inputQty.style.backgroundColor = "#fff"; // Ubah jadi putih
            
            // 2. Set Angka Awal = 1
            inputQty.value = 1;
            itemCounts[code] = 1;
            
            updateUI(code, 1, maxQty);
            
            // 3. FOKUS & SELECT (Agar User Bisa Langsung Ketik Angka Lain)
            // 'select()' akan memblok angka 1, jadi kalau user ketik '10', angka 1 hilang terganti 10.
            setTimeout(() => {
                inputQty.focus();
                inputQty.select();
            }, 50);

            audioSuccess.play();
            statusEl.className = "p-2 mb-2 rounded text-center fw-bold bg-success text-white";
            statusEl.innerHTML = `TERBUKA: ${name}`;
            tampilkanPesan('success', `🔓 <b>${name}</b> terbuka. Ketik jumlah lalu Enter.`);
            return; 
        }

        // KASUS 2: Sudah Terbuka (Scan Lagi = +1 atau Fokus Ulang)
        if (currentVal >= maxQty) {
            showWarning('SUDAH LENGKAP', `Item <b>${name}</b> sudah penuh.`);
            inputQty.focus();
            inputQty.select();
            return;
        }

        let newVal = currentVal + 1;
        inputQty.value = newVal;
        itemCounts[code] = newVal;
        updateUI(code, newVal, maxQty);
        
        audioSuccess.play();
        
        // Tetap arahkan fokus ke sini agar user sadar
        inputQty.focus();
        inputQty.select();
    }

    // LOGIC: Validasi Input Manual
    function manualUpdate(code) {
        let row = document.getElementById(`row-${code}`);
        let maxQty = parseInt(row.getAttribute('data-max')) || 0;
        let inputQty = document.getElementById(`qty-${code}`);
        
        let val = parseInt(inputQty.value);

        if (isNaN(val) || val < 0) val = 0;

        // Cegah melebihi stok/pesanan
        if (val > maxQty) {
            audioLimit.play();
            val = maxQty;
            Swal.fire({
                icon: 'warning',
                title: 'Melebihi Batas',
                text: `Maksimal: ${maxQty}`,
                timer: 1500,
                showConfirmButton: false
            });
        }

        inputQty.value = val;
        itemCounts[code] = val;
        updateUI(code, val, maxQty);
    }

    // Helper: Enter di Input Angka -> Kembali ke Scan Barcode
    function checkInputEnter(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.target.blur(); 
            inputEl.focus(); // Kembali siap scan barang berikutnya
        }
    }

    function updateUI(code, current, max) {
        let row = document.getElementById(`row-${code}`);
        let icon = document.getElementById(`icon-${code}`);
        let target = parseInt(row.getAttribute('data-target'));

        if (current >= target) {
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
        let hasScan = false;
        for (let key in itemCounts) {
            if(itemCounts[key] > 0) hasScan = true;
        }

        if (hasScan) {
            btnSelesai.disabled = false;
            btnSelesai.className = "btn btn-warning w-100 py-3 fw-bold fs-5 shadow-sm";
            btnSelesai.innerHTML = '<i class="fa-solid fa-truck-fast me-2"></i> PROSES KIRIM';
        } else {
            btnSelesai.disabled = true;
            btnSelesai.className = "btn btn-secondary w-100 py-3 fw-bold fs-5 shadow-sm";
            btnSelesai.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i> PROSES KIRIM';
        }
    }

    function showError(title, msg) {
        audioError.play();
        statusEl.className = "p-2 mb-2 rounded text-center fw-bold bg-danger text-white animate__animated animate__shakeX";
        statusEl.innerHTML = title;
        tampilkanPesan('error', `⛔ ${msg}`);
        setTimeout(() => inputEl.focus(), 100);
    }

    function showWarning(title, msg) {
        audioLimit.play();
        statusEl.className = "p-2 mb-2 rounded text-center fw-bold bg-warning text-dark";
        statusEl.innerHTML = title;
        tampilkanPesan('error', `⚠️ ${msg}`);
    }

    function tampilkanPesan(type, html) {
        feedbackEl.innerHTML = html;
        feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        feedbackEl.classList.add('alert', type === 'success' ? 'alert-success' : 'alert-danger');
    }

    function kirimKeAccurate() {
        if (inputEl) inputEl.blur();

        let originalBtnText = btnSelesai.innerHTML;
        btnSelesai.disabled = true;
        btnSelesai.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> MENGIRIM...';

        Swal.fire({
            title: 'Memproses DO...',
            html: 'Menghubungkan ke API Accurate...<br><small>Mohon tunggu sebentar</small>',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() }
        });

        fetch('{{ url("/scan-process/submit") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                so_id: "{{ $so['id'] }}",
                so_number: soNumber,
                items: itemCounts
            })
        })
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Server Error (${res.status}): ${text.substring(0, 200)}...`); 
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'DO Berhasil Dibuat!',
                    html: `
                        <div class="text-start bg-light p-3 rounded small">
                            <strong>No DO:</strong> ${data.do_number}<br>
                            <strong>Status:</strong> Terkirim ke Accurate<br>
                            <br>
                            Surat jalan siap dicetak.
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '📥 Cetak Surat Jalan',
                    cancelButtonText: 'Kembali ke List',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        const urlPDF = `{{ url('/print-do') }}/${data.do_id}`;
                        window.open(urlPDF, '_blank');
                        setTimeout(() => { window.location.href = "{{ url('/scan-so') }}"; }, 2000); 
                    } else {
                        window.location.href = "{{ url('/scan-so') }}";
                    }
                });
            } else {
                Swal.fire('Gagal!', data.message, 'error');
                btnSelesai.disabled = false;
                btnSelesai.innerHTML = originalBtnText;
                if(inputEl) inputEl.focus();
            }
        })
        .catch(err => {
            console.error("DEBUG ERROR:", err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Kirim',
                text: err.message
            });
            btnSelesai.disabled = false;
            btnSelesai.innerHTML = originalBtnText;
            if(inputEl) inputEl.focus();
        });
    }
</script>
@endsection