@extends('layouts.master')

@section('header', 'Daftar Sales Order')

@section('content')
<div class="card-custom bg-white p-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold mb-0">Antrian Pesanan</h5>
            <small class="text-muted">Cari berdasarkan Nomor SO</small>
        </div>
        <button class="btn btn-primary btn-sm" onclick="location.reload()">
            <i class="fa-solid fa-sync"></i> Refresh
        </button>
    </div>

    {{-- SEARCH BAR --}}
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-search text-muted"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0"
                       placeholder="Ketik Nomor SO (Cth: SO.2024...)" autocomplete="off">
                <span class="input-group-text bg-white text-primary" id="loadingIcon" style="display:none;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                </span>
            </div>
        </div>
    </div>

    {{-- TABEL QUEUE --}}
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="bg-light text-secondary small">
                <tr>
                    <th>Nomor SO</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                @include('warehouse.partials.table-scan', ['orders' => $orders])
            </tbody>
        </table>
    </div>
</div>

{{-- ================================================================ --}}
{{-- SECTION WAITING — SO yang sudah pernah dikirim sebagian          --}}
{{-- ================================================================ --}}
@if(isset($waitingOrders) && count($waitingOrders) > 0)
<div class="card-custom bg-white p-4 shadow-sm mt-4">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> SEBAGIAN TERKIRIM
        </span>
        <span class="text-muted small">{{ count($waitingOrders) }} SO menunggu pengiriman sisa</span>
    </div>

    <div class="alert alert-warning border-start border-4 border-warning py-2 small mb-3">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        SO berikut sudah <strong>pernah dikirim sebagian</strong>.
        Klik <strong>"Mulai Scan Sisa"</strong> untuk melanjutkan pengiriman sisa barang.
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-secondary small">
                <tr>
                    <th>Nomor SO</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($waitingOrders as $order)
                <tr>
                    <td>
                        <span class="fw-bold text-warning">{{ $order['number'] ?? '-' }}</span>
                    </td>
                    <td class="text-muted small">
                        {{ $order['transDate'] ?? '-' }}
                    </td>
                    <td>
                        {{ $order['customer']['name'] ?? ($order['customerName'] ?? '-') }}
                    </td>
                    <td class="text-end fw-bold">
                        Rp {{ number_format($order['totalAmount'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-warning text-dark">
                            <i class="fa-solid fa-circle-half-stroke me-1"></i> SEBAGIAN
                        </span>
                    </td>
                    <td class="text-end">
                        {{-- Langsung ke scan-process — controller akan detect WAITING & hitung sisa --}}
                        <a href="{{ url('/scan-process/' . $order['id']) }}"
                           class="btn btn-warning btn-sm fw-bold">
                            <i class="fa-solid fa-barcode me-1"></i> Mulai Scan Sisa
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">Tidak ada SO WAITING</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- SCRIPT AJAX SEARCH (hanya untuk QUEUE) --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let timeout = null;
        const searchInput = document.getElementById('searchInput');
        const tableBody   = document.getElementById('tableBody');
        const loadingIcon = document.getElementById('loadingIcon');

        searchInput.addEventListener('keyup', function () {
            const query = this.value;
            clearTimeout(timeout);
            loadingIcon.style.display = 'block';

            timeout = setTimeout(function () {
                fetch(`{{ url('/scan-so') }}?search=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.text())
                .then(html => {
                    tableBody.innerHTML       = html;
                    loadingIcon.style.display = 'none';
                })
                .catch(err => {
                    console.error('Error:', err);
                    loadingIcon.style.display = 'none';
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>';
                });
            }, 600);
        });
    });
</script>
@endsection