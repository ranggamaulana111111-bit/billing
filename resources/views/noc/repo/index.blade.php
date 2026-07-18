@extends('layouts.app')

@section('title', 'Configuration Repository — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-database me-2" style="color:var(--primary);"></i>Configuration Repository</h2>
        <p class="section-subtitle mb-0 mt-1">Versioned configuration storage — Source of Truth for all RouterOS configs</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.repo.changes') }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-clock-rotate-left me-1"></i>All Changes
        </a>
        <a href="{{ route('noc.config.modules') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-sliders me-1"></i>Config Center
        </a>
    </div>
</div>

{{-- ═══ FILTERS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm">
                    <option value="">All Routers</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($routerId ?? '') == $r->id ? 'selected' : '' }}>
                        {{ $r->display_identity }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ STATS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.78rem;">Total Versions</div>
                <h4 class="mb-0 fw-bold" style="color:var(--primary);">{{ number_format($stats['total_versions']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.78rem;">Unique Items Tracked</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-success);">{{ number_format($stats['unique_items']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.78rem;">Items with Changes</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-warning);">{{ number_format($stats['changed_items']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.78rem;">Changes (24h)</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-info);">{{ number_format($stats['recent_24h']) }}</h4>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CHANGES BY SOURCE ═══ --}}
@if(!empty($stats['sources_breakdown']))
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pb-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-layer-group me-1"></i> Changes by Source</h6>
    </div>
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-3">
            @foreach($stats['sources_breakdown'] as $source => $count)
            <span class="badge bg-{{ match($source) { 'sync' => 'info', 'manual' => 'warning', 'api' => 'primary', default => 'secondary' } }} fs-6 px-3 py-2">
                {{ ucfirst($source) }}: {{ number_format($count) }}
            </span>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    {{-- ═══ RECENT CHANGES ═══ --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> Recent Changes</h6>
                <a href="{{ route('noc.repo.changes') }}" class="text-decoration-none" style="font-size:0.8rem;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr>
                                <th>Module</th>
                                <th>Item</th>
                                <th>Ver</th>
                                <th>Source</th>
                                <th>Changed</th>
                                <th></th>
                            </tr>

                        <tbody>
                            @forelse($recentChanges as $change)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $change->module }}</span>
                                </td>
                                <td class="fw-semibold" style="max-width:200px;" title="{{ $change->item_id }}">
                                    {{ Str::limit($change->item_name, 30) }}
                                </td>
                                <td><span class="badge bg-secondary">v{{ $change->version }}</span></td>
                                <td>
                                    <span class="badge bg-{{ $change->change_source_badge }}">
                                        {{ $change->change_source_label }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $change->created_at->diffForHumans() }}</td>
                                <td>
                                    <a href="{{ route('noc.repo.show', $change->id) }}" class="btn btn-sm btn-outline-primary py-0">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No changes recorded yet</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ ITEMS WITH MOST VERSIONS ═══ --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-code-branch me-1"></i> Frequently Changed Items</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($changedItems as $item)
                    <a href="{{ route('noc.repo.item-history', ['routerId' => $item->mikrotik_router_id, 'module' => $item->module, 'itemId' => $item->item_id]) }}" class="list-group-item list-group-item-action py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold" style="font-size:0.82rem;">{{ Str::limit($item->item_name, 35) }}</div>
                                <small class="text-muted">{{ $item->module }} · {{ $item->router->display_identity ?? 'N/A' }}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning text-dark">{{ $item->total_versions }} versions</span>
                                <div><small class="text-muted">{{ $item->last_changed_at->diffForHumans() }}</small></div>
                            </div>
                        </div>
                    </a>
                    @empty
                    <div class="text-center text-muted py-4">No items have changed yet</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODULE CHANGE SUMMARY ═══ --}}
@if($moduleSummary->count())
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-table-cells me-1"></i> Module Version Summary</h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Router</th>
                        <th>Module</th>
                        <th>Versions</th>
                        <th>Latest Version</th>
                        <th>First Changed</th>
                        <th>Last Changed</th>
                    </tr>

                <tbody>
                    @foreach($moduleSummary as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->router->display_identity ?? 'N/A' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $row->module }}</span></td>
                        <td>{{ number_format($row->total_versions) }}</td>
                        <td><span class="badge bg-secondary">v{{ $row->latest_version }}</span></td>
                        <td class="text-muted">{{ $row->first_changed_at?->diffForHumans() ?? 'N/A' }}</td>
                        <td class="text-muted">{{ $row->last_changed_at?->diffForHumans() ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

