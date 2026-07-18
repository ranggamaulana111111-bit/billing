@extends('layouts.app')

@section('title', 'Hotspot Server Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Hotspot Server</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-wifi me-2" style="color:var(--primary);"></i>Hotspot Server Manager
        </h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add Server
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
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search servers..." onkeyup="filterTable()">
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group btn-group-sm" id="bulkActions" style="display:none;">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-outline-success py-1" onclick="bulkToggle()" title="Toggle Selected"><i class="fa-solid fa-toggle-on"></i></button>
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
                        <i class="fa-solid fa-server fa-lg" style="color:#2563eb;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Total Servers</div>
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
                        <i class="fa-solid fa-circle-check fa-lg" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Active Users</div>
                        <h4 class="mb-0 fw-bold">{{ count(array_filter($items, fn($i) => ($i['disabled'] ?? 'false') !== 'true')) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(220,53,69,0.1);">
                        <i class="fa-solid fa-ban fa-lg" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Disabled</div>
                        <h4 class="mb-0 fw-bold">{{ count(array_filter($items, fn($i) => ($i['disabled'] ?? 'false') === 'true')) }}</h4>
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
                        <th>Interface</th>
                        <th>Address Pool</th>
                        <th>Profile</th>
                        <th>Status</th>
                        <th style="width:120px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="fw-semibold">{{ $item['name'] ?? '—' }}</td>
                        <td>{{ $item['interface'] ?? '—' }}</td>
                        <td><code style="font-size:0.78rem;">{{ $item['address-pool'] ?? '—' }}</code></td>
                        <td>{{ $item['profile'] ?? '—' }}</td>
                        <td>
                            @if(($item['disabled'] ?? 'false') === 'true')
                            <span class="badge bg-secondary" style="font-size:0.6rem;">Disabled</span>
                            @else
                            <span class="badge bg-success" style="font-size:0.6rem;">
                                <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>Active
                            </span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="toggleItem('{{ $item['.id'] }}')"><i class="fa-solid fa-{{ ($item['disabled'] ?? 'false') === 'true' ? 'check' : 'ban' }} me-2 text-{{ ($item['disabled'] ?? 'false') === 'true' ? 'success' : 'warning' }}"></i>{{ ($item['disabled'] ?? 'false') === 'true' ? 'Enable' : 'Disable' }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['name'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="fa-solid fa-wifi" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No hotspot servers found on this router</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ HOTSPOT USERS ═══ --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#hotspotUsersCollapse" style="cursor:pointer;">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-users me-1" style="color:var(--primary);"></i>
            <span class="fw-bold">Hotspot Users</span>
            <span class="badge bg-primary" style="font-size:0.7rem;">{{ count($hotspotUsers) }}</span>
        </div>
        <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;"></i>
    </div>
    <div id="hotspotUsersCollapse" class="collapse">
        <div class="card-body p-0">
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                
                        <tr>
                            <th>Username</th>
                            <th>Server</th>
                            <th>Profile</th>
                            <th>Limit Uptime</th>
                            <th>Status</th>
                            <th style="width:100px;"></th>
                        </tr>

                    <tbody>
                        @forelse($hotspotUsers as $u)
                        @php $isDisabled = ($u['disabled'] ?? 'false') === 'true'; @endphp
                        <tr style="{{ $isDisabled ? 'opacity:0.5;' : '' }}">
                            <td class="fw-semibold">{{ $u['name'] ?? '—' }}</td>
                            <td>{{ $u['server'] ?? '—' }}</td>
                            <td><code style="font-size:0.78rem;">{{ $u['profile'] ?? '—' }}</code></td>
                            <td><code style="font-size:0.78rem;">{{ $u['limit-uptime'] ?? '—' }}</code></td>
                            <td>
                                @if($isDisabled)
                                    <span class="badge bg-danger" style="font-size:0.6rem;">Disabled</span>
                                @else
                                    <span class="badge bg-success" style="font-size:0.6rem;">Active</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('noc.internet.hotspotuser-toggle', $u['.id'] ?? '') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="router_id" value="{{ $router->id }}">
                                    <input type="hidden" name="disable" value="{{ $isDisabled ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm {{ $isDisabled ? 'btn-outline-success' : 'btn-outline-warning' }} py-0" title="{{ $isDisabled ? 'Enable' : 'Disable' }}">
                                        <i class="fa-solid {{ $isDisabled ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">
                            <i class="fa-solid fa-users" style="font-size:1.5rem;"></i>
                            <p class="mt-1 mb-0">Tidak ada hotspot user</p>
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.internet.hotspot-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add Hotspot Server</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required placeholder="e.g. hotspot1"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Interface <span class="text-danger">*</span></label><input type="text" name="interface" class="form-control" required placeholder="e.g. wlan1"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Address Pool</label><input type="text" name="address-pool" class="form-control" placeholder="e.g. hotspot_pool"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Profile</label><input type="text" name="profile" class="form-control" placeholder="default"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Disabled</label><select name="disabled" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
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
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit Hotspot Server</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label><input type="text" name="name" id="editName" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Interface <span class="text-danger">*</span></label><input type="text" name="interface" id="editInterface" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Address Pool</label><input type="text" name="address-pool" id="editAddressPool" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Profile</label><input type="text" name="profile" id="editProfile" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Disabled</label><select name="disabled" id="editDisabled" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="editComment" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

{{-- ═══ DELETE FORM ═══ --}}
<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>

{{-- ═══ TOGGLE FORM ═══ --}}
<form method="POST" id="toggleForm" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
</form>

{{-- ═══ BULK FORM ═══ --}}
<form method="POST" id="bulkForm" action="{{ route('noc.internet.hotspot-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>

@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.internet.hotspot-update', ['itemId' => 'X']));
const ROUTE_DESTROY = @json(route('noc.internet.hotspot-destroy', ['itemId' => 'X']));
const ROUTE_TOGGLE = @json(route('noc.internet.hotspot-toggle', ['itemId' => 'X']));

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
    if (!confirm('Delete ' + checked.length + ' selected servers?')) return;
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkActionInput').value = 'delete';
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'item_ids[]';
        inp.value = cb.value;
        form.appendChild(inp);
    });
    form.submit();
}

function bulkToggle() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!confirm('Toggle ' + checked.length + ' selected servers?')) return;
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkActionInput').value = 'toggle';
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
    document.getElementById('editForm').action = ROUTE_UPDATE.replace('X', encodeURIComponent(item['.id']));
    document.getElementById('editName').value = item['name'] || '';
    document.getElementById('editInterface').value = item['interface'] || '';
    document.getElementById('editAddressPool').value = item['address-pool'] || '';
    document.getElementById('editProfile').value = item['profile'] || '';
    document.getElementById('editDisabled').value = item['disabled'] || 'false';
    document.getElementById('editComment').value = item['comment'] || '';
}

function toggleItem(id) {
    const form = document.getElementById('toggleForm');
    form.action = ROUTE_TOGGLE.replace('X', encodeURIComponent(id));
    form.submit();
}

function deleteItem(id, name) {
    if (!confirm('Delete hotspot server "' + name + '"? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    form.action = ROUTE_DESTROY.replace('X', encodeURIComponent(id));
    form.submit();
}
</script>
@endpush

