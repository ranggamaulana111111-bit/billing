@extends('layouts.app')

@section('title', 'Template Cetak Voucher')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-0"><i class="fa-solid fa-ticket me-2" style="color:#e11d48;"></i>Template Cetak Voucher</h2>
            <p class="text-muted mb-0">Desain struk voucher WiFi yang akan dicetak.</p>
        </div>
        <div>
            <a href="{{ route('voucher-print-templates.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>Template Baru
            </a>
            <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($templates->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">Belum ada template cetak. Buat template baru.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Ukuran Kertas</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $t)
                                <tr>
                                    <td class="fw-semibold">{{ $t->name }}</td>
                                    <td><span class="badge bg-light text-dark text-uppercase">{{ $t->paper_size }}</span></td>
                                    <td>
                                        @if($t->is_active)
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('voucher-print-templates.preview', $t) }}" target="_blank" class="btn btn-outline-secondary" title="Preview">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="{{ route('voucher-print-templates.edit', $t) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            @if(!$t->is_active)
                                                <button type="button" class="btn btn-outline-success" onclick="activateTpl({{ $t->id }})" title="Aktifkan">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteTpl({{ $t->id }})" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function activateTpl(id){
    if(!confirm('Jadikan template ini aktif?'))return;
    fetch('{{ route("voucher-print-templates.activate", 0) }}'.replace('/0/','/'+id+'/'),{
        method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}
    }).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert('Gagal: '+(d.message||''));});
}
function deleteTpl(id){
    if(!confirm('Hapus template ini?'))return;
    fetch('{{ route("voucher-print-templates.destroy", 0) }}'.replace('/0','/'+id),{
        method:'DELETE',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}
    }).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert('Gagal: '+(d.message||''));});
}
</script>
@endpush
