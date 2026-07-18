@extends('layouts.app')

@section('title', 'Address List — Security Policy Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.security.dashboard', ['router_id' => $router->id ?? '']) }}">Security Policy Center</a></li>
                <li class="breadcrumb-item active">Address List</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-address-book me-2" style="color:#10b981;"></i>Address List</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ count($items) }} entries · <code style="font-size:0.7rem;">/ip/firewall/address-list</code></p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i>Add Entry</button>
    </div>
</div>

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
</div>
@endif
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ═══ ROUTER SELECTOR + FILTER ═══ --}}
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
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">List Name</label>
                <select id="filterListName" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Lists</option>
                    @php $listNames = collect($items)->pluck('list')->unique()->sort()->values()->toArray(); @endphp
                    @foreach($listNames as $ln)
                    <option value="{{ $ln }}">{{ $ln }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Type</label>
                <select id="filterDynamic" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All</option>
                    <option value="static">Static</option>
                    <option value="dynamic">Dynamic</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search..." onkeyup="filterTable()">
            </div>
            <div class="col-md-2 text-end">
                <div class="btn-group btn-group-sm d-none" id="bulkActions">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0</span>
                    <button type="button" class="btn btn-outline-danger py-1" onclick="bulkOp('delete')" title="Delete"><i class="fa-solid fa-trash"></i></button>
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
                        <th style="width:35px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th style="width:40px;">#</th>
                        <th>List Name</th>
                        <th>Address</th>
                        <th>Timeout</th>
                        <th>Dynamic/Static</th>
                        <th>Comment</th>
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $idx => $item)
                    @php $dynamic = ($item['dynamic'] ?? 'false') === 'true'; @endphp
                    <tr data-list="{{ $item['list'] ?? '' }}" data-dynamic="{{ $dynamic ? 'dynamic' : 'static' }}" data-search="{{ strtolower(implode(' ', [$item['list'] ?? '', $item['address'] ?? '', $item['timeout'] ?? '', $item['comment'] ?? ''])) }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="text-muted">{{ (int) $idx + 1 }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $item['list'] ?? '' }}</span></td>
                        <td><code style="font-size:0.72rem;">{{ $item['address'] ?? '—' }}</code></td>
                        <td style="font-size:0.75rem;">{{ $item['timeout'] ?? '—' }}</td>
                        <td>
                            @if($dynamic)
                                <span class="badge bg-info" style="font-size:0.58rem;">DYNAMIC</span>
                            @else
                                <span class="badge bg-secondary" style="font-size:0.58rem;">STATIC</span>
                            @endif
                        </td>
                        <td style="font-size:0.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $item['comment'] ?? '—' }}</td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen"></i></button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['address'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="fa-solid fa-address-book" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No address list entries found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.security.address-list-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add Address List Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">List <span class="text-danger">*</span></label><input type="text" name="list" class="form-control" required placeholder="blocked-ips"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Address <span class="text-danger">*</span></label><input type="text" name="address" class="form-control" required placeholder="192.168.1.100"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Timeout</label><input type="text" name="timeout" class="form-control" placeholder="1d"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Create</button></div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="editForm" action="">
        @csrf @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit Address List Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">List</label><input type="text" name="list" id="editList" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Address</label><input type="text" name="address" id="editAddress" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Timeout</label><input type="text" name="timeout" id="editTimeout" class="form-control" placeholder="1d"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="editComment" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>
<form method="POST" id="bulkForm" action="{{ route('noc.security.address-list-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>
@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.security.address-list-update', ['itemId' => 'X']));
const ROUTE_DESTROY = @json(route('noc.security.address-list-destroy', ['itemId' => 'X']));

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const listName = document.getElementById('filterListName').value;
    const dynamic = document.getElementById('filterDynamic').value;
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        let show = true;
        if (q && !row.dataset.search.includes(q)) show = false;
        if (listName && row.dataset.list !== listName) show = false;
        if (dynamic && row.dataset.dynamic !== dynamic) show = false;
        row.style.display = show ? '' : 'none';
    });
}

function toggleSelectAll(el) { document.querySelectorAll('.row-check').forEach(cb => { cb.checked = el.checked; }); updateBulkActions(); }

function updateBulkActions() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulkActions');
    const cnt = document.getElementById('selectedCount');
    bar.classList.toggle('d-none', checked.length === 0);
    cnt.textContent = checked.length + ' sel';
}

function prefillEdit(item) {
    document.getElementById('editForm').action = ROUTE_UPDATE.replace('X', encodeURIComponent(item['.id']));
    document.getElementById('editList').value = item['list'] || '';
    document.getElementById('editAddress').value = item['address'] || '';
    document.getElementById('editTimeout').value = item['timeout'] || '';
    document.getElementById('editComment').value = item['comment'] || '';
}

function deleteItem(id, addr) { if (!confirm('Delete address list entry "'+addr+'"?')) return; const f = document.getElementById('deleteForm'); f.action = ROUTE_DESTROY.replace('X', encodeURIComponent(id)); f.submit(); }

function bulkOp(action) {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!confirm(action.toUpperCase() + ' ' + checked.length + ' selected entries?')) return;
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkActionInput').value = action;
    checked.forEach(cb => { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'item_ids[]'; inp.value = cb.value; form.appendChild(inp); });
    form.submit();
}
</script>
@endpush
