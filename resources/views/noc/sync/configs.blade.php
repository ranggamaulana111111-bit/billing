@extends('layouts.app')

@section('title', 'Synced Configs — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-database me-2" style="color:var(--primary);"></i>Synced Configurations</h2>
        <p class="section-subtitle mb-0 mt-1">Browse konfigurasi RouterOS yang tersimpan lokal</p>
    </div>
    <a href="{{ route('noc.sync.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                            {{ $router->display_identity }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($modules as $key => $mod)
                        <option value="{{ $key }}" {{ request('module') === $key ? 'selected' : '' }}>{{ $mod['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                    <option value="conflict" {{ request('status') === 'conflict' ? 'selected' : '' }}>Conflict</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Item name or ID..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('noc.sync.configs') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ CONFIG TABLE ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.78rem;">Router</th>
                        <th style="font-size:0.78rem;">Module</th>
                        <th style="font-size:0.78rem;">Item Name</th>
                        <th style="font-size:0.78rem;">Item ID</th>
                        <th style="font-size:0.78rem;">Status</th>
                        <th style="font-size:0.78rem;">Last Synced</th>
                        <th style="font-size:0.78rem;">Checksum</th>
                    </tr>

                <tbody>
                    @forelse($configs as $cfg)
                    <tr>
                        <td style="font-size:0.8rem;">{{ $cfg->router->display_identity ?? '—' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border" style="font-size:0.72rem;">
                                {{ $modules[$cfg->module]['label'] ?? $cfg->module }}
                            </span>
                        </td>
                        <td class="fw-semibold" style="font-size:0.85rem;">{{ $cfg->item_name }}</td>
                        <td><code style="font-size:0.72rem;">{{ $cfg->item_id }}</code></td>
                        <td>
                            @if($cfg->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($cfg->status === 'deleted')
                                <span class="badge bg-danger">Deleted</span>
                            @else
                                <span class="badge bg-warning">Conflict</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">{{ $cfg->last_synced_at->format('d M H:i') }}</td>
                        <td>
                            <code style="font-size:0.65rem;" title="{{ $cfg->checksum }}">
                                {{ substr($cfg->checksum, 0, 12) }}…
                            </code>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fa-solid fa-database fa-2x mb-2 d-block opacity-25"></i>
                            Belum ada konfigurasi tersinkronisasi. Jalankan sync terlebih dahulu.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($configs->hasPages())
    <div class="card-footer bg-transparent">
        {{ $configs->links() }}
    </div>
    @endif
</div>
@endsection

