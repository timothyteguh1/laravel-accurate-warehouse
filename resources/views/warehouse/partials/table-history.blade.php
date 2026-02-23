@forelse($orders as $so)
@if(is_array($so))
<tr>
    <td class="px-4 py-3">{{ $so['transDate'] ?? '-' }}</td>
    
    <td class="px-4 py-3">
        <span class="badge bg-secondary">{{ $so['number'] ?? '-' }}</span>
    </td>
    
    <td class="px-4 py-3">
        {{ $so['customer']['name'] ?? 'Umum' }}
    </td>
    
    <td class="px-4 py-3">
        @php $st = strtoupper($so['status'] ?? ''); @endphp
        
        @if($st == 'CLOSED')
            <span class="badge bg-success shadow-sm">SELESAI (CLOSED)</span>
        @elseif(in_array($st, ['PROCESSED', 'PROCEED']))
            <span class="badge bg-primary shadow-sm">TERPROSES (FULL)</span>
        @else
            <span class="badge bg-secondary">{{ $st }}</span>
        @endif
    </td>
    
    <td class="px-4 py-3 text-end">
        {{-- TOMBOL BARU: Memanggil Pop-up showDOList --}}
        <button type="button" class="btn btn-dark btn-sm fw-bold shadow-sm" onclick="showDOList('{{ $so['number'] ?? '' }}')">
            <i class="fa-solid fa-print me-1"></i> Lihat Surat Jalan
        </button>
    </td>
</tr>
@endif
@empty
<tr>
    <td colspan="5" class="text-center text-muted py-5">
        <i class="fa-solid fa-folder-open fs-2 mb-3 d-block text-secondary opacity-50"></i>
        Belum ada riwayat pesanan yang sudah diproses atau lunas.
    </td>
</tr>
@endforelse