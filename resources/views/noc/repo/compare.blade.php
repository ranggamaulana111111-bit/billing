@extends('layouts.app')

@section('title', 'Compare Versions — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.repo.index') }}">Repository</a></li>
                <li class="breadcrumb-item active">Compare Versions</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-code-compare me-2" style="color:var(--primary);"></i>
            Version Comparison
        </h2>
    </div>
</div>

{{-- ═══ VERSION INFO ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-4 border-secondary">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><span class="badge bg-secondary">v{{ $from->version }}</span> From</h6>
                    <span class="badge bg-{{ $from->change_source_badge }}">{{ $from->change_source_label }}</span>
                </div>
                <div class="text-muted" style="font-size:0.8rem;">
                    {{ $from->module }} · {{ $from->router->display_identity ?? 'N/A' }}<br>
                    Item: {{ $from->item_name }}<br>
                    Changed: {{ $from->created_at->format('Y-m-d H:i:s') }}
                    @if($from->user) · By: {{ $from->user->name }}@endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><span class="badge bg-primary">v{{ $to->version }}</span> To</h6>
                    <span class="badge bg-{{ $to->change_source_badge }}">{{ $to->change_source_label }}</span>
                </div>
                <div class="text-muted" style="font-size:0.8rem;">
                    {{ $to->module }} · {{ $to->router->display_identity ?? 'N/A' }}<br>
                    Item: {{ $to->item_name }}<br>
                    Changed: {{ $to->created_at->format('Y-m-d H:i:s') }}
                    @if($to->user) · By: {{ $to->user->name }}@endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ DIFF SUMMARY ═══ --}}
@if($diff['summary'])
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-success fw-bold fs-4">+{{ $diff['summary']['added_count'] }}</div>
            <div class="text-muted" style="font-size:0.8rem;">Added</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-danger fw-bold fs-4">-{{ $diff['summary']['removed_count'] }}</div>
            <div class="text-muted" style="font-size:0.8rem;">Removed</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-warning fw-bold fs-4">~{{ $diff['summary']['changed_count'] }}</div>
            <div class="text-muted" style="font-size:0.8rem;">Changed</div>
        </div>
    </div>
</div>
@endif

{{-- ═══ ADDED ═══ --}}
@if(!empty($diff['added']))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-success bg-opacity-10 border-0 py-2">
        <h6 class="fw-bold mb-0 text-success"><i class="fa-solid fa-plus-circle me-1"></i> Added Keys ({{ count($diff['added']) }})</h6>
    </div>
    <div class="card-body p-0">
        
                <tr><th>Key</th><th>Value</th></tr>

            <tbody>
                @foreach($diff['added'] as $key => $value)
                <tr>
                    <td class="fw-semibold">{{ $key }}</td>
                    <td><code>{{ is_array($value) ? json_encode($value) : $value }}</code></td>
                </tr>
                @endforeach
            </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
        </table>
    </div>
</div>
@endif

{{-- ═══ REMOVED ═══ --}}
@if(!empty($diff['removed']))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-danger bg-opacity-10 border-0 py-2">
        <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-minus-circle me-1"></i> Removed Keys ({{ count($diff['removed']) }})</h6>
    </div>
    <div class="card-body p-0">
        
                <tr><th>Key</th><th>Previous Value</th></tr>

            <tbody>
                @foreach($diff['removed'] as $key => $value)
                <tr>
                    <td class="fw-semibold">{{ $key }}</td>
                    <td><code>{{ is_array($value) ? json_encode($value) : $value }}</code></td>
                </tr>
                @endforeach
            </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
        </table>
    </div>
</div>
@endif

{{-- ═══ CHANGED ═══ --}}
@if(!empty($diff['changed']))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-2">
        <h6 class="fw-bold mb-0 text-warning"><i class="fa-solid fa-pen-to-square me-1"></i> Changed Keys ({{ count($diff['changed']) }})</h6>
    </div>
    <div class="card-body p-0">
        
                <tr><th>Key</th><th>From (v{{ $from->version }})</th><th>To (v{{ $to->version }})</th></tr>

            <tbody>
                @foreach($diff['changed'] as $key => $change)
                <tr>
                    <td class="fw-semibold">{{ $key }}</td>
                    <td><code class="text-danger">{{ is_array($change['from']) ? json_encode($change['from']) : $change['from'] }}</code></td>
                    <td><code class="text-success">{{ is_array($change['to']) ? json_encode($change['to']) : $change['to'] }}</code></td>
                </tr>
                @endforeach
            </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
        </table>
    </div>
</div>
@endif

@if(empty($diff['added']) && empty($diff['removed']) && empty($diff['changed']))
<div class="alert alert-success d-flex align-items-center">
    <i class="fa-solid fa-check-circle me-2"></i>
    No differences found between these two versions.
</div>
@endif

{{-- ═══ FULL SNAPSHOTS ═══ --}}
<div class="row g-3 mt-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-2">
                <h6 class="fw-bold mb-0">v{{ $from->version }} — Full Snapshot</h6>
            </div>
            <div class="card-body p-0" style="max-height:400px; overflow-y:auto;">
                <pre class="p-3 mb-0" style="font-size:0.75rem; background:#1e1e2e; color:#cdd6f4; border-radius:0 0 0.5rem 0;">{{ json_encode($from->config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-2">
                <h6 class="fw-bold mb-0">v{{ $to->version }} — Full Snapshot</h6>
            </div>
            <div class="card-body p-0" style="max-height:400px; overflow-y:auto;">
                <pre class="p-3 mb-0" style="font-size:0.75rem; background:#1e1e2e; color:#cdd6f4; border-radius:0 0 0.5rem 0;">{{ json_encode($to->config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</div>

@endsection

