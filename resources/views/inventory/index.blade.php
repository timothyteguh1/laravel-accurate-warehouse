@extends('layouts.master')

@section('header', 'Daftar Inventori')

@section('content')
<div class="container-fluid">
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
                    <tbody>
                        @forelse($items as $item)
                        <tr>
                            <td class="px-4 fw-bold text-primary">{{ $item['no'] }}</td>
                            <td class="px-4">
                                <div class="fw-medium text-dark">{{ $item['name'] }}</div>
                                <small class="text-muted">ID: {{ $item['id'] }}</small>
                            </td>
                            <td class="px-4">
                                @if($item['itemType'] == 'INVENTORY')
                                    <span class="badge bg-info bg-opacity-10 text-info px-2">Barang Persediaan</span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2">Jasa / Non-Persediaan</span>
                                @endif
                            </td>
                            <td class="px-4 text-center">
                                <span class="fw-bold {{ ($item['quantity'] ?? 0) <= 0 ? 'text-danger' : 'text-dark' }}">
                                    {{ number_format($item['quantity'] ?? 0, 0, ',', '.') }}
                                </span>
                                <small class="text-muted">{{ $item['unit1Name'] ?? '' }}</small>
                            </td>
                            <td class="px-4">
                                <div class="fw-bold text-success">
                                    Rp {{ number_format($item['unitPrice'] ?? 0, 0, ',', '.') }}
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">Data tidak ditemukan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                <span class="text-muted small">Halaman {{ $page }}</span>
                <div>
                    @if($page > 1)
                        <a href="{{ url('/inventory?page=' . ($page - 1)) }}" class="btn btn-white border shadow-sm btn-sm me-1">Prev</a>
                    @endif
                    <a href="{{ url('/inventory?page=' . ($page + 1)) }}" class="btn btn-white border shadow-sm btn-sm">Next</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection