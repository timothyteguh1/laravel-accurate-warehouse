<!DOCTYPE html>
<html>
<head>
    <title>Buat Sales Order Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">

    <div class="card col-md-6 mx-auto">
        <div class="card-header bg-primary text-white">
            <h4>Input Sales Order (Program Sendiri)</h4>
        </div>
        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ url('/sales-order/store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label>Pilih Barang (Dari Accurate)</label>
                    <select name="item_no" id="itemSelect" class="form-control" required>
                        <option value="">-- Pilih Barang --</option>
                        @foreach($items as $item)
                            <option value="{{ $item['no'] }}" data-price="{{ $item['unitPrice'] ?? 0 }}">
                                {{ $item['no'] }} - {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label>Harga Satuan</label>
                    <input type="number" name="unit_price" id="unitPrice" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label>Kuantitas</label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                </div>

                <button type="submit" class="btn btn-success w-100">Buat Sales Order</button>
            </form>
        </div>
    </div>

    <script>
        // Script kecil supaya pas pilih barang, harganya otomatis muncul
        document.getElementById('itemSelect').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var price = selectedOption.getAttribute('data-price');
            document.getElementById('unitPrice').value = price;
        });
    </script>
</body>
</html>