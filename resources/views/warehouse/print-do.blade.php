<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $do['number'] ?? 'DRAFT' }}</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 14px; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #000; padding-bottom: 15px; }
        .header h2 { margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .header h4 { margin: 5px 0 0; font-weight: normal; }
        
        .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .info-table td { padding: 5px; vertical-align: top; }
        .label { font-weight: bold; width: 130px; display: inline-block; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 10px; }
        .items-table th { background-color: #f2f2f2; text-transform: uppercase; font-size: 12px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .footer { display: flex; justify-content: space-between; margin-top: 50px; padding: 0 50px; }
        .sign-box { text-align: center; width: 200px; }
        .sign-line { margin-top: 80px; border-top: 1px solid #000; }
        
        @media print { 
            .no-print { display: none; } 
            body { padding: 0; }
        }
        .btn-print { background: #0d6efd; color: white; border: none; padding: 12px 25px; cursor: pointer; font-size: 16px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .btn-print:hover { background: #0b5ed7; }
    </style>
</head>
<body>

    <button onclick="window.print()" class="no-print btn-print">🖨️ CETAK SURAT JALAN</button>

    <div class="header">
        <h2>Surat Jalan</h2>
        <h4>PT. DEUS CODE WAREHOUSE</h4>
    </div>

    @php
        // --- LOGIKA PENCARIAN NOMOR SO (SUPER ROBUST) ---
        $noSO = '-';
        
        // 1. Cek Header Resmi Accurate
        if (!empty($do['salesOrderDocNo'])) {
            $noSO = $do['salesOrderDocNo'];
        } 
        // 2. Cek Detail Item (Baris Pertama)
        elseif (!empty($do['detailItem']) && is_array($do['detailItem'])) {
            foreach($do['detailItem'] as $dItem) {
                if (isset($dItem['salesOrder']['number'])) {
                    $noSO = $dItem['salesOrder']['number'];
                    break;
                }
                if (isset($dItem['salesOrderDocNo'])) {
                    $noSO = $dItem['salesOrderDocNo'];
                    break;
                }
            }
        }
        
        // 3. (JURUS TERAKHIR) Cek Description / Keterangan
        // Karena di Controller kita tulis: 'DO Otomatis... Ref SO SO-2024...'
        if ($noSO == '-' && !empty($do['description'])) {
            // Cari kata setelah "Ref SO" atau "SO"
            if (preg_match('/(Ref SO|SO)\s*[:\#]?\s*([A-Za-z0-9\.\-]+)/i', $do['description'], $matches)) {
                $noSO = $matches[2]; // Ambil hasil tangkapan regex
            }
        }
    @endphp

    <table class="info-table">
        <tr>
            <td width="50%">
                <span class="label">Nomor DO</span> : <strong>{{ $do['number'] ?? 'DRAFT / BELUM ADA' }}</strong><br>
                <span class="label">Tanggal Kirim</span> : {{ $do['transDate'] ?? date('d/m/Y') }}<br>
                <span class="label">No. Polisi/Kurir</span> : {{ $do['shipmentName'] ?? '-' }}
            </td>
            <td width="50%">
                <span class="label">Kepada Yth.</span> :<br>
                <strong>{{ $do['customer']['name'] ?? 'Pelanggan Umum' }}</strong><br>
                <div style="margin-left: 135px; margin-top: -18px;">
                    {{ $do['toAddress'] ?? '-' }}
                </div>
                <br>
                <span class="label">Referensi SO</span> : <strong>{{ $noSO }}</strong>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">Kode Barang</th>
                <th width="45%">Nama Barang</th>
                <th width="15%" class="text-center">Qty</th>
                <th width="15%" class="text-center">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($do['detailItem']) && is_array($do['detailItem']))
                @foreach($do['detailItem'] as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item['item']['no'] ?? '-' }}</td>
                    <td>
                        {{ $item['item']['name'] ?? 'Item Tanpa Nama' }}
                        @if(!empty($item['detailNotes']))
                            <br><small><i>Catatan: {{ $item['detailNotes'] }}</i></small>
                        @endif
                    </td>
                    <td class="text-center" style="font-size: 1.1em; font-weight: bold;">
                        {{ number_format($item['quantity'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @php
                            // LOGIKA PENGECEKAN SATUAN (ANTI-ERROR)
                            $unitName = 'Pcs';
                            if (isset($item['itemUnit'])) {
                                if (is_array($item['itemUnit'])) {
                                    $unitName = $item['itemUnit']['name'] ?? 'Pcs';
                                } elseif (is_string($item['itemUnit'])) {
                                    $unitName = $item['itemUnit'];
                                }
                            }
                        @endphp
                        {{ $unitName }}
                    </td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="text-center">-- Tidak ada data barang --</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-bottom: 20px;">
        <strong>Keterangan:</strong><br>
        {{ $do['description'] ?? '-' }}
    </div>

    <div class="footer">
        <div class="sign-box">
            <p>Penerima</p>
            <div class="sign-line"></div>
            <small>(Tanda Tangan & Stempel)</small>
        </div>
        <div class="sign-box">
            <p>Hormat Kami,</p>
            <div class="sign-line"></div>
            <small>Bagian Gudang</small>
        </div>
    </div>

    <script>
        // Auto Print setelah 1 detik agar browser sempat render CSS
        window.onload = function() { 
            setTimeout(function() { window.print(); }, 1000); 
        };
    </script>
</body>
</html>