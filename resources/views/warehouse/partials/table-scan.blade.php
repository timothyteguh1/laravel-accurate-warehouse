@forelse($orders as $order)
<tr>
    <td class="fw-bold text-primary">
        {{ $order['number'] }}
    </td>
    <td>{{ $order['transDate'] }}</td>
    <td>{{ $order['customer']['name'] ?? '-' }}</td>
    <td class="text-end fw-bold">Rp {{ number_format($order['totalAmount'], 0, ',', '.') }}</td>
    
    <td class="text-center">
        @if(strtoupper($order['status']) == 'CLOSED')
            <span class="badge bg-success"><i class="fa-check-circle me-1"></i> SELESAI</span>
        @elseif(strtoupper($order['status']) == 'PROCESSED')
            <span class="badge bg-primary"><i class="fa-truck-loading me-1"></i> DIPROSES</span>
        @else
            <span class="badge bg-warning text-dark"><i class="fa-clock me-1"></i> ANTRIAN</span>
        @endif
    </td>

    <td class="text-end">
        @if(strtoupper($order['status']) == 'CLOSED')
            <button class="btn btn-sm btn-secondary disabled" title="Sudah Selesai">
                <i class="fa-solid fa-check"></i> Selesai
            </button>
        @else
            <a href="{{ url('/scan-process/'.$order['id']) }}" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-barcode me-1"></i> Mulai Scan
            </a>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center py-5 text-muted">
        <i class="fa-solid fa-box-open fs-1 mb-3 d-block opacity-25"></i>
        <p>Tidak ada Sales Order yang ditemukan.</p>
    </td>
</tr>
@endforelse