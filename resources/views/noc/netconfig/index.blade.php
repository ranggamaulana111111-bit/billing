@extends('layouts.app')

@section('title', $def['label'] . ' Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.netconfig.dashboard') }}">Network Config</a></li>
                <li class="breadcrumb-item active">{{ $def['label'] }}</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="{{ $def['icon'] ?? 'fa-solid fa-cube' }} me-2" style="color:var(--primary);"></i>{{ $def['label'] }} Manager
        </h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity }} · {{ count($items) }} items</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('noc.netconfig.sync', ['resource' => $resource]) }}" class="d-inline">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2"><i class="fa-solid fa-rotate me-1"></i>Sync</button>
        </form>
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add {{ Str::singular($def['label']) }}
        </button>
    </div>
</div>

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
</div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $router->id == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search..." onkeyup="filterTable()">
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group btn-group-sm" id="bulkActions" style="display:none;">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-outline-success py-1" onclick="bulkAction('enable')" title="Enable"><i class="fa-solid fa-check"></i></button>
                    <button type="button" class="btn btn-outline-warning py-1" onclick="bulkAction('disable')" title="Disable"><i class="fa-solid fa-ban"></i></button>
                    <button type="button" class="btn btn-outline-danger py-1" onclick="bulkAction('delete')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══ DATA TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        @if($resource === 'bridge')
                        <th>Name</th><th>Protocol Mode</th><th>Priority</th><th>VLAN Filter</th><th>Comment</th>
                        @elseif($resource === 'vlan')
                        <th>Name</th><th>VLAN ID</th><th>Interface</th><th>Comment</th>
                        @elseif($resource === 'ip_address')
                        <th>Address</th><th>Interface</th><th>Network</th><th>Comment</th><th>Status</th>
                        @endif
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        @if($resource === 'bridge')
                        <td class="fw-semibold">{{ $item['name'] ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $item['protocol-mode'] ?? 'rstp' }}</span></td>
                        <td>{{ $item['priority'] ?? '—' }}</td>
                        <td>
                            @if(($item['vlan-filtering'] ?? 'false') === 'true')
                            <span class="badge bg-success">Enabled</span>
                            @else
                            <span class="badge bg-secondary">Disabled</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $item['comment'] ?? '—' }}</td>
                        @elseif($resource === 'vlan')
                        <td class="fw-semibold">{{ $item['name'] ?? '—' }}</td>
                        <td><span class="badge bg-primary">{{ $item['vlan-id'] ?? '—' }}</span></td>
                        <td>{{ $item['interface'] ?? '—' }}</td>
                        <td class="text-muted">{{ $item['comment'] ?? '—' }}</td>
                        @elseif($resource === 'ip_address')
                        <td class="fw-semibold"><code>{{ $item['address'] ?? '—' }}</code></td>
                        <td>{{ $item['interface'] ?? '—' }}</td>
                        <td class="text-muted">{{ $item['network'] ?? '—' }}</td>
                        <td class="text-muted">{{ $item['comment'] ?? '—' }}</td>
                        <td>
                            @if(($item['disabled'] ?? 'false') === 'true')
                            <span class="badge bg-secondary">Disabled</span>
                            @else
                            <span class="badge bg-success">Active</span>
                            @endif
                        </td>
                        @endif
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                        @if($resource === 'ip_address')
                                        <li><a class="dropdown-item" href="#" onclick="toggleItem('{{ $item['.id'] }}', {{ ($item['disabled'] ?? 'false') === 'true' ? 'false' : 'true' }})"><i class="fa-solid fa-{{ ($item['disabled'] ?? 'false') === 'true' ? 'check' : 'ban' }} me-2 text-{{ ($item['disabled'] ?? 'false') === 'true' ? 'success' : 'warning' }}"></i>{{ ($item['disabled'] ?? 'false') === 'true' ? 'Enable' : 'Disable' }}</a></li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['name'] ?? $item['address'] ?? $item['.id']) }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No items found on this router</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.netconfig.store', ['resource' => $resource]) }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add {{ Str::singular($def['label']) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @if($resource === 'bridge')
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Protocol Mode</label><select name="protocol-mode" class="form-select"><option value="rstp">RSTP</option><option value="stp">STP</option><option value="mstp">MSTP</option></select></div>
            <div class="mb-3"><label class="form-label fw-semibold">Priority</label><input type="text" name="priority" class="form-control" value="0x8000" placeholder="0x8000"></div>
            <div class="mb-3"><label class="form-label fw-semibold">VLAN Filtering</label><select name="vlan-filtering" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control"></div>
            @elseif($resource === 'vlan')
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">VLAN ID <span class="text-danger">*</span></label><input type="number" name="vlan-id" class="form-control" required min="1" max="4094"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Interface <span class="text-danger">*</span></label><input type="text" name="interface" class="form-control" required placeholder="e.g. bridge1"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control"></div>
            @elseif($resource === 'ip_address')
            <div class="mb-3"><label class="form-label fw-semibold">Address <span class="text-danger">*</span></label><input type="text" name="address" class="form-control" required placeholder="e.g. 192.168.1.1/24"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Interface <span class="text-danger">*</span></label><input type="text" name="interface" class="form-control" required placeholder="e.g. ether1"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control"></div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Create</button></div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="editForm" action="">
        @csrf
        @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit {{ Str::singular($def['label']) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="editModalBody"></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

{{-- ═══ DELETE FORM ═══ --}}
<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')<input type="hidden" name="router_id" value="{{ $router->id }}"></form>

{{-- ═══ TOGGLE FORM ═══ --}}
<form method="POST" id="toggleForm" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="disable" id="toggleDisable" value="">
</form>

{{-- ═══ BULK FORM ═══ --}}
<form method="POST" id="bulkForm" action="{{ route('noc.netconfig.bulk', ['resource' => $resource]) }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>

@endsection

@push('scripts')
<script>
const RESOURCE = '{{ $resource }}';
const ROUTER_ID = '{{ $router->id }}';
const ROUTE_PREFIX = '{{ route("noc.netconfig.index", ["resource" => $resource]) }}';

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
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
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
    cnt.textContent = checked.length + ' selected';
}

function bulkAction(action) {
    const checked = document.querySelectorAll('.row-check:checked');
    const msg = `Are you sure you want to ${action} ${checked.length} items?`;
    if (!confirm(msg)) return;
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkActionInput').value = action;
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'item_ids[]';
        inp.value = cb.value;
        form.appendChild(inp);
    });
    form.submit();
}

