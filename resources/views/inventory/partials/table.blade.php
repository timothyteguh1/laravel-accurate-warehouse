@forelse($items as $item)
<tr>
    <td class="px-4 fw-bold text-primary">{{ $item['no'] }}</td>
    <td class="px-4">
        <div class="fw-medium text-dark">{{ $item['name'] }}</div>
        <small class="text-muted">ID: {{ $item['id'] }}</small>
    </td>
    <td class="px-4">
        @if($item['itemType'] == 'INVENTORY')
            <span class="badge bg-info bg-opacity-10 text-info px-2">Barang Persediaan</span>
        @else
            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2">Jasa / Non-Persediaan</span>
        @endif
    </td>
    <td class="px-4 text-center">
        <span class="fw-bold {{ ($item['quantity'] ?? 0) <= 0 ? 'text-danger' : 'text-dark' }}">
            {{ number_format($item['quantity'] ?? 0, 0, ',', '.') }}
        </span>
        <small class="text-muted">{{ $item['unit1Name'] ?? '' }}</small>
    </td>
    <td class="px-4">
        <div class="fw-bold text-success">
            Rp {{ number_format($item['unitPrice'] ?? 0, 0, ',', '.') }}
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center py-5 text-muted">
        <i class="fa-solid fa-box-open fs-1 mb-3 d-block opacity-25"></i>
        <p>Barang tidak ditemukan.</p>
    </td>
</tr>
@endforelse