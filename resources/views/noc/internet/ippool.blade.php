@extends('layouts.app')

@section('title', 'IP Pool Manager — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard', ['router_id' => $router->id ?? '']) }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">IP Pool Manager</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>IP Pool Manager</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router selected' }} · {{ count($items) }} pools</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.internet.conflicts', ['router_id' => $router->id ?? '']) }}" class="btn btn-outline-warning px-3 py-2" title="Check IP Conflicts">
            <i class="fa-solid fa-shield-halved me-1"></i>IP Conflicts
        </a>
        <form method="POST" action="{{ route('noc.internet.ippool-sync') }}" class="d-inline">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        </form>
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add Pool
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($error)
    <div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
    </div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
@if($routers && $routers->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══ STAT CARDS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-layer-group"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <div>
                        <div class="stat-number">{{ count($poolUsage) }}</div>
                        <div class="stat-label">Total Pools</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#10b981,#059669);min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-cubes"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-cubes"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format(collect($poolUsage)->sum('total_ips')) }}</div>
                        <div class="stat-label">Total IPs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format(collect($poolUsage)->sum('total_used')) }}</div>
                        <div class="stat-label">Used IPs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-arrow-down-long"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-arrow-down-long"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format(collect($poolUsage)->sum('free')) }}</div>
                        <div class="stat-label">Free IPs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ FILTER + BULK BAR ═══ --}}
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nama pool..." onkeyup="filterTable()">
            </div>
            <div class="col-md-8 text-end">
                <div class="btn-group btn-group-sm d-none" id="bulkActions">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-outline-danger py-1" onclick="bulkDelete()" title="Delete Selected"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ DATA TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th>Name</th>
                        <th>Ranges</th>
                        <th class="text-center">Total IPs</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Free</th>
                        <th style="min-width:140px;">Usage %</th>
                        <th class="text-center">Status</th>
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr class="pool-row" data-search="{{ strtolower($item['name'] ?? '') }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="fw-semibold">{{ $item['name'] ?? '—' }}</td>
                        <td style="font-size:0.76rem;">
                            @if(isset($item['ranges']) && is_array($item['ranges']))
                                @foreach($item['ranges'] as $range)
                                    <code>{{ $range }}</code>@if(!$loop->last)<br>@endif
                                @endforeach
                            @elseif(isset($item['range']))
                                <code>{{ $item['range'] }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $item['total_ips'] ?? '—' }}</td>
                        <td class="text-center fw-semibold">{{ $item['used'] ?? '—' }}</td>
                        <td class="text-center">{{ $item['free'] ?? '—' }}</td>
                        <td>
                            @php
                                $pct = $item['percent'] ?? 0;
                                $barClass = $pct > 95 ? 'bg-danger' : ($pct > 85 ? 'bg-warning' : 'bg-success');
                            @endphp
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:6px;">
                                    <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%"></div>
                                </div>
                                <span style="font-size:0.75rem;min-width:36px;">{{ number_format($pct, 1) }}%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            @if(($item['disabled'] ?? false) || ($item['disabled'] ?? '') === 'true')
                                <span class="badge bg-secondary" style="font-size:0.6rem;">DISABLED</span>
                            @else
                                <span class="badge bg-success" style="font-size:0.6rem;">
                                    <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>ACTIVE
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary py-0" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                    onclick="prefillEdit({{ json_encode($item) }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn btn-outline-danger py-0" title="Delete"
                                    onclick="deleteItem('{{ $item['.id'] ?? '' }}', '{{ addslashes($item['name'] ?? '') }}')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="fa-solid fa-layer-group" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada IP pool ditemukan di router ini</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.internet.ippool-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add IP Pool</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. pool_hotspot_1">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Ranges <span class="text-danger">*</span></label>
                <textarea name="ranges" class="form-control" rows="4" required placeholder="192.168.1.10-192.168.1.100&#10;192.168.1.200-192.168.1.250"></textarea>
                <small class="text-muted">Satu range per baris. Format: <code>IP-awal-IP-akhir</code></small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Comment</label>
                <input type="text" name="comment" class="form-control" placeholder="Opsional">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Create</button>
        </div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="editForm" action="">
        @csrf
        @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit IP Pool</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Ranges <span class="text-danger">*</span></label>
                <textarea name="ranges" id="editRanges" class="form-control" rows="4" required></textarea>
                <small class="text-muted">Satu range per baris. Format: <code>IP-awal-IP-akhir</code></small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Comment</label>
                <input type="text" name="comment" id="editComment" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button>
        </div>
    </form>
</div></div></div>

{{-- ═══ DELETE FORM ═══ --}}
<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')<input type="hidden" name="router_id" value="{{ $router->id ?? '' }}"></form>

{{-- ═══ BULK FORM ═══ --}}
<form method="POST" id="bulkForm" action="{{ route('noc.internet.ippool-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
    <input type="hidden" name="action" value="delete">
</form>

@endsection

@push('scripts')
<script>
const ROUTER_ID = '{{ $router->id ?? '' }}';

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.pool-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}

function toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(cb => { cb.checked = el.checked; });
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulkActions');
    const cnt = document.getElementById('selectedCount');
    bar.classList.toggle('d-none', checked.length === 0);
    cnt.textContent = checked.length + ' selected';
}

function prefillEdit(item) {
    const form = document.getElementById('editForm');
    form.action = '{{ route("noc.internet.ippool-update", ["itemId" => "__ID__"]) }}'.replace('__ID__', encodeURIComponent(item['.id'] || ''));
    document.getElementById('editName').value = item['name'] || '';
    const ranges = item['ranges'] || (item['range'] ? [item['range']] : []);
    document.getElementById('editRanges').value = Array.isArray(ranges) ? ranges.join('\n') : ranges;
    document.getElementById('editComment').value = item['comment'] || '';
}

function deleteItem(id, name) {
    if (!confirm('Hapus pool "' + name + '"? Aksi ini tidak dapat dibatalkan.')) return;
    const form = document.getElementById('deleteForm');
    form.action = '{{ route("noc.internet.ippool-destroy", ["itemId" => "__ID__"]) }}'.replace('__ID__', encodeURIComponent(id));
    form.submit();
}

function bulkDelete() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) return;
    if (!confirm('Hapus ' + checked.length + ' pool yang dipilih?')) return;
    const form = document.getElementById('bulkForm');
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'item_ids[]';
        inp.value = cb.value;
        form.appendChild(inp);
    });
    form.submit();
}
</script>
@endpush
