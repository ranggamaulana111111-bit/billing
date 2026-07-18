@extends('layouts.app')

@section('title', 'Simple Queue — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">Simple Queue</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-layer-group me-2" style="color:#6366f1;"></i>Simple Queue Manager</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="GET" class="d-inline">
            <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($routers as $r)
                <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                @endforeach
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i>Add Queue</button>
    </div>
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>@endif
@if(session('danger'))<div class="alert alert-danger alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('danger') }}<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>@endif

@if($error)
<div class="alert alert-warning mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-triangle-exclamation me-2"></i>API Error: {{ $error }}</div>
@endif

{{-- ═══ FILTER BAR ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-2">
                <select id="filterStatus" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="filterPriority" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Priority</option>
                    @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                </select>
            </div>
            <div class="col-md-2">
                <select id="filterQueueType" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Queue Types</option>
                    @foreach($queueTypes as $qt)
                    <option value="{{ $qt['name'] ?? '' }}">{{ $qt['name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search queues..." oninput="filterTable()">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="bulkBtn" disabled>Bulk Actions</button>
                    <ul class="dropdown-menu" style="font-size:0.82rem;">
                        <li><a class="dropdown-item" href="#" onclick="bulkOp('enable')"><i class="fa-solid fa-check me-1 text-success"></i>Enable</a></li>
                        <li><a class="dropdown-item" href="#" onclick="bulkOp('disable')"><i class="fa-solid fa-ban me-1 text-warning"></i>Disable</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="bulkOp('delete')"><i class="fa-solid fa-trash me-1 text-danger"></i>Delete</a></li>
                    </ul>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()" title="Select All"><i class="fa-solid fa-check-double"></i></button>
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
                        <th style="width:30px;"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>#</th>
                        <th>Name</th>
                        <th>Target</th>
                        <th>Max Limit</th>
                        <th>Min Limit</th>
                        <th>Queue</th>
                        <th>Priority</th>
                        <th>Rate</th>
                        <th>Bytes</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th style="width:140px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as (int) $idx => $item)
                    @php $disabled = ($item['disabled'] ?? 'false') === 'true'; @endphp
                    <tr data-name="{{ $item['name'] ?? '' }}" data-target="{{ $item['target'] ?? '' }}" data-status="{{ $disabled ? 'disabled' : 'enabled' }}" data-priority="{{ $item['priority'] ?? '1' }}" data-queue="{{ $item['queue'] ?? '' }}" data-search="{{ strtolower(implode(' ', [$item['name'] ?? '', $item['target'] ?? '', $item['max-limit'] ?? '', $item['queue'] ?? '', $item['priority'] ?? '', $item['comment'] ?? ''])) }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td class="fw-semibold" style="font-size:0.82rem;">{{ $item['name'] ?? '' }}</td>
                        <td><code style="font-size:0.72rem;">{{ $item['target'] ?? '—' }}</code></td>
                        <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $item['max-limit'] ?? '—' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $item['limit-at'] ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $item['queue'] ?? 'default' }}</span></td>
                        <td><span class="badge bg-info" style="font-size:0.65rem;">{{ $item['priority'] ?? '1' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $item['rate'] ?? '0' }}</td>
                        <td style="font-size:0.75rem;">{{ $item['bytes'] ?? '0' }}</td>
                        <td>
                            @if($disabled)
                            <span class="badge bg-secondary" style="font-size:0.62rem;">Disabled</span>
                            @else
                            <span class="badge bg-success" style="font-size:0.62rem;">Active</span>
                            @endif
                        </td>
                        <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.78rem;">{{ $item['comment'] ?? '—' }}</td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen"></i></button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="toggleItem('{{ $item['.id'] }}', {{ $disabled ? 'false' : 'true' }})"><i class="fa-solid fa-{{ $disabled ? 'play' : 'pause' }} me-2 text-{{ $disabled ? 'success' : 'warning' }}"></i>{{ $disabled ? 'Enable' : 'Disable' }}</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="copyItem('{{ $item['.id'] }}')"><i class="fa-solid fa-copy me-2 text-secondary"></i>Copy</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['name'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">
                        <i class="fa-solid fa-layer-group" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No simple queues found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.traffic_eng.simple-queue-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header py-2"><h6 class="modal-title fw-bold">Add Simple Queue</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:0.82rem;">
            <div class="mb-3"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control form-control-sm" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Target *</label><input type="text" name="target" class="form-control form-control-sm" placeholder="e.g. 192.168.1.0/24" required></div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Max Limit</label><input type="text" name="max-limit" class="form-control form-control-sm" placeholder="e.g. 10M/10M"></div>
                <div class="col-6"><label class="form-label fw-semibold">Min Limit</label><input type="text" name="min-limit" class="form-control form-control-sm" placeholder="e.g. 1M/1M"></div>
            </div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Queue Type</label>
                    <select name="queue" class="form-select form-select-sm">
                        <option value="default">default</option>
                        @foreach($queueTypes as $qt)<option value="{{ $qt['name'] ?? '' }}">{{ $qt['name'] ?? '' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6"><label class="form-label fw-semibold">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}" {{ $i === 1 ? 'selected' : '' }}>{{ $i }}</option>@endfor
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Burst Limit</label><input type="text" name="burst-limit" class="form-control form-control-sm" placeholder="e.g. 15M/15M"></div>
                <div class="col-6"><label class="form-label fw-semibold">Burst Threshold</label><input type="text" name="burst-threshold" class="form-control form-control-sm" placeholder="e.g. 5M/5M"></div>
            </div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Parent</label><input type="text" name="parent" class="form-control form-control-sm" placeholder="Parent queue name"></div>
                <div class="col-6"><label class="form-label fw-semibold">Burst Time</label><input type="text" name="burst-time" class="form-control form-control-sm" placeholder="e.g. 8/16"></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control form-control-sm"></div>
        </div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="editForm" method="POST" action="">
        @csrf
        @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header py-2"><h6 class="modal-title fw-bold">Edit Simple Queue</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:0.82rem;">
            <div class="mb-3"><label class="form-label fw-semibold">Name</label><input type="text" name="name" id="edit_name" class="form-control form-control-sm"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Target</label><input type="text" name="target" id="edit_target" class="form-control form-control-sm"></div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Max Limit</label><input type="text" name="max-limit" id="edit_max_limit" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label fw-semibold">Min Limit</label><input type="text" name="min-limit" id="edit_min_limit" class="form-control form-control-sm"></div>
            </div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Queue Type</label>
                    <select name="queue" id="edit_queue" class="form-select form-select-sm">
                        <option value="default">default</option>
                        @foreach($queueTypes as $qt)<option value="{{ $qt['name'] ?? '' }}">{{ $qt['name'] ?? '' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6"><label class="form-label fw-semibold">Priority</label>
                    <select name="priority" id="edit_priority" class="form-select form-select-sm">
                        @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Parent</label><input type="text" name="parent" id="edit_parent" class="form-control form-control-sm"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="edit_comment" class="form-control form-control-sm"></div>
        </div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
    </form>
</div></div></div>

{{-- ═══ HIDDEN FORMS ═══ --}}
<form id="deleteForm" method="POST" style="display:none;">@csrf @method('DELETE')</form>
<form id="toggleForm" method="POST" style="display:none;">@csrf</form>
<form id="copyForm" method="POST" style="display:none;">@csrf</form>
<form id="bulkForm" method="POST" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.traffic_eng.simple-queue-update', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_DESTROY = @json(route('noc.traffic_eng.simple-queue-destroy', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_TOGGLE = @json(route('noc.traffic_eng.simple-queue-toggle', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_COPY = @json(route('noc.traffic_eng.simple-queue-copy', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_BULK = @json(route('noc.traffic_eng.simple-queue-bulk', ['router_id' => request('router_id')]));

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const priority = document.getElementById('filterPriority').value;
    const queueType = document.getElementById('filterQueueType').value;
    document.querySelectorAll('tbody tr[data-search]').forEach(tr => {
        const matchSearch = !search || tr.dataset.search.includes(search);
        const matchStatus = !status || tr.dataset.status === status;
        const matchPriority = !priority || tr.dataset.priority === priority;
        const matchQueue = !queueType || tr.dataset.queue === queueType;
        tr.style.display = (matchSearch && matchStatus && matchPriority && matchQueue) ? '' : 'none';
    });
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.row-check').forEach(cb => { cb.checked = checked; });
    updateBulkActions();
}

function updateBulkActions() {
    const count = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkBtn').disabled = count === 0;
}

function prefillEdit(item) {
    document.getElementById('editForm').action = ROUTE_UPDATE.replace('X', item['.id']);
    document.getElementById('edit_name').value = item.name || '';
    document.getElementById('edit_target').value = item.target || '';
    document.getElementById('edit_max_limit').value = item['max-limit'] || '';
    document.getElementById('edit_min_limit').value = item['min-limit'] || '';
    document.getElementById('edit_queue').value = item.queue || 'default';
    document.getElementById('edit_priority').value = item.priority || '1';
    document.getElementById('edit_parent').value = item.parent || '';
    document.getElementById('edit_comment').value = item.comment || '';
}

function toggleItem(id, disable) {
    const form = document.getElementById('toggleForm');
    form.action = ROUTE_TOGGLE.replace('X', id);
    form.innerHTML += '<input type="hidden" name="disable" value="' + disable + '">';
    form.submit();
}

function copyItem(id) {
    const form = document.getElementById('copyForm');
    form.action = ROUTE_COPY.replace('X', id);
    form.submit();
}

function deleteItem(id, name) {
    if (!confirm('Delete queue "' + name + '"?')) return;
    const form = document.getElementById('deleteForm');
    form.action = ROUTE_DESTROY.replace('X', id);
    form.submit();
}

function bulkOp(action) {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
    if (ids.length === 0) return alert('Select items first');
    if (action === 'delete' && !confirm('Delete ' + ids.length + ' items?')) return;
    const form = document.getElementById('bulkForm');
    form.action = ROUTE_BULK;
    form.innerHTML += '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">';
    ids.forEach(id => { form.innerHTML += '<input type="hidden" name="item_ids[]" value="' + id + '">';
    });
    form.submit();
}
</script>
@endpush

