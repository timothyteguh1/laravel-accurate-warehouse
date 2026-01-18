@extends('layouts.master')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div class="bg-primary bg-opacity-10 p-2 rounded text-primary">
                    <i class="fa-solid fa-box fa-lg"></i>
                </div>
                <span class="text-success small fw-bold">+12%</span>
            </div>
            <div class="stat-value">24</div>
            <div class="stat-label">Total SO Hari Ini</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div class="bg-success bg-opacity-10 p-2 rounded text-success">
                    <i class="fa-solid fa-check-circle fa-lg"></i>
                </div>
                <span class="text-success small fw-bold">+8%</span>
            </div>
            <div class="stat-value">18</div>
            <div class="stat-label">SO Selesai</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div class="bg-warning bg-opacity-10 p-2 rounded text-warning">
                    <i class="fa-solid fa-clock fa-lg"></i>
                </div>
                <span class="text-danger small fw-bold">-3%</span>
            </div>
            <div class="stat-value">6</div>
            <div class="stat-label">SO Pending</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div class="bg-info bg-opacity-10 p-2 rounded text-info">
                    <i class="fa-solid fa-chart-line fa-lg"></i>
                </div>
                <span class="text-success small fw-bold">+18%</span>
            </div>
            <div class="stat-value">342</div>
            <div class="stat-label">Total Item Scan</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="card-title fw-bold mb-3">Aktivitas Terbaru</h5>
        
        <div class="list-group list-group-flush">
            <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-light p-2 rounded">
                        <i class="fa-solid fa-box-open text-muted"></i>
                    </div>
                    <div>
                        <div class="fw-bold">SO.2017.02.00001</div>
                        <div class="text-muted small">Abadi Phone Center</div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success bg-opacity-10 text-success mb-1">Selesai</span>
                    <div class="text-muted small">10 Feb 2017</div>
                </div>
            </div>
            
             <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-light p-2 rounded">
                        <i class="fa-solid fa-box-open text-muted"></i>
                    </div>
                    <div>
                        <div class="fw-bold">SO.2017.02.00002</div>
                        <div class="text-muted small">Pelanggan Umum - Jakarta</div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success bg-opacity-10 text-success mb-1">Selesai</span>
                    <div class="text-muted small">10 Feb 2017</div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection