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
                       placeholder="Scan Barang..." autofocus autocomplete="off" 
                       onblur="this.focus()">
            </div>
            
            <div id="pesanFeedback" class="mt-2 fw-bold small p-2 rounded d-none"></div>

            <div class="alert alert-warning mt-3 mb-0 small border-start border-4 border-warning fst-italic">
                <i class="fa-solid fa-lock"></i> <strong>Proteksi Stok:</strong><br>
                Sistem akan memblokir scan jika melebihi stok gudang.
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
                        <th class="text-center">Scan</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($so['detailItem'] as $item)
                    <tr id="row-{{ $item['item']['no'] }}" class="barang-row"
                        data-code="{{ $item['item']['no'] }}" 
                        data-name="{{ $item['item']['name'] }}"
                        data-target="{{ $item['quantity'] }}"
                        data-stock="{{ $item['stok_gudang'] ?? 0 }}"> 
                        
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $item['item']['name'] }}</div>
                            <div class="badge bg-light text-dark border mt-1">
                                {{ $item['item']['no'] }}
                            </div>
                        </td>
                        
                        <td class="text-center fw-bold fs-5 text-secondary">
                            {{ number_format($item['quantity'], 0) }}
                        </td>

                        <td class="text-center fw-bold fs-5 bg-warning bg-opacity-10 border-start border-end text-dark">
                            {{ $item['stok_gudang'] ?? 0 }}
                        </td>

                        <td class="text-center fw-bold fs-5 text-primary">
                            <span id="qty-{{ $item['item']['no'] }}">0</span>
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
    const customerNo = "{{ $so['customer']['customerNo'] ?? $so['customer']['no'] ?? 'P.00001' }}";

    @foreach($so['detailItem'] as $item)
        itemCounts['{{ $item['item']['no'] }}'] = 0;
    @endforeach

    // Audio
    const audioSuccess = new Audio('https://actions.google.com/sounds/v1/cartoon/pop.ogg');
    const audioError   = new Audio('https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg');
    const audioLimit   = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');

    const inputEl = document.getElementById('barcodeInput');
    const statusEl = document.getElementById('statusHeader');
    const feedbackEl = document.getElementById('pesanFeedback');
    const btnSelesai = document.getElementById('btnSelesai');

    inputEl.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let code = this.value.trim();
            this.value = '';
            if (code === "") return;
            prosesScan(code);
        }
    });

    function prosesScan(code) {
        let row = document.getElementById(`row-${code}`);

        if (!row) {
            showError('SALAH BARANG!', `Kode <b>${code}</b> tidak ada di SO ini.`);
            return;
        }

        let name = row.getAttribute('data-name');
        let target = parseInt(row.getAttribute('data-target')) || 0;
        let stockReal = parseInt(row.getAttribute('data-stock')); 
        
        if (isNaN(stockReal)) stockReal = 0;

        let current = itemCounts[code];

        if ((current + 1) > stockReal) {
            showError('STOK HABIS!', `Gudang cuma punya <b>${stockReal}</b> pcs.<br>Tidak bisa ambil barang lagi.`);
            return;
        }

        if (current >= target) {
            showWarning('SUDAH LENGKAP', `Barang <b>${name}</b> sudah pas ${target} pcs sesuai order.`);
            return;
        }

        itemCounts[code]++;
        updateUI(code, target, stockReal);
        
        audioSuccess.play();
        statusEl.className = "p-2 mb-2 rounded text-center fw-bold bg-success text-white";
        statusEl.innerHTML = `SUKSES: ${name}`;
        tampilkanPesan('success', `✅ <b>${name}</b> masuk (+1)`);
    }

    function updateUI(code, target, stockReal) {
        let current = itemCounts[code];
        document.getElementById(`qty-${code}`).innerText = current;

        let row = document.getElementById(`row-${code}`);
        let icon = document.getElementById(`icon-${code}`);

        if (current === target) {
            row.classList.add('table-success'); 
            icon.className = 'fa-solid fa-circle-check text-success fs-4';
        } else if (current === stockReal) {
            row.classList.add('table-warning'); 
            icon.className = 'fa-solid fa-triangle-exclamation text-warning fs-4';
        } else {
            row.classList.remove('table-success', 'table-warning');
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
        }
    }

    function showError(title, msg) {
        audioError.play();
        statusEl.className = "p-2 mb-2 rounded text-center fw-bold bg-danger text-white animate__animated animate__shakeX";
        statusEl.innerHTML = title;
        tampilkanPesan('error', `⛔ ${msg}`);
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
        Swal.fire({
            title: 'Memproses DO...',
            html: 'Mengirim data ke Accurate...',
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
                customer_no: customerNo,
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
                
                // --- POPUP SUKSES ---
                Swal.fire({
                    icon: 'success',
                    title: 'DO Berhasil Dibuat!',
                    html: `
                        <div class="text-start bg-light p-3 rounded small">
                            <strong>No DO:</strong> ${data.do_number}<br>
                            <strong>Status:</strong> Stok Berhasil Terpotong<br>
                            <br>
                            Silakan download surat jalan sekarang.
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '📥 Download PDF Surat Jalan',
                    cancelButtonText: 'Kembali ke List SO',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // DOWNLOAD PDF (Buka di Tab Baru)
                        const urlPDF = `{{ url('/print-do') }}/${data.do_id}`;
                        window.open(urlPDF, '_blank');
                        
                        // Redirect ke halaman list agar status terupdate
                        setTimeout(() => {
                            window.location.href = "{{ url('/scan-so') }}";
                        }, 2000); 
                    } else {
                        // User klik Kembali
                        window.location.href = "{{ url('/scan-so') }}";
                    }
                });

            } else {
                Swal.fire('Gagal!', data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Kirim',
                text: err.message,
                footer: 'Cek koneksi internet atau login ulang Accurate.'
            });
        });
    }

    window.onload = function() { inputEl.focus(); }
</script>
@endsection