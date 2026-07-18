@extends('layouts.app')

@section('title', 'DHCP Lease Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">Internet Service Center</a></li>
                <li class="breadcrumb-item active">DHCP Leases</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-address-card me-2" style="color:var(--primary);"></i>DHCP Lease Manager
        </h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity }} · {{ count($items) }} leases</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="GET" class="d-inline">
            <input type="hidden" name="router_id" value="{{ $router->id }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        </form>
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add Static Lease
        </button>
    </div>
</div>

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

@if($error)
    <div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
    </div>
@endif

{{-- ═══ STAT CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--primary);color:#fff;">
                    <i class="fa-solid fa-address-card"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.75rem;">Total Leases</div>
                    <div class="fw-bold" style="font-size:1.3rem;">{{ count($items) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#198754;color:#fff;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.75rem;">Active</div>
                    <div class="fw-bold" style="font-size:1.3rem;">{{ collect($items)->where('status', 'used')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#0dcaf0;color:#fff;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.75rem;">Static</div>
                    <div class="fw-bold" style="font-size:1.3rem;">{{ collect($items)->where('dynamic', 'false')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#6c757d;color:#fff;">
                    <i class="fa-solid fa-random"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.75rem;">Dynamic</div>
                    <div class="fw-bold" style="font-size:1.3rem;">{{ collect($items)->where('dynamic', 'true')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search leases..." onkeyup="filterTable()">
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group btn-group-sm" id="bulkActions" style="display:none;">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-outline-danger py-1" onclick="bulkAction('delete')" title="Bulk Delete"><i class="fa-solid fa-trash"></i></button>
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
                        <th>MAC Address</th>
                        <th>IP Address</th>
                        <th>Server</th>
                        <th>Status</th>
                        <th>Dynamic</th>
                        <th>Comment</th>
                        <th style="width:120px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td style="font-size:0.78rem;"><code>{{ $item['mac-address'] ?? '—' }}</code></td>
                        <td class="fw-semibold"><code>{{ $item['address'] ?? '—' }}</code></td>
                        <td>{{ $item['server'] ?? '—' }}</td>
                        <td>
                            @php
                                $status = $item['status'] ?? 'waiting';
                            @endphp
                            @if($status === 'used' || $status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($status === 'expired' || $status === 'rejected')
                                <span class="badge bg-secondary">{{ ucfirst($status) }}</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ ucfirst($status) }}</span>
                            @endif
                        </td>
                        <td>
                            @if(($item['dynamic'] ?? 'true') === 'true')
                                <span class="badge bg-secondary">Dynamic</span>
                            @else
                                <span class="badge bg-info">Static</span>
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
                                        @if(($item['dynamic'] ?? 'true') === 'true')
                                        <li><a class="dropdown-item" href="#" onclick="makeStatic('{{ $item['.id'] }}')"><i class="fa-solid fa-lock me-2 text-info"></i>Make Static</a></li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['mac-address'] ?? $item['address'] ?? $item['.id']) }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="fa-solid fa-address-card" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No DHCP leases found on this router</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.internet.dhcplease-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add Static Lease</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label><input type="text" name="address" class="form-control" required placeholder="e.g. 192.168.1.100"></div>
            <div class="mb-3"><label class="form-label fw-semibold">MAC Address <span class="text-danger">*</span></label><input type="text" name="mac-address" class="form-control" required placeholder="e.g. AA:BB:CC:DD:EE:FF"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Server</label><input type="text" name="server" class="form-control" placeholder="e.g. dhcp1"></div>
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
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit Lease</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">IP Address</label><input type="text" name="address" id="editAddress" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">MAC Address</label><input type="text" name="mac-address" id="editMacAddress" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Server</label><input type="text" name="server" id="editServer" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="editComment" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

{{-- ═══ MAKE STATIC FORM ═══ --}}
<form method="POST" id="makeStaticForm" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
</form>

{{-- ═══ DELETE FORM ═══ --}}
<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>

{{-- ═══ BULK FORM ═══ --}}
<form method="POST" id="bulkForm" action="{{ route('noc.internet.dhcplease-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>

@endsection

@push('scripts')
<script>
const ROUTER_ID = '{{ $router->id }}';

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
    const msg = `Are you sure you want to delete ${checked.length} lease(s)?`;
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
    form.action = '{{ route("noc.internet.dhcplease-update", ["itemId" => "X"]) }}'.replace('X', encodeURIComponent(item['.id']));
    document.getElementById('editAddress').value = item['address'] || '';
    document.getElementById('editMacAddress').value = item['mac-address'] || '';
    document.getElementById('editServer').value = item['server'] || '';
    document.getElementById('editComment').value = item['comment'] || '';
}

function makeStatic(id) {
    if (!confirm('Convert this dynamic lease to a static lease?')) return;
    const form = document.getElementById('makeStaticForm');
    form.action = '{{ route("noc.internet.dhcplease-make-static", ["itemId" => "__ID__"]) }}'.replace('__ID__', encodeURIComponent(id));
    form.submit();
}

function deleteItem(id, name) {
    if (!confirm('Delete lease "' + name + '"? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    form.action = '{{ route("noc.internet.dhcplease-destroy", ["itemId" => "__ID__"]) }}'.replace('__ID__', encodeURIComponent(id));
    form.submit();
}
</script>
@endpush

