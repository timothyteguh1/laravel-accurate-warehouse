@extends('layouts.master')

@section('header', 'Daftar Sales Order')

@section('content')
<div class="card-custom bg-white p-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">Antrian Pesanan</h5>
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
                    <th class="text-center">Status Gudang</th> 
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td class="fw-bold text-primary">{{ $order['number'] }}</td>
                    <td>{{ $order['transDate'] }}</td>
                    <td>{{ $order['customer']['name'] ?? '-' }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($order['totalAmount'], 0, ',', '.') }}</td>
                    
                    {{-- LOGIKA BARU: FIX STATUS WARNA & DETEKSI DO --}}
                    @php
                        // 1. Ambil Status Asli dari Accurate
                        $statusAccurate = strtoupper($order['status']); 
                        
                        // 2. Cek apakah DO sudah terbentuk di Accurate (Backup Check)
                        // Variabel $processed_numbers dikirim dari Controller baru
                        $isProcessedLocal = in_array($order['number'], $processed_numbers ?? []);

                        // 3. Tentukan Warna & Teks
                        if ($statusAccurate == 'CLOSED') {
                            $statusFinal = 'SELESAI';
                            $badgeClass = 'bg-success text-white';
                            $icon = 'fa-check-circle';
                            $btnDisabled = true;
                        } 
                        // JIKA status 'PROCESSED' ATAU sudah ada DO-nya -> Tampilkan DIPROSES (Biru)
                        elseif ($statusAccurate == 'PROCESSED' || $isProcessedLocal) {
                            $statusFinal = 'DIPROSES'; 
                            $badgeClass = 'bg-primary text-white';
                            $icon = 'fa-truck-loading';
                            $btnDisabled = false; 
                        } 
                        else {
                            $statusFinal = 'ANTRIAN';
                            $badgeClass = 'bg-warning text-dark';
                            $icon = 'fa-clock';
                            $btnDisabled = false;
                        }
                    @endphp

                    <td class="text-center">
                        <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill shadow-sm">
                            <i class="fa-solid {{ $icon }} me-1"></i> {{ $statusFinal }}
                        </span>
                        @if($isProcessedLocal && $statusAccurate == 'QUEUE')
                            <div style="font-size: 10px;" class="text-danger mt-1 fw-bold">(Syncing...)</div>
                        @endif
                    </td>

                    <td class="text-end">
                        @if($statusFinal == 'SELESAI')
                            <button class="btn btn-outline-success btn-sm disabled" style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fa-solid fa-check-double"></i> Tuntas
                            </button>
                            <a href="{{ url('/find-do/'.$order['number']) }}" class="btn btn-sm btn-light border" title="Lihat DO">
                                <i class="fa-solid fa-file-invoice"></i>
                            </a>
                        @else
                            {{-- Jika DIPROSES atau ANTRIAN, tombol scan tetap nyala --}}
                            <a href="{{ url('/scan-process/'.$order['id']) }}" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">
                                <i class="fa-solid fa-barcode me-2"></i> 
                                {{ $statusFinal == 'DIPROSES' ? 'Lanjut Scan' : 'Mulai Scan' }}
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