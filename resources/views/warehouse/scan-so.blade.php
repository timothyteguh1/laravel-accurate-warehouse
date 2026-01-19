@extends('layouts.master')

@section('header', 'Daftar Sales Order')

@section('content')
<div class="card-custom bg-white p-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold mb-0">Antrian Pesanan (All Source)</h5>
            <small class="text-muted">Mendukung SO dari Accurate Web & Aplikasi</small>
        </div>
        <button class="btn btn-primary btn-sm" onclick="location.reload()">
            <i class="fa-solid fa-sync"></i> Refresh Data
        </button>
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
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td class="fw-bold text-primary">
                        {{ $order['number'] }}
                    </td>
                    <td>{{ $order['transDate'] }}</td>
                    <td>{{ $order['customer']['name'] ?? '-' }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($order['totalAmount'], 0, ',', '.') }}</td>
                    
                    <td class="text-center">
                        @if(strtoupper($order['status']) == 'CLOSED')
                            <span class="badge bg-success"><i class="fa-check-circle me-1"></i> SELESAI</span>
                        @elseif(strtoupper($order['status']) == 'PROCESSED')
                            <span class="badge bg-primary"><i class="fa-truck-loading me-1"></i> DIPROSES</span>
                        @else
                            <span class="badge bg-warning text-dark"><i class="fa-clock me-1"></i> ANTRIAN</span>
                        @endif
                    </td>

                    <td class="text-end">
                        @if(strtoupper($order['status']) == 'CLOSED')
                            <button class="btn btn-sm btn-secondary disabled" title="Sudah Selesai">
                                <i class="fa-solid fa-check"></i> Selesai
                            </button>
                        @else
                            {{-- SEMUA SO BISA DISCAN --}}
                            <a href="{{ url('/scan-process/'.$order['id']) }}" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
                                <i class="fa-solid fa-barcode me-1"></i> Mulai Scan
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-box-open fs-1 mb-3 d-block opacity-25"></i>
                        Tidak ada Sales Order aktif saat ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection