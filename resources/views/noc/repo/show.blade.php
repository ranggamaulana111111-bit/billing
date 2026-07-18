@extends('layouts.app')

@section('title', 'Version v' . $version->version . ' — ' . $version->item_name . ' — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.repo.index') }}">Repository</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('noc.repo.item-history', ['routerId' => $version->mikrotik_router_id, 'module' => $version->module, 'itemId' => $version->item_id]) }}">
                        {{ $version->module }} · {{ Str::limit($version->item_id, 30) }}
                    </a>
                </li>
                <li class="breadcrumb-item active">v{{ $version->version }}</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-file-code me-2" style="color:var(--primary);"></i>
            Configuration Snapshot
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <span class="badge bg-secondary me-1">v{{ $version->version }}</span>
            {{ $version->item_name }}
        </p>
    </div>
</div>

{{-- ═══ VERSION META ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Module</div>
            <div class="fw-bold">{{ $version->module }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Router</div>
            <div class="fw-bold">{{ $version->router->display_identity ?? 'N/A' }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Source</div>
            <span class="badge bg-{{ $version->change_source_badge }} fs-6">{{ $version->change_source_label }}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Changed By</div>
            <div class="fw-bold">{{ $version->user->name ?? 'System' }}</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Timestamp</div>
            <div class="fw-bold" style="font-size:0.9rem;">{{ $version->created_at->format('Y-m-d H:i:s') }}</div>
            <small class="text-muted">{{ $version->created_at->diffForHumans() }}</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Checksum</div>
            <code style="font-size:0.7rem; word-break:break-all;">{{ $version->checksum }}</code>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm py-3 text-center">
            <div class="text-muted mb-1" style="font-size:0.75rem;">Summary</div>
            <div class="fw-semibold" style="font-size:0.9rem;">{{ $version->change_summary ?? '—' }}</div>
        </div>
    </div>
</div>

{{-- ═══ DIFF FROM PREVIOUS ═══ --}}
@if($version->diff_from_previous)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 py-2">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-code-compare me-1"></i> Changes from v{{ $version->version - 1 }}</h6>
    </div>
    <div class="card-body p-0">
        <div class="row g-0">
            @if(!empty($version->diff_from_previous['added']))
            <div class="col-md-4 p-3">
                <div class="text-success fw-bold mb-2"><i class="fa-solid fa-plus-circle me-1"></i> Added ({{ count($version->diff_from_previous['added']) }})</div>
                @foreach($version->diff_from_previous['added'] as $k => $v)
                <div class="mb-1" style="font-size:0.8rem;"><code>{{ $k }}</code>: <span class="text-success">{{ is_array($v) ? json_encode($v) : $v }}</span></div>
                @endforeach
            </div>
            @endif
            @if(!empty($version->diff_from_previous['removed']))
            <div class="col-md-4 p-3">
                <div class="text-danger fw-bold mb-2"><i class="fa-solid fa-minus-circle me-1"></i> Removed ({{ count($version->diff_from_previous['removed']) }})</div>
                @foreach($version->diff_from_previous['removed'] as $k => $v)
                <div class="mb-1" style="font-size:0.8rem;"><code>{{ $k }}</code>: <span class="text-danger">{{ is_array($v) ? json_encode($v) : $v }}</span></div>
                @endforeach
            </div>
            @endif
            @if(!empty($version->diff_from_previous['changed']))
            <div class="col-md-4 p-3">
                <div class="text-warning fw-bold mb-2"><i class="fa-solid fa-pen-to-square me-1"></i> Changed ({{ count($version->diff_from_previous['changed']) }})</div>
                @foreach($version->diff_from_previous['changed'] as $k => $c)
                <div class="mb-1" style="font-size:0.8rem;">
                    <code>{{ $k }}</code>:
                    <span class="text-danger text-decoration-line-through">{{ is_array($c['from']) ? json_encode($c['from']) : $c['from'] }}</span>
                    → <span class="text-success">{{ is_array($c['to']) ? json_encode($c['to']) : $c['to'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ═══ FULL CONFIG JSON ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-2">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-code me-1"></i> Full Configuration Snapshot</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('configJson').textContent)">
            <i class="fa-solid fa-copy me-1"></i>Copy
        </button>
    </div>
    <div class="card-body p-0" style="max-height:600px; overflow-y:auto;">
        <pre id="configJson" class="p-3 mb-0" style="font-size:0.75rem; background:#1e1e2e; color:#cdd6f4; border-radius:0 0 0.5rem 0;">{{ json_encode($version->config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>

@endsection
