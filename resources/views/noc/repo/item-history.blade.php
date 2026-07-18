@extends('layouts.app')

@section('title', 'Version History — ' . ($moduleDef['label'] ?? $module) . ' — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.repo.index') }}">Repository</a></li>
                <li class="breadcrumb-item">{{ $moduleDef['label'] ?? $module }}</li>
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($itemId, 40) }}</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-code-branch me-2" style="color:var(--primary);"></i>
            Version History
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            {{ $moduleDef['label'] ?? $module }} · {{ $router->display_identity }} · Item: {{ $itemId }}
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.config.module', ['module' => $module, 'router_id' => $router->id]) }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-sliders me-1"></i>Back to Module
        </a>
    </div>
</div>

@if($latest)
<div class="alert alert-info d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-info me-2"></i>
    Current version: <strong class="mx-1">v{{ $latest->version }}</strong>
    · Last changed: {{ $latest->created_at->diffForHumans() }}
    · Source: <span class="badge bg-{{ $latest->change_source_badge }} mx-1">{{ $latest->change_source_label }}</span>
</div>
@endif

{{-- ═══ VERSION TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> All Versions</h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:60px;">Version</th>
                        <th>Summary</th>
                        <th>Source</th>
                        <th>Changed By</th>
                        <th>When</th>
                        <th style="width:100px;">Actions</th>
                    </tr>

                <tbody>
                    @forelse($versions as $ver)
                    <tr>
                        <td>
                            <span class="badge {{ $ver->version === ($latest->version ?? 0) ? 'bg-success' : 'bg-secondary' }}">
                                v{{ $ver->version }}
                            </span>
                        </td>
                        <td>{{ $ver->change_summary ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $ver->change_source_badge }}">
                                {{ $ver->change_source_label }}
                            </span>
                        </td>
                        <td>{{ $ver->user->name ?? 'System' }}</td>
                        <td class="text-muted">{{ $ver->created_at->diffForHumans() }}<br>
                            <small>{{ $ver->created_at->format('Y-m-d H:i:s') }}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('noc.repo.show', $ver->id) }}" class="btn btn-outline-primary py-0" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                @if($ver->diff_from_previous)
                                <button class="btn btn-outline-warning py-0" title="Diff"
                                        data-bs-toggle="modal" data-bs-target="#diffModal{{ $ver->id }}">
                                    <i class="fa-solid fa-code-compare"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Inline diff display --}}
                    @if($ver->diff_from_previous && ($ver->diff_from_previous['summary']['added_count'] + $ver->diff_from_previous['summary']['removed_count'] + $ver->diff_from_previous['summary']['changed_count']) > 0)
                    <tr class="table-active">
                        <td colspan="6" class="p-0">
                            <div class="px-3 py-2" style="font-size:0.78rem;">
                                <strong>v{{ $ver->version }} changes:</strong>
                                @if($ver->diff_from_previous['summary']['added_count'] > 0)
                                <span class="text-success">+{{ $ver->diff_from_previous['summary']['added_count'] }} added</span>
                                @endif
                                @if($ver->diff_from_previous['summary']['removed_count'] > 0)
                                <span class="text-danger ms-2">-{{ $ver->diff_from_previous['summary']['removed_count'] }} removed</span>
                                @endif
                                @if($ver->diff_from_previous['summary']['changed_count'] > 0)
                                <span class="text-warning ms-2">~{{ $ver->diff_from_previous['summary']['changed_count'] }} changed</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No versions recorded for this item</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($versions->hasPages())
    <div class="card-footer bg-transparent border-0">
        {{ $versions->withQueryString()->links() }}
    </div>
    @endif
</div>

{{-- ═══ COMPARISON FORM ═══ --}}
@if($versions->count() >= 2)
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-code-compare me-1"></i> Compare Versions</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('noc.repo.compare') }}" class="row g-2 align-items-end">
            <input type="hidden" name="item_router" value="{{ $router->id }}">
            <input type="hidden" name="item_module" value="{{ $module }}">
            <input type="hidden" name="item_id" value="{{ $itemId }}">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">From Version</label>
                <select name="from" class="form-select form-select-sm" required>
                    @foreach($versions as $ver)
                    <option value="{{ $ver->id }}">v{{ $ver->version }} — {{ $ver->created_at->format('Y-m-d H:i') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">To Version</label>
                <select name="to" class="form-select form-select-sm" required>
                    @foreach($versions as $ver)
                    <option value="{{ $ver->id }}" {{ $loop->first ? 'selected' : '' }}>v{{ $ver->version }} — {{ $ver->created_at->format('Y-m-d H:i') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-code-compare me-1"></i>Compare
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection

