<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $do['number'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items-table th { background-color: #f0f0f0; }
        .footer { display: flex; justify-content: space-between; text-align: center; margin-top: 50px; }
        .sign-box { width: 30%; }
        .sign-line { margin-top: 60px; border-top: 1px solid #000; }
        @media print { .no-print { display: none; } }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 10px 20px; cursor: pointer; font-size: 16px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <button onclick="window.print()" class="no-print btn-print">🖨️ Cetak Surat Jalan</button>

    <div class="header">
        <h2 style="margin:0;">SURAT JALAN</h2>
        <h4 style="margin:5px 0;">PT. DEUS CODE WAREHOUSE</h4>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Nomor DO</strong></td>
            <td width="35%">: {{ $do['number'] }}</td>
            <td width="15%"><strong>Tanggal</strong></td>
            <td width="35%">: {{ $do['transDate'] }}</td>
        </tr>
        <tr>
            <td><strong>Kepada</strong></td>
            <td>: {{ $do['customer']['name'] ?? 'Pelanggan Umum' }}</td>
            <td><strong>No SO</strong></td>
            <td>: 
                @php
                    $noSO = '-';
                    // Cek Header
                    if (!empty($do['salesOrderDocNo'])) {
                        $noSO = $do['salesOrderDocNo'];
                    } 
                    // Cek Detail Item (Objek)
                    elseif (isset($do['detailItem'][0]['salesOrder']['number'])) {
                        $noSO = $do['detailItem'][0]['salesOrder']['number'];
                    }
                    // Cek Detail Item (String)
                    elseif (isset($do['detailItem'][0]['salesOrderDocNo'])) {
                        $noSO = $do['detailItem'][0]['salesOrderDocNo'];
                    }
                @endphp
                <span style="font-weight: bold;">{{ $noSO }}</span>
            </td>
        </tr>
        <tr>
            <td><strong>Alamat</strong></td>
            <td colspan="3">: {{ $do['toAddress'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 10%; text-align: center;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($do['detailItem'] as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item['item']['no'] }}</td>
                <td>{{ $item['item']['name'] }}</td>
                <td style="text-align: center;">{{ number_format($item['quantity'], 0) }}</td>
                <td style="text-align: center;">{{ $item['itemUnit']['name'] ?? 'Pcs' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="sign-box">
            <p>Penerima</p>
            <div class="sign-line"></div>
        </div>
        <div class="sign-box">
            <p>Pengirim / Gudang</p>
            <div class="sign-line"></div>
        </div>
        <div class="sign-box">
            <p>Hormat Kami</p>
            <div class="sign-line"></div>
        </div>
    </div>

    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>