@extends('layouts.master')

@section('header', 'Dashboard Gudang')

@section('content')
<div class="container-fluid">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card card-custom border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                            <i class="fa-solid fa-file-invoice fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">SO Belum Diproses</h6>
                            <h3 class="fw-bold mb-0">{{ $stats['pending_so'] }}</h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('/scan-so') }}" class="text-primary text-decoration-none small fw-bold">
                            Mulai Scan Sekarang <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-3 me-3">
                            <i class="fa-solid fa-truck-fast fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pengiriman Hari Ini</h6>
                            <h3 class="fw-bold mb-0">{{ $stats['today_do'] }}</h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('/history-do') }}" class="text-success text-decoration-none small fw-bold">
                            Lihat Riwayat SO <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 me-3">
                            <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Master Barang</h6>
                            <h3 class="fw-bold mb-0">{{ $stats['total_items'] }}</h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('/inventory') }}" class="text-info text-decoration-none small fw-bold">
                            Cek Inventori <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-custom border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Tren Pengiriman (7 Hari Terakhir)</h5>
                    <div style="height: 320px;">
                        <canvas id="deliveryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-custom border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="fw-bold mb-3">Status Integrasi</h5>
                    <div class="grow">
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">API Accurate</span>
                            <span class="badge bg-success rounded-pill">Terkoneksi</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">Host</span>
                            <span class="text-dark small">accurate.id</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">Waktu Server</span>
                            <span class="text-dark">{{ date('H:i') }} WIB</span>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 rounded-3 mt-3">
                        <p class="small text-muted mb-0">
                            <i class="fa-solid fa-circle-info me-1"></i> 
                            Data ditarik secara real-time dari Accurate Online API.
                        </p>
                    </div>
                    
                    <button onclick="window.location.reload()" class="btn btn-outline-primary w-100 mt-3">
                        <i class="fa-solid fa-sync me-2"></i> Refresh Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        
        // Data dari PHP
        const labels = {!! json_encode($chartLabels) !!};
        const dataValues = {!! json_encode($chartData) !!};

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Pengiriman (DO)',
                    data: dataValues,
                    backgroundColor: '#2563eb', // Blue Primary
                    borderRadius: 6,
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    });
</script>
@endsection