function prefillEdit(item) {
    const form = document.getElementById('editForm');
    form.action = ROUTE_PREFIX + '/' + encodeURIComponent(item['.id']);
    const body = document.getElementById('editModalBody');
    let html = '';
    if (RESOURCE === 'bridge') {
        html += field('Name', 'name', item['name'] || '');
        html += selectField('Protocol Mode', 'protocol-mode', item['protocol-mode'] || 'rstp', {rstp:'RSTP',stp:'STP',mstp:'MSTP'});
        html += field('Priority', 'priority', item['priority'] || '0x8000');
        html += selectField('VLAN Filtering', 'vlan-filtering', item['vlan-filtering'] || 'false', {false:'No',true:'Yes'});
        html += field('Comment', 'comment', item['comment'] || '');
    } else if (RESOURCE === 'vlan') {
        html += field('Name', 'name', item['name'] || '');
        html += field('VLAN ID', 'vlan-id', item['vlan-id'] || '', 'number');
        html += field('Interface', 'interface', item['interface'] || '');
        html += field('Comment', 'comment', item['comment'] || '');
    } else if (RESOURCE === 'ip_address') {
        html += field('Address', 'address', item['address'] || '');
        html += field('Interface', 'interface', item['interface'] || '');
        html += field('Comment', 'comment', item['comment'] || '');
    }
    body.innerHTML = html;
}

function field(label, name, val, type='text') {
    return `<div class="mb-3"><label class="form-label fw-semibold">${label}</label><input type="${type}" name="${name}" class="form-control" value="${escHtml(val)}"></div>`;
}

function selectField(label, name, val, opts) {
    let h = `<div class="mb-3"><label class="form-label fw-semibold">${label}</label><select name="${name}" class="form-select">`;
    for (const [k, v] of Object.entries(opts)) {
        h += `<option value="${k}" ${val===k?'selected':''}>${v}</option>`;
    }
    return h + '</select></div>';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function deleteItem(id, name) {
    if (!confirm('Delete "' + name + '"? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    form.action = ROUTE_PREFIX + '/' + encodeURIComponent(id);
    form.submit();
}

function toggleItem(id, disable) {
    const form = document.getElementById('toggleForm');
    form.action = ROUTE_PREFIX + '/' + encodeURIComponent(id) + '/toggle';
    document.getElementById('toggleDisable').value = disable;
    form.submit();
}
</script>
@endpush

