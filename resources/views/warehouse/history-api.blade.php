@extends('layouts.master')

@section('header', 'Riwayat Selesai (Processed/Closed)')

@section('content')
<div class="container-fluid">
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <i class="fa-solid fa-info-circle me-2"></i>
        Halaman ini menampilkan pesanan yang <b>Sudah Diproses (Ada DO)</b> atau <b>Sudah Lunas (Closed)</b>.
    </div>

    {{-- SEARCH BAR --}}
    <div class="row mb-3">
        <div class="col-md-5">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-search text-muted"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" 
                       placeholder="Cari Nomor SO..." autocomplete="off">
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
                            <th class="px-4 py-3">Tgl. Transaksi</th>
                            <th class="px-4 py-3">Nomor SO</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        @include('warehouse.partials.table-history', ['orders' => $orders])
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                <span class="text-muted small">Halaman {{ $page }}</span>
                <div>
                    @if($page > 1)
                        <a href="{{ url('/history-do?page=' . ($page - 1)) }}" class="btn btn-white border shadow-sm btn-sm me-1">Prev</a>
                    @else
                        <button class="btn btn-white border shadow-sm btn-sm me-1" disabled>Prev</button>
                    @endif
                    <a href="{{ url('/history-do?page=' . ($page + 1)) }}" class="btn btn-white border shadow-sm btn-sm">Next</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let timeout = null;
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const loadingIcon = document.getElementById('loadingIcon');

        searchInput.addEventListener('keyup', function() {
            const query = this.value;
            clearTimeout(timeout);
            loadingIcon.style.display = 'block';
            timeout = setTimeout(function() {
                fetch(`{{ url('/history-do') }}?search=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.text())
                .then(html => {
                    tableBody.innerHTML = html;
                    loadingIcon.style.display = 'none';
                })
                .catch(error => {
                    console.error(error);
                    loadingIcon.style.display = 'none';
                });
            }, 600);
        });
    });
</script>
@endsection