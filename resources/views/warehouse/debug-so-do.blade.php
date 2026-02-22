@extends('layouts.master')

@section('header', 'Cek Relasi SO & DO')

@section('content')
<div class="container mt-4">
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Data Sales Order (SO)</h5>
        </div>
        <div class="card-body">
            <p><strong>Nomor SO:</strong> {{ $so['number'] }}</p>
            <p><strong>Tanggal:</strong> {{ $so['transDate'] }}</p>
            <p><strong>Pelanggan:</strong> {{ $so['customer']['name'] ?? '-' }}</p>
            <p><strong>Status SO:</strong> <span class="badge bg-warning text-dark">{{ $so['status'] }}</span></p>
        </div>
    </div>

    <div class="card border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Delivery Order (DO) Terhubung: {{ count($dos) }} Ditemukan</h5>
        </div>
        <div class="card-body p-0">
            @if(count($dos) > 0)
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nomor DO</th>
                        <th>Tanggal DO</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Detail Barang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dos as $do)
                    <tr>
                        <td class="fw-bold">{{ $do['number'] }}</td>
                        <td>{{ $do['transDate'] }}</td>
                        <td><span class="badge bg-secondary">{{ $do['status'] }}</span></td>
                        <td>{{ $do['description'] ?? '(Kosong)' }}</td>
                        <td>
                            @if(!empty($do['detailItem']))
                                <ul class="mb-0">
                                @foreach($do['detailItem'] as $item)
                                    <li>{{ $item['item']['name'] ?? 'Barang' }} (Qty: {{ $item['quantity'] ?? 0 }})</li>
                                @endforeach
                                </ul>
                            @else
                                <span class="text-muted">Gagal memuat item dari list API</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="p-4 text-center text-muted">
                Tidak ada DO yang terhubung secara sistem dengan SO ini.
            </div>
            @endif
        </div>
    </div>
    
    <div class="mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Kembali</a>
    </div>
</div>
@endsection