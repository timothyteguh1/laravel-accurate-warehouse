@forelse($orders as $so)
<tr>
    <td class="px-4">{{ $so['transDate'] }}</td>
    
    <td class="px-4">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
            {{ $so['number'] }}
        </span>
    </td>

    <td class="px-4 fw-medium text-dark">{{ $so['customer']['name'] ?? 'Umum' }}</td>
    
    <td class="px-4 text-muted">
        Rp {{ number_format($so['totalAmount'] ?? 0, 0, ',', '.') }}
    </td>
    
    <td class="px-4 text-end">
        <a href="{{ url('/find-do-print/' . $so['number']) }}" target="_blank" class="btn btn-sm btn-outline-dark border-2 fw-medium">
            <i class="fa-solid fa-print me-1"></i> Lihat Surat Jalan
        </a>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center py-5 text-muted">
        <i class="fa-solid fa-clipboard-check fa-2x mb-3 d-block opacity-25"></i>
        <p>Data tidak ditemukan.</p>
    </td>
</tr>
@endforelse