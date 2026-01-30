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

    {{-- SEARCH BAR BARU --}}
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
            {{-- ID UNTUK TARGET AJAX --}}
            <tbody id="tableBody">
                @include('warehouse.partials.table-scan', ['orders' => $orders])
            </tbody>
        </table>
    </div>
</div>

{{-- SCRIPT AJAX DEBOUNCE --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let timeout = null;
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const loadingIcon = document.getElementById('loadingIcon');

        searchInput.addEventListener('keyup', function() {
            const query = this.value;

            // Clear timer sebelumnya (Debounce logic)
            clearTimeout(timeout);

            // Tampilkan loading
            loadingIcon.style.display = 'block';

            // Set timer baru (tunggu 600ms setelah user selesai mengetik)
            timeout = setTimeout(function() {
                fetchOrders(query);
            }, 600);
        });

        function fetchOrders(query) {
            // URL saat ini + parameter search
            const url = `{{ url('/scan-so') }}?search=${encodeURIComponent(query)}`;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Menandai ini request AJAX
                }
            })
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html; // Ganti isi tbody
                loadingIcon.style.display = 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                loadingIcon.style.display = 'none';
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Gagal memuat data. Cek koneksi internet/Accurate.</td></tr>';
            });
        }
    });
</script>
@endsection