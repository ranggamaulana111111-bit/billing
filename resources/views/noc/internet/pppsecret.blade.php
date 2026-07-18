@extends('layouts.app')

@section('title', 'PPPoE / PPP Secret Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">Internet Service Center</a></li>
                <li class="breadcrumb-item active">PPPoE / PPP Secret</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-key me-2" style="color:var(--primary);"></i>PPPoE / PPP Secret Manager
        </h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ count($items) }} secrets</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form action="{{ route('customers.sync-pppoe') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary px-3 py-2" onclick="return confirm('Sync PPPoE semua pelanggan aktif ke MikroTik?')">
                <i class="fa-solid fa-rotate me-1"></i>Sync PPPoE
            </button>
        </form>
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Add Secret
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
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $router->id == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search secrets..." onkeyup="filterTable()">
            </div>
            <div class="col-md-3">
                <select id="filterStatus" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="online">Online Only</option>
                    <option value="offline">Offline Only</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
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
                        <i class="fa-solid fa-key fa-lg" style="color:#2563eb;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Total Secrets</div>
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
                        <div class="text-muted" style="font-size:0.78rem;">Online</div>
                        <h4 class="mb-0 fw-bold">{{ count(array_filter($items, fn($i) => in_array($i['name'] ?? '', $activeUsernames ?? []))) }}</h4>
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
                        <i class="fa-solid fa-circle-xmark fa-lg" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Offline</div>
                        <h4 class="mb-0 fw-bold">{{ count($items) - count(array_filter($items, fn($i) => in_array($i['name'] ?? '', $activeUsernames ?? []))) }}</h4>
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
                        <th>Username</th>
                        <th>Service</th>
                        <th>Profile</th>
                        <th>Local Address</th>
                        <th>Remote Address</th>
                        <th>Status</th>
                        <th>Comment / Session</th>
                        <th style="width:120px;"></th>
                    </tr>

                <tbody>
                    @php $activeNames = $activeUsernames ?? []; @endphp
                    @forelse($items as $item)
                    @php $isOnline = in_array($item['name'] ?? '', $activeNames); @endphp
                    @php $session = collect($activeSessions ?? [])->firstWhere('name', $item['name'] ?? ''); @endphp
                    <tr data-status="{{ $isOnline ? 'online' : 'offline' }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="fw-semibold">
                            {{ $item['name'] ?? '—' }}
                            @if($isOnline)
                            <br><small class="text-success"><i class="fa-solid fa-circle fa-xs me-1"></i>Connected</small>
                            @endif
                        </td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.7rem;">{{ $item['service'] ?? 'pppoe' }}</span></td>
                        <td>{{ $item['profile'] ?? '—' }}</td>
                        <td><code style="font-size:0.78rem;">{{ ($item['local-address'] ?? $session['local-address'] ?? '—') }}</code></td>
                        <td><code style="font-size:0.78rem;">{{ ($session['address'] ?? $item['remote-address'] ?? '—') }}</code></td>
                        <td>
                            @if($isOnline)
                            <span class="badge bg-success" style="font-size:0.6rem;">
                                <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>Online
                            </span>
                            @else
                            <span class="badge bg-secondary" style="font-size:0.6rem;">Offline</span>
                            @endif
                        </td>
                        <td>
                            @if($session)
                            <div style="font-size:0.75rem;">
                                <div><i class="fa-solid fa-clock me-1 text-primary"></i>{{ $session['uptime'] ?? '—' }}</div>
                                @if(isset($session['bytes-in']) || isset($session['bytes-out']))
                                <div class="mt-1">
                                    <span class="text-success"><i class="fa-solid fa-arrow-down"></i> {{ $session['bytes-in'] ?? '0' }}</span>
                                    <span class="text-danger ms-2"><i class="fa-solid fa-arrow-up"></i> {{ $session['bytes-out'] ?? '0' }}</span>
                                </div>
                                @endif
                                @if(isset($session['rate']))
                                <div class="mt-1"><i class="fa-solid fa-bolt me-1 text-warning"></i>{{ $session['rate'] }}</div>
                                @endif
                            </div>
                            @else
                            <span class="text-muted">{{ $item['comment'] ?? '—' }}</span>
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
                                        <li><a class="dropdown-item" href="#" onclick="toggleItem('{{ $item['.id'] }}')"><i class="fa-solid fa-{{ $isOnline ? 'ban' : 'check' }} me-2 text-{{ $isOnline ? 'warning' : 'success' }}"></i>{{ $isOnline ? 'Disable' : 'Enable' }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['name'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">
                        <i class="fa-solid fa-key" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No PPP secrets found on this router</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.internet.pppsecret-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add PPP Secret</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Username <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Password <span class="text-danger">*</span></label><input type="text" name="password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Service</label>
                <select name="service" class="form-select">
                    <option value="pppoe">PPPoE</option>
                    <option value="l2tp">L2TP</option>
                    <option value="pptp">PPTP</option>
                    <option value="ovpn">OpenVPN</option>
                    <option value="any">Any</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Profile</label><input type="text" name="profile" class="form-control" placeholder="default"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Local Address</label><input type="text" name="local-address" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Remote Address</label><input type="text" name="remote-address" class="form-control"></div>
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
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit PPP Secret</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-semibold">Username <span class="text-danger">*</span></label><input type="text" name="name" id="editName" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Password</label>
                <input type="text" name="password" id="editPassword" class="form-control" placeholder="">
                <div class="form-text text-warning"><i class="fa-solid fa-shield-halved me-1"></i>Leave empty to keep current password.</div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Service</label>
                <select name="service" id="editService" class="form-select">
                    <option value="pppoe">PPPoE</option>
                    <option value="l2tp">L2TP</option>
                    <option value="pptp">PPTP</option>
                    <option value="ovpn">OpenVPN</option>
                    <option value="any">Any</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Profile</label><input type="text" name="profile" id="editProfile" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Local Address</label><input type="text" name="local-address" id="editLocalAddress" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Remote Address</label><input type="text" name="remote-address" id="editRemoteAddress" class="form-control"></div>
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
<form method="POST" id="bulkForm" action="{{ route('noc.internet.pppsecret-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>

@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.internet.pppsecret-update', ['itemId' => 'X']));
const ROUTE_DESTROY = @json(route('noc.internet.pppsecret-destroy', ['itemId' => 'X']));
const ROUTE_TOGGLE = @json(route('noc.internet.pppsecret-toggle', ['itemId' => 'X']));

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        let show = true;
        if (q && !row.textContent.toLowerCase().includes(q)) show = false;
        if (status && row.dataset.status !== status) show = false;
        row.style.display = show ? '' : 'none';
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
    if (!confirm('Delete ' + checked.length + ' selected secrets?')) return;
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
    if (!confirm('Toggle ' + checked.length + ' selected secrets?')) return;
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
    document.getElementById('editPassword').value = '';
    document.getElementById('editService').value = item['service'] || 'pppoe';
    document.getElementById('editProfile').value = item['profile'] || '';
    document.getElementById('editLocalAddress').value = item['local-address'] || '';
    document.getElementById('editRemoteAddress').value = item['remote-address'] || '';
    document.getElementById('editComment').value = item['comment'] || '';
}

function toggleItem(id) {
    const form = document.getElementById('toggleForm');
    form.action = ROUTE_TOGGLE.replace('X', encodeURIComponent(id));
    form.submit();
}

function deleteItem(id, name) {
    if (!confirm('Delete secret "' + name + '"? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    form.action = ROUTE_DESTROY.replace('X', encodeURIComponent(id));
    form.submit();
}

// Prevent empty password from being sent in edit
document.getElementById('editForm').addEventListener('submit', function(e) {
    const pw = document.getElementById('editPassword');
    if (!pw.value.trim()) {
        pw.removeAttribute('name');
    }
});
</script>
@endpush

