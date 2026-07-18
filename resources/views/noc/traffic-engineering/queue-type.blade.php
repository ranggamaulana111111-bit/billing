@extends('layouts.app')

@section('title', 'Queue Type — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">Queue Type</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-cubes me-2" style="color:#06b6d4;"></i>Queue Type Manager</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="GET" class="d-inline">
            <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($routers as $r)
                <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                @endforeach
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i>Add Queue Type</button>
    </div>
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>@endif
@if(session('danger'))<div class="alert alert-danger alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('danger') }}<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>@endif

@if($error)
<div class="alert alert-warning mb-4 py-2" style="font-size:0.85rem;"><i class="fa-solid fa-triangle-exclamation me-2"></i>API Error: {{ $error }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search queue types..." oninput="filterTable()">
            </div>
            <div class="col-md-8 d-flex gap-1 justify-content-end">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="bulkBtn" disabled>Bulk</button>
                    <ul class="dropdown-menu" style="font-size:0.82rem;">
                        <li><a class="dropdown-item" href="#" onclick="bulkOp('delete')"><i class="fa-solid fa-trash me-1 text-danger"></i>Delete</a></li>
                    </ul>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()"><i class="fa-solid fa-check-double"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:30px;"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>#</th>
                        <th>Name</th>
                        <th>Kind</th>
                        <th>PFIFO Limit</th>
                        <th>RED Avg Packet</th>
                        <th>SFQ Allot</th>
                        <th>SFQ Perturb</th>
                        <th>CAKE Diffserv</th>
                        <th>CAKE Flowmode</th>
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as (int) $idx => $item)
                    <tr data-search="{{ strtolower(implode(' ', [$item['name'] ?? '', $item['kind'] ?? '', $item['pfifo-limit'] ?? '', $item['cake-diffserv'] ?? '', $item['cake-flowmode'] ?? ''])) }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td class="fw-semibold" style="font-size:0.82rem;">{{ $item['name'] ?? '' }}</td>
                        <td><span class="badge bg-info" style="font-size:0.65rem;">{{ $item['kind'] ?? 'unknown' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $item['pfifo-limit'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['red-avg-packet'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['sfq-allot'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['sfq-perturb'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['cake-diffserv'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['cake-flowmode'] ?? '—' }}</td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prefillEdit({{ json_encode($item) }})"><i class="fa-solid fa-pen"></i></button>
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
                    <tr><td colspan="11" class="text-center text-muted py-4">
                        <i class="fa-solid fa-cubes" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No queue types found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('noc.traffic_eng.queue-type-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header py-2"><h6 class="modal-title fw-bold">Add Queue Type</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:0.82rem;">
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label fw-semibold">Kind *</label>
                    <select name="kind" class="form-select form-select-sm" required>
                        <option value="pfifo">pfifo</option>
                        <option value="red">red</option>
                        <option value="sfq">sfq</option>
                        <option value="cake">cake</option>
                        <option value="fq-codel">fq-codel</option>
                        <option value="fifo">fifo</option>
                    </select>
                </div>
            </div>
            <h6 class="fw-bold mt-3 mb-2">PFIFO Settings</h6>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label">PFIFO Limit</label><input type="number" name="pfifo-limit" class="form-control form-control-sm" value="50"></div>
                <div class="col-6"><label class="form-label">PFIFO Packet Limit</label><input type="number" name="pfifo-packet-limit" class="form-control form-control-sm"></div>
            </div>
            <h6 class="fw-bold mt-3 mb-2">RED Settings</h6>
            <div class="row mb-3">
                <div class="col-4"><label class="form-label">Avg Packet</label><input type="number" name="red-avg-packet" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label">Max Threshold</label><input type="number" name="red-max-threshold" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label">Min Threshold</label><input type="number" name="red-min-threshold" class="form-control form-control-sm"></div>
            </div>
            <h6 class="fw-bold mt-3 mb-2">SFQ Settings</h6>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label">SFQ Allot</label><input type="number" name="sfq-allot" class="form-control form-control-sm" value="1514"></div>
                <div class="col-6"><label class="form-label">SFQ Perturb</label><input type="number" name="sfq-perturb" class="form-control form-control-sm" value="10"></div>
            </div>
            <h6 class="fw-bold mt-3 mb-2">CAKE Settings</h6>
            <div class="row mb-3">
                <div class="col-4"><label class="form-label">Diffserv</label>
                    <select name="cake-diffserv" class="form-select form-select-sm"><option value="">—</option><option value="besteffort">besteffort</option><option value="diffserv3">diffserv3</option><option value="diffserv4">diffserv4</option><option value="diffserv8">diffserv8</option><option value="diffserv-llt">diffserv-llt</option></select>
                </div>
                <div class="col-4"><label class="form-label">Flowmode</label>
                    <select name="cake-flowmode" class="form-select form-select-sm"><option value="">—</option><option value="flowblind">flowblind</option><option value="srchost">srchost</option><option value="dsthost">dsthost</option><option value="hosts">hosts</option><option value="flows">flows</option><option value="dual-srchost">dual-srchost</option><option value="dual-dsthost">dual-dsthost</option><option value="nt">nt</option><option value="source">source</option><option value="destination">destination</option><option value="virtual- hosts">virtual-hosts</option><option value="perhost">perhost</option><option value="perip">perip</option></select>
                </div>
                <div class="col-4"><label class="form-label">NAT</label>
                    <select name="cake-nat" class="form-select form-select-sm"><option value="no">No</option><option value="yes">Yes</option></select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Memlimit</label><input type="text" name="cake-memlimit" class="form-control form-control-sm" placeholder="e.g. 2mbit"></div>
        </div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="editForm" method="POST" action="">
        @csrf @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header py-2"><h6 class="modal-title fw-bold">Edit Queue Type</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:0.82rem;">
            <div class="row mb-3">
                <div class="col-6"><label class="form-label fw-semibold">Name</label><input type="text" name="name" id="edit_name" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label fw-semibold">Kind</label>
                    <select name="kind" id="edit_kind" class="form-select form-select-sm">
                        <option value="pfifo">pfifo</option><option value="red">red</option><option value="sfq">sfq</option><option value="cake">cake</option><option value="fq-codel">fq-codel</option><option value="fifo">fifo</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><label class="form-label">PFIFO Limit</label><input type="number" name="pfifo-limit" id="edit_pfifo_limit" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label">SFQ Allot</label><input type="number" name="sfq-allot" id="edit_sfq_allot" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label">SFQ Perturb</label><input type="number" name="sfq-perturb" id="edit_sfq_perturb" class="form-control form-control-sm"></div>
            </div>
            <div class="row mb-3">
                <div class="col-6"><label class="form-label">CAKE Diffserv</label><input type="text" name="cake-diffserv" id="edit_cake_diffserv" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label">CAKE Flowmode</label><input type="text" name="cake-flowmode" id="edit_cake_flowmode" class="form-control form-control-sm"></div>
            </div>
        </div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
    </form>
</div></div></div>

<form id="deleteForm" method="POST" style="display:none;">@csrf @method('DELETE')</form>
<form id="bulkForm" method="POST" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.traffic_eng.queue-type-update', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_DESTROY = @json(route('noc.traffic_eng.queue-type-destroy', ['itemId' => 'X', 'router_id' => request('router_id')]));
const ROUTE_BULK = @json(route('noc.traffic_eng.queue-type-bulk', ['router_id' => request('router_id')]));

function filterTable() {
    const s = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('tbody tr[data-search]').forEach(tr => { tr.style.display = !s || tr.dataset.search.includes(s) ? '' : 'none'; });
}
function toggleSelectAll() { const c = document.getElementById('selectAll').checked; document.querySelectorAll('.row-check').forEach(cb => cb.checked = c); updateBulkActions(); }
function updateBulkActions() { document.getElementById('bulkBtn').disabled = !document.querySelectorAll('.row-check:checked').length; }
function prefillEdit(item) {
    document.getElementById('editForm').action = ROUTE_UPDATE.replace('X', item['.id']);
    document.getElementById('edit_name').value = item.name || '';
    document.getElementById('edit_kind').value = item.kind || 'pfifo';
    document.getElementById('edit_pfifo_limit').value = item['pfifo-limit'] || '';
    document.getElementById('edit_sfq_allot').value = item['sfq-allot'] || '';
    document.getElementById('edit_sfq_perturb').value = item['sfq-perturb'] || '';
    document.getElementById('edit_cake_diffserv').value = item['cake-diffserv'] || '';
    document.getElementById('edit_cake_flowmode').value = item['cake-flowmode'] || '';
}
function deleteItem(id, name) { if (!confirm('Delete "' + name + '"?')) return; const f = document.getElementById('deleteForm'); f.action = ROUTE_DESTROY.replace('X', id); f.submit(); }
function bulkOp(action) {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
    if (!ids.length) return alert('Select items first');
    if (action === 'delete' && !confirm('Delete ' + ids.length + ' items?')) return;
    const f = document.getElementById('bulkForm'); f.action = ROUTE_BULK;
    f.innerHTML += '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">';
    ids.forEach(id => { f.innerHTML += '<input type="hidden" name="item_ids[]" value="' + id + '">'; }); f.submit();
}
</script>
@endpush

