@forelse($orders as $so)
@if(is_array($so))
<tr>
    <td class="px-4">{{ $so['transDate'] ?? '-' }}</td>
    <td class="px-4"><span class="badge bg-secondary">{{ $so['number'] ?? '-' }}</span></td>
    <td class="px-4">{{ $so['customer']['name'] ?? 'Umum' }}</td>
    
    <td class="px-4">
        @php $st = strtoupper($so['status'] ?? ''); @endphp
        
        @if($st == 'CLOSED')
            <span class="badge bg-success shadow-sm">SELESAI (CLOSED)</span>
        @elseif(in_array($st, ['PROCESSED', 'PROCEED']))
            <span class="badge bg-primary shadow-sm">TERPROSES (FULL)</span>
        @else
            <span class="badge bg-secondary">{{ $st }}</span>
        @endif
    </td>
    
    <td class="px-4 text-end">
        <a href="{{ url('/find-do-print/' . ($so['number'] ?? '')) }}" target="_blank" class="btn btn-sm btn-outline-dark">
            <i class="fa-solid fa-print"></i> Lihat Surat Jalan
        </a>
    </td>
</tr>
@endif
@empty
<tr><td colspan="5" class="text-center">Belum ada riwayat.</td></tr>
@endforelse