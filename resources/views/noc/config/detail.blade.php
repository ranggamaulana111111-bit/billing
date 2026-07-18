@extends('layouts.app')

@section('title', 'Detail — '.$moduleDef['label'])

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="{{ $moduleDef['icon'] }} me-2" style="color:var(--primary);"></i>{{ $moduleDef['label'] }} Detail
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="text-decoration-none">{{ $moduleDef['label'] }}</a>
            <i class="fa-solid fa-chevron-right mx-1" style="font-size:0.6rem;"></i>
            <code>{{ $itemId }}</code>
            <span class="badge bg-{{ $error ? 'danger' : 'success' }} ms-2" style="font-size:0.65rem;">{{ $error ? 'ERROR' : 'LIVE' }}</span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if($item && ($moduleDef['writable'] ?? false))
        <a href="{{ route('noc.config.edit', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="btn btn-warning px-3 py-2">
            <i class="fa-solid fa-pen me-1"></i>Edit
        </a>
        @if(($moduleDef['keyField'] ?? '') !== '__singleton__')
        <form action="{{ route('noc.config.destroy', ['module' => $module]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus item ini dari router? Tindakan ini tidak dapat dibatalkan.')">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id }}">
            <input type="hidden" name="item_id" value="{{ $itemId }}">
            <button type="submit" class="btn btn-danger px-3 py-2">
                <i class="fa-solid fa-trash me-1"></i>Delete
            </button>
        </form>
        @endif
        @endif
        <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <div><strong>Error:</strong> {{ session('error') }}</div>
</div>
@endif

@if(session('success'))
<div class="alert alert-success d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-check me-2"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <div><strong>Error:</strong> {{ $error }}</div>
</div>
@endif

@if($item)
<div class="row g-4">
    {{-- ═══ ITEM DETAILS ═══ --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-circle-info me-2"></i>Configuration</h6>
            </div>
            <div class="card-body">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr>
                                <th style="font-size:0.75rem;width:35%;">Field</th>
                                <th style="font-size:0.75rem;">Value</th>
                            </tr>

                        <tbody>
                            @foreach($item as $key => $value)
                            <tr>
                                <td>
                                    <code class="text-muted" style="font-size:0.78rem;">{{ $key }}</code>
                                </td>
                                <td style="font-size:0.85rem;">
                                    @if(is_array($value))
                                        <code style="font-size:0.75rem;">{{ json_encode($value) }}</code>
                                    @elseif(is_bool($value) || $value === 'true' || $value === 'false')
                                        <span class="badge bg-{{ ($value === 'true' || $value === true) ? 'success' : 'secondary' }}">{{ $value }}</span>
                                    @elseif($key === '.id')
                                        <code style="font-size:0.75rem;">{{ $value }}</code>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ SYNC STATUS ═══ --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-rotate me-2"></i>Sync Status</h6>
            </div>
            <div class="card-body">
                @if($syncedConfig)
                <div class="d-flex justify-content-between mb-2" style="font-size:0.82rem;">
                    <span class="text-muted">Status:</span>
                    <span class="badge bg-{{ $syncedConfig->status === 'active' ? 'success' : ($syncedConfig->status === 'deleted' ? 'danger' : 'warning') }}">
                        {{ ucfirst($syncedConfig->status) }}
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:0.82rem;">
                    <span class="text-muted">Last Synced:</span>
                    <span>{{ $syncedConfig->last_synced_at->diffForHumans() }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:0.82rem;">
                    <span class="text-muted">Checksum:</span>
                    <code style="font-size:0.68rem;">{{ substr($syncedConfig->checksum, 0, 16) }}...</code>
                </div>
                @else
                <div class="text-center text-muted py-3" style="font-size:0.82rem;">
                    <i class="fa-solid fa-cloud-arrow-down d-block mb-2" style="font-size:1.5rem;opacity:0.3;"></i>
                    Item belum tersinkronisasi.
                    <br>Gunakan tombol Sync untuk menyimpan data lokal.
                </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-server me-2"></i>Router</h6>
            </div>
            <div class="card-body" style="font-size:0.82rem;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Name:</span>
                    <span>{{ $router->display_identity }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Host:</span>
                    <code>{{ $router->host }}</code>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge bg-{{ $router->status === 'online' ? 'success' : 'secondary' }}">{{ ucfirst($router->status) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ RAW JSON ═══ --}}
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-code me-2"></i>Raw JSON</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('rawJson').textContent);this.innerHTML='<i class=\'fa-solid fa-check\'></i> Copied'">
            <i class="fa-solid fa-copy me-1"></i>Copy
        </button>
    </div>
    <div class="card-body">
        <pre id="rawJson" class="mb-0 p-3 rounded" style="background:var(--bs-body-bg);border:1px solid var(--bs-border-color);font-size:0.78rem;max-height:400px;overflow:auto;">{{ json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</div>
@else
<div class="text-center py-5">
    <i class="fa-solid fa-circle-exclamation d-block mb-3" style="font-size:3rem;opacity:0.2;"></i>
    <h5 class="text-muted">Item tidak ditemukan</h5>
    <p class="text-muted" style="font-size:0.85rem;">Item dengan ID <code>{{ $itemId }}</code> mungkin sudah dihapus dari router.</p>
    <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="btn btn-primary">Kembali ke daftar</a>
</div>
@endif
@endsection

