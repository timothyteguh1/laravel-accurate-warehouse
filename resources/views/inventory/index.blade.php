@extends('layouts.master')

@section('header', 'Daftar Inventori')

@section('content')
<div class="container-fluid">
    
    {{-- SEARCH BAR BARU --}}
    <div class="row mb-3">
        <div class="col-md-5">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-search text-muted"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" 
                       placeholder="Cari Kode atau Nama Barang..." autocomplete="off">
                <span class="input-group-text bg-white text-primary" id="loadingIcon" style="display:none;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="card card-custom border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="px-4 py-3">No. Barang</th>
                            <th class="px-4 py-3">Nama Barang / Jasa</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3 text-center">Stok</th>
                            <th class="px-4 py-3">Harga Jual</th>
                        </tr>
                    </thead>
                    
                    {{-- ID UNTUK TARGET AJAX --}}
                    <tbody id="tableBody">
                        @include('inventory.partials.table', ['items' => $items])
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                <span class="text-muted small">Halaman {{ $page }}</span>
                <div>
                    @if($page > 1)
                        <a href="{{ url('/inventory?page=' . ($page - 1)) }}" class="btn btn-white border shadow-sm btn-sm me-1">Prev</a>
                    @else
                        <button class="btn btn-white border shadow-sm btn-sm me-1" disabled>Prev</button>
                    @endif
                    <a href="{{ url('/inventory?page=' . ($page + 1)) }}" class="btn btn-white border shadow-sm btn-sm">Next</a>
                </div>
            </div>
        </div>
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

            // Clear timer sebelumnya
            clearTimeout(timeout);
            loadingIcon.style.display = 'block';

            // Tunggu 600ms (Debounce)
            timeout = setTimeout(function() {
                fetchInventory(query);
            }, 600);
        });

        function fetchInventory(query) {
            const url = `{{ url('/inventory') }}?search=${encodeURIComponent(query)}`;

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
                loadingIcon.style.display = 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                loadingIcon.style.display = 'none';
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
            });
        }
    });
</script>
@endsection