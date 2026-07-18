@extends('layouts.app')

@section('title', $moduleDef['label'].' — Configuration Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="{{ $moduleDef['icon'] }} me-2" style="color:var(--primary);"></i>{{ $moduleDef['label'] }}
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            {{ $router->display_identity }} — <code style="font-size:0.78rem;">{{ $router->host }}</code>
            <span class="badge bg-{{ $error ? 'danger' : 'success' }} ms-2" style="font-size:0.65rem;">{{ $error ? 'ERROR' : 'LIVE' }}</span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        @if($moduleDef['writable'] ?? false)
        <a href="{{ route('noc.config.create', ['module' => $module, 'router_id' => $router->id]) }}" class="btn btn-primary px-3 py-2">
            <i class="fa-solid fa-plus me-1"></i>Add
        </a>
        @endif
        <form action="{{ route('noc.config.sync-module', $module) }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2" title="Sync Module">
                <i class="fa-solid fa-rotate me-1"></i>Sync
            </button>
        </form>
        <button type="button" class="btn btn-outline-secondary px-3 py-2" id="btnRefresh" title="Refresh">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
        <a href="{{ route('noc.config.modules') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

{{-- ═══ STAT CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;background:rgba(13,202,240,0.1);">
                        <i class="fa-solid fa-list" style="color:#0dcaf0;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.75rem;">Live Items</div>
                        <h5 class="mb-0 fw-bold" id="statCount">{{ count($items) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;background:rgba(25,135,84,0.1);">
                        <i class="fa-solid fa-database" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.75rem;">Synced (Local)</div>
                        <h5 class="mb-0 fw-bold">{{ $syncedCount }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;background:rgba(108,117,125,0.1);">
                        <i class="fa-solid fa-server" style="color:#6c757d;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.75rem;">Router</div>
                        <div class="fw-bold" style="font-size:0.9rem;">{{ $router->display_identity }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;background:rgba(13,202,240,0.1);">
                        <i class="fa-solid fa-globe" style="color:#0dcaf0;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.75rem;">REST Path</div>
                        <code style="font-size:0.78rem;">{{ $moduleDef['path'] }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ROUTER SELECTOR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" id="routerSelect">
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}" {{ $r->id === $router->id ? 'selected' : '' }}>
                            {{ $r->display_identity }} ({{ $r->host }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Filter items...">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="fa-solid fa-server me-1"></i>Load
                </button>
                <a href="{{ route('noc.config.history', $module) }}?router_id={{ $router->id }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i>History
                </a>
            </div>
        </form>
    </div>
</div>

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <div>
        <strong>Connection Error:</strong> {{ $error }}
        <br><small>Router mungkin offline atau REST API tidak aktif.</small>
    </div>
</div>
@endif

{{-- ═══ DATA TABLE ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">{{ $moduleDef['label'] }} — {{ count($items) }} items</h6>
        <div class="d-flex gap-2">
            <span class="badge bg-secondary" style="font-size:0.72rem;">Bulk Action (Coming Soon)</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.75rem;width:40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select All">
                        </th>
                        <th style="font-size:0.75rem;">ID</th>
                        @if($moduleDef['keyField'] !== '__singleton__')
                        <th style="font-size:0.75rem;">Name</th>
                        @endif
                        <th style="font-size:0.75rem;">Status</th>
                        <th style="font-size:0.75rem;" class="text-end">Actions</th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    @php
                        $itemId = \App\Services\Mikrotik\Config\ConfigModuleRegistry::extractItemId($item, $module);
                        $itemName = \App\Services\Mikrotik\Config\ConfigModuleRegistry::extractItemName($item, $module);
                        $isDisabled = isset($item['disabled']) && $item['disabled'] === 'true';
                    @endphp
                    <tr class="item-row" data-search="{{ strtolower(json_encode($item)) }}">
                        <td>
                            <input type="checkbox" class="form-check-input item-checkbox" value="{{ $itemId }}">
                        </td>
                        <td><code style="font-size:0.72rem;">{{ $itemId }}</code></td>
                        @if($moduleDef['keyField'] !== '__singleton__')
                        <td>
                            <a href="{{ route('noc.config.detail', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="text-decoration-none fw-semibold" style="font-size:0.85rem;">
                                {{ $itemName }}
                            </a>
                        </td>
                        @endif
                        <td>
                            @if($isDisabled)
                                <span class="badge bg-secondary">Disabled</span>
                            @else
                                <span class="badge bg-success">Active</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($moduleDef['keyField'] === '__singleton__')
                            <a href="{{ route('noc.config.detail', ['module' => $module, 'router_id' => $router->id]) }}" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @else
                            <a href="{{ route('noc.config.detail', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @if($moduleDef['writable'] ?? false)
                            <a href="{{ route('noc.config.edit', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form action="{{ route('noc.config.destroy', ['module' => $module]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus item {{ addslashes($itemName) }} dari router?')">
                                @csrf
                                <input type="hidden" name="router_id" value="{{ $router->id }}">
                                <input type="hidden" name="item_id" value="{{ $itemId }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            @endif
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-25"></i>
@if(session('success'))
<div class="alert alert-success d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-check me-2"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

@if($error)
                                Gagal mengambil data dari router.
                            @else
                                Tidak ada data.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ RECENT SYNC LOGS ═══ --}}
@if($recentLogs->count())
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Sync Logs</h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.75rem;">Time</th>
                        <th style="font-size:0.75rem;">Type</th>
                        <th style="font-size:0.75rem;">Status</th>
                        <th style="font-size:0.75rem;" class="text-end">Items</th>
                        <th style="font-size:0.75rem;" class="text-end">Duration</th>
                    </tr>

                <tbody>
                    @foreach($recentLogs as $log)
                    <tr>
                        <td style="font-size:0.8rem;">{{ $log->started_at->diffForHumans() }}</td>
                        <td>
                            <span class="badge bg-{{ $log->sync_type === 'manual' ? 'info' : 'secondary' }}">{{ ucfirst($log->sync_type) }}</span>
                        </td>
                        <td><span class="badge bg-{{ $log->status_badge_color }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="text-end" style="font-size:0.8rem;">{{ $log->total_items }}</td>
                        <td class="text-end" style="font-size:0.8rem;">{{ $log->duration_human }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function() {
    // Router selector
    document.getElementById('routerSelect')?.addEventListener('change', function() {
        window.location.href = '{{ route("noc.config.module", $module) }}?router_id=' + this.value;
    });

    // Refresh button
    document.getElementById('btnRefresh')?.addEventListener('click', function() {
        window.location.reload();
    });

    // Search/filter
    document.getElementById('searchInput')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.item-row').forEach(row => {
            row.style.display = row.dataset.search.includes(q) ? '' : 'none';
        });
    });

    // Select all
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
    });
})();
</script>
@endpush

