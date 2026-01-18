<!DOCTYPE html>
<html>
<head>
    <title>Accurate Jastip Sales Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container">
        <h2 class="mb-4">📦 Kelola Sales Order (Jastip)</h2>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">Buat Order Baru</div>
                    <div class="card-body">
                        <form action="{{ url('/accurate/so/create') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label>No Pelanggan (Customer No)</label>
                                <input type="text" name="customerNo" class="form-control" placeholder="Contoh: C.00001" required>
                                <small class="text-muted">Cek di Accurate Online -> Penjualan -> Pelanggan</small>
                            </div>
                            <div class="mb-3">
                                <label>No Barang (Item No)</label>
                                <input type="text" name="itemNo" class="form-control" placeholder="Contoh: ITEM-001" required>
                                <small class="text-muted">Cek di Persediaan -> Barang & Jasa</small>
                            </div>
                            <div class="mb-3">
                                <label>Harga (Unit Price)</label>
                                <input type="number" name="price" class="form-control" value="10000">
                            </div>
                            <div class="mb-3">
                                <label>Jumlah (Qty)</label>
                                <input type="number" name="qty" class="form-control" value="1">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Simpan ke Accurate</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">List Sales Order (Dari Database Accurate)</div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No SO</th>
                                    <th>Pelanggan</th>
                                    <th>Tanggal</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $so)
                                <tr>
                                    <td>{{ $so['number'] }}</td>
                                    <td>{{ $so['customer']['name'] ?? '-' }}</td>
                                    <td>{{ $so['transDate'] }}</td>
                                    <td>Rp {{ number_format($so['totalAmount'], 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">Belum ada data Sales Order.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <a href="{{ url('/accurate/so') }}" class="btn btn-secondary btn-sm mt-3">Refresh Data</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>