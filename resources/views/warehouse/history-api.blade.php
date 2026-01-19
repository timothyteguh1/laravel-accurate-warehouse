@extends('layouts.master')

@section('header', 'Riwayat SO Selesai (Closed)')

@section('content')
<div class="container-fluid">
    <div class="alert alert-success border-0 shadow-sm mb-4">
        <i class="fa-solid fa-check-circle me-2"></i>
        Menampilkan Sales Order yang statusnya <b>CLOSED</b> (Selesai). Jika SO dibuka kembali di Accurate, data akan otomatis hilang dari sini.
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
                            <th class="px-4 py-3">Nilai Total</th>
                            <th class="px-4 py-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $so)
                        <tr>
                            <td class="px-4">{{ $so['transDate'] }}</td>
                            
                            <td class="px-4">
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
                                    {{ $so['number'] }}
                                </span>
                            </td>

                            <td class="px-4 fw-medium text-dark">{{ $so['customer']['name'] ?? 'Umum' }}</td>
                            
                            <td class="px-4 text-muted">
                                Rp {{ number_format($so['totalAmount'] ?? 0, 0, ',', '.') }}
                            </td>
                            
                            <td class="px-4 text-end">
                                <a href="{{ url('/find-do-print/' . $so['number']) }}" target="_blank" class="btn btn-sm btn-outline-dark border-2 fw-medium">
                                    <i class="fa-solid fa-print me-1"></i> Lihat Surat Jalan
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-check fa-2x mb-3 d-block opacity-25"></i>
                                Tidak ada SO dengan status CLOSED.
                            </td>
                        </tr>
                        @endforelse
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
@endsection