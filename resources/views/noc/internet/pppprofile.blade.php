@extends('layouts.app')

@section('title', 'PPP Profile Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">Internet Service Center</a></li>
                <li class="breadcrumb-item active">PPP Profile</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>PPP Profile Manager
        </h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('mikrotik.ppp-profiles.sync') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary px-3 py-2" onclick="return confirm('Sync PPP Profile dari MikroTik?')">
                <i class="fa-solid fa-rotate me-1"></i>Sync dari MikroTik
            </button>
        </form>
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add Profile
        </button>
    </div>
</div>

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
</div>
@endif

@if(session('success'))
<div class="alert alert-success d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
</div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $router->id == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search profiles..." onkeyup="filterTable()">
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group btn-group-sm" id="bulkActions" style="display:none;">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-outline-danger py-1" onclick="bulkDelete()" title="Delete Selected"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══ STAT CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(37,99,235,0.1);">
                        <i class="fa-solid fa-layer-group fa-lg" style="color:#2563eb;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Total Profiles</div>
                        <h4 class="mb-0 fw-bold">{{ count($items) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(25,135,84,0.1);">
                        <i class="fa-solid fa-gauge-high fa-lg" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">With Rate Limit</div>
                        <h4 class="mb-0 fw-bold">{{ count(array_filter($items, fn($i) => !empty($i['rate-limit']))) }}</h4>
                    </div>
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
                        <th>Local Address</th>
                        <th>Remote Address</th>
                        <th>Rate Limit</th>
                        <th>Only One</th>
                        <th>Comment</th>
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="fw-semibold">{{ $item['name'] ?? '—' }}</td>
                        <td><code style="font-size:0.78rem;">{{ $item['local-address'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.78rem;">{{ $item['remote-address'] ?? '—' }}</code></td>
                        <td>
                            @if(!empty($item['rate-limit']))
                            <span class="badge bg-success" style="font-size:0.7rem;">{{ $item['rate-limit'] }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if(($item['only-one'] ?? 'false') === 'true')
                            <span class="badge bg-primary">Yes</span>
                            @else
                            <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $item['comment'] ?? '—' }}</td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['name'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="fa-solid fa-layer-group" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No PPP profiles found on this router</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.internet.pppprofile-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add PPP Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Local Address</label><input type="text" name="local-address" class="form-control" placeholder="e.g. 10.0.0.1"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Remote Address</label><input type="text" name="remote-address" class="form-control" placeholder="e.g. 10.0.0.2"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Rate Limit</label><input type="text" name="rate-limit" class="form-control" placeholder="10M/5M"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Only One</label><select name="only-one" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" class="form-control"></div>
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
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit PPP Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" id="editName" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Local Address</label><input type="text" name="local-address" id="editLocalAddress" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Remote Address</label><input type="text" name="remote-address" id="editRemoteAddress" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Rate Limit</label><input type="text" name="rate-limit" id="editRateLimit" class="form-control" placeholder="10M/5M"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Only One</label><select name="only-one" id="editOnlyOne" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="editComment" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

{{-- ═══ DELETE FORM ═══ --}}
<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>

{{-- ═══ BULK FORM ═══ --}}
<form method="POST" id="bulkForm" action="{{ route('noc.internet.pppprofile-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="action" value="delete">
</form>

@endsection

@push('scripts')
<script>
const ROUTE_PREFIX = @json(route('noc.internet.pppprofile-update', ['itemId' => 'X']));

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

function bulkDelete() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!confirm('Delete ' + checked.length + ' selected profiles?')) return;
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

function prefillEdit(item) {
    document.getElementById('editForm').action = ROUTE_PREFIX.replace('X', encodeURIComponent(item['.id']));
    document.getElementById('editName').value = item['name'] || '';
    document.getElementById('editLocalAddress').value = item['local-address'] || '';
    document.getElementById('editRemoteAddress').value = item['remote-address'] || '';
    document.getElementById('editRateLimit').value = item['rate-limit'] || '';
    document.getElementById('editOnlyOne').value = item['only-one'] || 'false';
    document.getElementById('editComment').value = item['comment'] || '';
}

function deleteItem(id, name) {
    if (!confirm('Delete profile "' + name + '"? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    form.action = ROUTE_PREFIX.replace('X', encodeURIComponent(id));
    form.submit();
}
</script>
@endpush

