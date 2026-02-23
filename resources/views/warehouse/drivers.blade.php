@extends('layouts.master')

@section('header', 'Monitor Armada & Sopir')

@section('content')
<div class="container-fluid">
    <div class="alert alert-primary border-0 shadow-sm mb-4">
        <i class="fa-solid fa-truck-fast me-2"></i>
        Pantau pergerakan armada dan Surat Jalan (DO) yang sedang dibawa oleh masing-masing sopir.
        *(Status sementara diubah manual via Database)*
    </div>

    <div class="row">
        @forelse($drivers as $driver)
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100 border-0 shadow-sm">
                
                {{-- Header Card Sopir --}}
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-user-tie text-primary me-2"></i>{{ $driver->name }}
                            </h5>
                            <span class="badge bg-dark mt-2 fs-6">
                                <i class="fa-solid fa-car-rear me-1"></i> {{ $driver->license_plate }}
                            </span>
                        </div>
                        <div class="text-end">
                            @php
                                // Hitung berapa DO yang sedang 'Di Perjalanan'
                                $activeDO = $driver->deliveries->where('status', 'Di Perjalanan')->count();
                            @endphp
                            @if($activeDO > 0)
                                <div class="spinner-grow text-warning spinner-grow-sm" role="status"></div>
                                <span class="fw-bold text-warning ms-1">{{ $activeDO }} Jalan</span>
                            @else
                                <span class="badge bg-success rounded-pill"><i class="fa-solid fa-mug-hot me-1"></i> Standby</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- List DO yang Dibawa --}}
                <div class="card-body">
                    <hr class="text-muted opacity-25">
                    <h6 class="text-muted small fw-bold mb-3">RIWAYAT PENGIRIMAN:</h6>
                    
                    @if($driver->deliveries->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($driver->deliveries as $delivery)
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start border-0">
                                    <div>
                                        <div class="fw-bold text-primary">{{ $delivery->accurate_do_number }}</div>
                                        <small class="text-muted">{{ $delivery->created_at->format('d M Y, H:i') }}</small>
                                    </div>
                                    
                                    @if($delivery->status == 'Selesai')
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success mt-1">
                                            <i class="fa-solid fa-check-double me-1"></i> Selesai
                                        </span>
                                    @elseif($delivery->status == 'Di Perjalanan')
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning mt-1">
                                            <i class="fa-solid fa-route me-1"></i> Jalan
                                        </span>
                                    @else
                                        <span class="badge bg-secondary mt-1">{{ $delivery->status }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fa-solid fa-box-open fs-3 mb-2 opacity-50"></i><br>
                            <small>Belum ada tugas pengiriman.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <h5 class="text-muted">Data Sopir belum tersedia.</h5>
        </div>
        @endforelse
    </div>
</div>
@endsection