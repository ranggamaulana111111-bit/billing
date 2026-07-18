@extends('layouts.app')

@section('title', 'Configuration Changes — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.repo.index') }}">Repository</a></li>
                <li class="breadcrumb-item active">All Changes</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>
            Configuration Changes
        </h2>
        <p class="section-subtitle mb-0 mt-1">Riwayat perubahan konfigurasi dengan filter lanjutan</p>
    </div>
</div>

{{-- ═══ FILTERS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm">
                    <option value="">All Routers</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ request('router_id') == $r->id ? 'selected' : '' }}>
                        {{ $r->display_identity }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">All Modules</option>
                    @foreach($modules as $key => $mod)
                    <option value="{{ $key }}" {{ request('module') == $key ? 'selected' : '' }}>
                        {{ $mod['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">All Sources</option>
                    @foreach($sources as $s)
                    <option value="{{ $s }}" {{ request('source') == $s ? 'selected' : '' }}>
                        {{ ucfirst($s) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('noc.repo.changes') }}" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ RESULTS ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="fa-solid fa-list me-1"></i> Changes
            <span class="badge bg-secondary ms-1">{{ $changes->total() }}</span>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Module</th>
                        <th>Item</th>
                        <th>Version</th>
                        <th>Summary</th>
                        <th>Source</th>
                        <th>Changed By</th>
                        <th>Router</th>
                        <th>When</th>
                        <th></th>
                    </tr>

                <tbody>
                    @forelse($changes as $change)
                    <tr>
                        <td class="text-muted">{{ $change->id }}</td>
                        <td><span class="badge bg-light text-dark">{{ $change->module }}</span></td>
                        <td class="fw-semibold" style="max-width:180px;" title="{{ $change->item_id }}">
                            {{ Str::limit($change->item_name, 25) }}
                        </td>
                        <td><span class="badge bg-secondary">v{{ $change->version }}</span></td>
                        <td style="max-width:200px;">{{ Str::limit($change->change_summary ?? '—', 35) }}</td>
                        <td>
                            <span class="badge bg-{{ $change->change_source_badge }}">
                                {{ $change->change_source_label }}
                            </span>
                        </td>
                        <td>{{ $change->user->name ?? 'System' }}</td>
                        <td>{{ $change->router->display_identity ?? 'N/A' }}</td>
                        <td class="text-muted">{{ $change->created_at->diffForHumans() }}</td>
                        <td>
                            <a href="{{ route('noc.repo.show', $change->id) }}" class="btn btn-sm btn-outline-primary py-0">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No changes found matching filters</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($changes->hasPages())
    <div class="card-footer bg-transparent border-0">
        {{ $changes->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection

