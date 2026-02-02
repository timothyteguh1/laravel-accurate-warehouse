@forelse($orders as $order)
@if(is_array($order))
<tr>
    <td class="fw-bold text-primary">
        {{ $order['number'] ?? '-' }}
    </td>
    <td>{{ $order['transDate'] ?? '-' }}</td>
    <td>{{ $order['customer']['name'] ?? '-' }}</td>
    <td class="text-end fw-bold">Rp {{ number_format($order['totalAmount'] ?? 0, 0, ',', '.') }}</td>
    
    <td class="text-center">
        {{-- Karena sistem split, semua yang disini statusnya PASTI Antrian Baru --}}
        <span class="badge bg-warning text-dark border border-warning shadow-sm">
            <i class="fa-regular fa-clock me-1"></i> ANTRIAN BARU
        </span>
    </td>

    <td class="text-end">
        <a href="{{ url('/scan-process/'.($order['id'] ?? 0)) }}" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
            <i class="fa-solid fa-barcode me-1"></i> Mulai Scan
        </a>
    </td>
</tr>
@endif
@empty
<tr>
    <td colspan="6" class="text-center py-5 text-muted">
        <h5 class="fw-bold text-dark">Antrian Kosong</h5>
        <p class="mb-0">Semua pesanan sudah diproses.</p>
    </td>
</tr>
@endforelse