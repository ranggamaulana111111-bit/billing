@extends('layouts.app')

@section('title', 'Mangle Rules — Security Policy Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.security.dashboard', ['router_id' => $router->id ?? '']) }}">Security Policy Center</a></li>
                <li class="breadcrumb-item active">Mangle Rules</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-tag me-2" style="color:#8b5cf6;"></i>Mangle Rules</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ count($items) }} rules · <code style="font-size:0.7rem;">/ip/firewall/mangle</code></p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i>Add Rule</button>
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
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Chain</label>
                <select id="filterChain" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Chains</option>
                    <option value="prerouting">prerouting</option>
                    <option value="input">input</option>
                    <option value="forward">forward</option>
                    <option value="output">output</option>
                    <option value="postrouting">postrouting</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Action</label>
                <select id="filterAction" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All Actions</option>
                    <option value="mark-packet">mark-packet</option>
                    <option value="mark-connection">mark-connection</option>
                    <option value="mark-routing">mark-routing</option>
                    <option value="passthrough">passthrough</option>
                    <option value="accept">accept</option>
                    <option value="drop">drop</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select id="filterStatus" class="form-select form-select-sm" onchange="filterTable()">
                    <option value="">All</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                    <option value="dynamic">Dynamic</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search..." onkeyup="filterTable()">
            </div>
            <div class="col-md-2 text-end">
                <div class="btn-group btn-group-sm d-none" id="bulkActions">
                    <span class="btn btn-outline-secondary py-1" id="selectedCount">0</span>
                    <button type="button" class="btn btn-outline-success py-1" onclick="bulkOp('enable')" title="Enable"><i class="fa-solid fa-check"></i></button>
                    <button type="button" class="btn btn-outline-warning py-1" onclick="bulkOp('disable')" title="Disable"><i class="fa-solid fa-ban"></i></button>
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
                        <th>Chain</th>
                        <th>Action</th>
                        <th>Packet Marks</th>
                        <th>Conn Marks</th>
                        <th>Routing Marks</th>
                        <th>Src Address</th>
                        <th>Dst Address</th>
                        <th>Dst Port</th>
                        <th>Protocol</th>
                        <th>Passthrough</th>
                        <th class="text-center">Hits</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th style="width:140px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $idx => $item)
                    @php $disabled = ($item['disabled'] ?? 'false') === 'true'; $dynamic = ($item['dynamic'] ?? 'false') === 'true'; @endphp
                    <tr data-chain="{{ $item['chain'] ?? '' }}" data-action="{{ $item['action'] ?? '' }}" data-status="{{ $disabled ? 'disabled' : ($dynamic ? 'dynamic' : 'enabled') }}" data-search="{{ strtolower(implode(' ', [$item['chain'] ?? '', $item['action'] ?? '', $item['new-packet-marks'] ?? '', $item['new-connection-mark'] ?? '', $item['new-routing-mark'] ?? '', $item['src-address'] ?? '', $item['dst-address'] ?? '', $item['dst-port'] ?? '', $item['comment'] ?? ''])) }}">
                        <td><input type="checkbox" class="row-check" value="{{ $item['.id'] ?? '' }}" onchange="updateBulkActions()"></td>
                        <td class="text-muted">{{ (int) $idx + 1 }}</td>
                        <td>
                            @php $chainColors = ['prerouting'=>'info','input'=>'primary','forward'=>'warning','output'=>'success','postrouting'=>'dark']; @endphp
                            <span class="badge bg-{{ $chainColors[$item['chain'] ?? ''] ?? 'secondary' }}" style="font-size:0.65rem;">{{ $item['chain'] ?? '' }}</span>
                        </td>
                        <td>
                            @php $actColors = ['mark-packet'=>'primary','mark-connection'=>'info','mark-routing'=>'warning','passthrough'=>'secondary','accept'=>'success','drop'=>'danger']; @endphp
                            <span class="badge bg-{{ $actColors[$item['action'] ?? ''] ?? 'secondary' }}" style="font-size:0.65rem;">{{ $item['action'] ?? '' }}</span>
                        </td>
                        <td><code style="font-size:0.72rem;">{{ $item['new-packet-marks'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $item['new-connection-mark'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $item['new-routing-mark'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $item['src-address'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $item['dst-address'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $item['dst-port'] ?? '—' }}</code></td>
                        <td style="font-size:0.75rem;">{{ $item['protocol'] ?? '—' }}</td>
                        <td style="font-size:0.75rem;">{{ $item['passthrough'] ?? '—' }}</td>
                        <td class="text-center">
                            @php $pkts = $item['packets'] ?? '0'; $bts = $item['bytes'] ?? '0'; @endphp
                            <span style="font-size:0.72rem;" title="{{ number_format((int)$pkts) }} packets, {{ number_format((int)$bts) }} bytes">{{ $pkts }}</span>
                        </td>
                        <td>
                            @if($disabled)
                                <span class="badge bg-secondary" style="font-size:0.58rem;">DISABLED</span>
                            @elseif($dynamic)
                                <span class="badge bg-info" style="font-size:0.58rem;">DYNAMIC</span>
                            @else
                                <span class="badge bg-success" style="font-size:0.58rem;"><span style="display:inline-block;width:4px;height:4px;border-radius:50%;background:#fff;margin-right:2px;"></span>ACTIVE</span>
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
                                        <li><a class="dropdown-item" href="#" onclick="toggleItem('{{ $item['.id'] }}', {{ $disabled ? 'false' : 'true' }})"><i class="fa-solid fa-{{ $disabled ? 'check' : 'ban' }} me-2 text-{{ $disabled ? 'success' : 'warning' }}"></i>{{ $disabled ? 'Enable' : 'Disable' }}</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="copyItem('{{ $item['.id'] }}')"><i class="fa-solid fa-copy me-2 text-info"></i>Copy</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem('{{ $item['.id'] }}', '{{ addslashes($item['comment'] ?? '') }}')"><i class="fa-solid fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="16" class="text-center text-muted py-4">
                        <i class="fa-solid fa-tag" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No mangle rules found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('noc.security.mangle-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add Mangle Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Chain <span class="text-danger">*</span></label>
                    <select name="chain" class="form-select" required><option value="prerouting">prerouting</option><option value="input">input</option><option value="forward">forward</option><option value="output">output</option><option value="postrouting">postrouting</option></select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Action <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" required><option value="mark-packet">mark-packet</option><option value="mark-connection">mark-connection</option><option value="mark-routing">mark-routing</option><option value="passthrough">passthrough</option><option value="accept">accept</option><option value="drop">drop</option></select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Protocol</label>
                    <select name="protocol" class="form-select"><option value="">-- Any --</option><option value="tcp">tcp</option><option value="udp">udp</option><option value="icmp">icmp</option></select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Packet Marks</label><input type="text" name="new-packet-marks" class="form-control" placeholder="mark-1"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Connection Mark</label><input type="text" name="new-connection-mark" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Routing Mark</label><input type="text" name="new-routing-mark" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Source Address</label><input type="text" name="src-address" class="form-control" placeholder="192.168.1.0/24"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Destination Address</label><input type="text" name="dst-address" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Source Port</label><input type="text" name="src-port" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Destination Port</label><input type="text" name="dst-port" class="form-control" placeholder="80,443"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">In Interface</label><input type="text" name="in-interface" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Out Interface</label><input type="text" name="out-interface" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Passthrough</label>
                    <select name="passthrough" class="form-select"><option value="true">yes</option><option value="false">no</option></select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">DSCP</label><input type="text" name="dscp" class="form-control" placeholder="0-63"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">TTL</label><input type="text" name="ttl" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Comment <span class="text-danger">*</span></label><input type="text" name="comment" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Disabled</label>
                    <select name="disabled" class="form-select"><option value="false">No</option><option value="true">Yes</option></select>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Create</button></div>
    </form>
</div></div></div>

{{-- ═══ EDIT MODAL ═══ --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="editForm" action="">
        @csrf @method('PUT')
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit Mangle Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Chain</label><select name="chain" id="editChain" class="form-select"><option value="prerouting">prerouting</option><option value="input">input</option><option value="forward">forward</option><option value="output">output</option><option value="postrouting">postrouting</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Action</label><select name="action" id="editAction" class="form-select"><option value="mark-packet">mark-packet</option><option value="mark-connection">mark-connection</option><option value="mark-routing">mark-routing</option><option value="passthrough">passthrough</option><option value="accept">accept</option><option value="drop">drop</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Protocol</label><select name="protocol" id="editProtocol" class="form-select"><option value="">-- Any --</option><option value="tcp">tcp</option><option value="udp">udp</option><option value="icmp">icmp</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Packet Marks</label><input type="text" name="new-packet-marks" id="editNewPacketMarks" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Connection Mark</label><input type="text" name="new-connection-mark" id="editNewConnMark" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">New Routing Mark</label><input type="text" name="new-routing-mark" id="editNewRoutingMark" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Src Address</label><input type="text" name="src-address" id="editSrcAddress" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Dst Address</label><input type="text" name="dst-address" id="editDstAddress" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Src Port</label><input type="text" name="src-port" id="editSrcPort" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Dst Port</label><input type="text" name="dst-port" id="editDstPort" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">In Interface</label><input type="text" name="in-interface" id="editInInterface" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Out Interface</label><input type="text" name="out-interface" id="editOutInterface" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Passthrough</label><select name="passthrough" id="editPassthrough" class="form-select"><option value="true">yes</option><option value="false">no</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">DSCP</label><input type="text" name="dscp" id="editDscp" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">TTL</label><input type="text" name="ttl" id="editTtl" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Comment</label><input type="text" name="comment" id="editComment" class="form-control"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Disabled</label><select name="disabled" id="editDisabled" class="form-select"><option value="false">No</option><option value="true">Yes</option></select></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>
<form method="POST" id="toggleForm" style="display:none;">@csrf</form>
<form method="POST" id="copyForm" style="display:none;">@csrf</form>
<form method="POST" id="bulkForm" action="{{ route('noc.security.mangle-bulk') }}" style="display:none;">@csrf
    <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
    <input type="hidden" name="action" id="bulkActionInput" value="">
</form>
@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.security.mangle-update', ['itemId' => 'X']));
const ROUTE_DESTROY = @json(route('noc.security.mangle-destroy', ['itemId' => 'X']));
const ROUTE_TOGGLE = @json(route('noc.security.mangle-toggle', ['itemId' => 'X']));
const ROUTE_COPY = @json(route('noc.security.mangle-copy', ['itemId' => 'X']));

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const chain = document.getElementById('filterChain').value;
    const action = document.getElementById('filterAction').value;
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        let show = true;
        if (q && !row.dataset.search.includes(q)) show = false;
        if (chain && row.dataset.chain !== chain) show = false;
        if (action && row.dataset.action !== action) show = false;
        if (status && row.dataset.status !== status) show = false;
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
    document.getElementById('editChain').value = item['chain'] || 'prerouting';
    document.getElementById('editAction').value = item['action'] || 'mark-packet';
    document.getElementById('editProtocol').value = item['protocol'] || '';
    document.getElementById('editNewPacketMarks').value = item['new-packet-marks'] || '';
    document.getElementById('editNewConnMark').value = item['new-connection-mark'] || '';
    document.getElementById('editNewRoutingMark').value = item['new-routing-mark'] || '';
    document.getElementById('editSrcAddress').value = item['src-address'] || '';
    document.getElementById('editDstAddress').value = item['dst-address'] || '';
    document.getElementById('editSrcPort').value = item['src-port'] || '';
    document.getElementById('editDstPort').value = item['dst-port'] || '';
    document.getElementById('editInInterface').value = item['in-interface'] || '';
    document.getElementById('editOutInterface').value = item['out-interface'] || '';
    document.getElementById('editPassthrough').value = item['passthrough'] || 'true';
    document.getElementById('editDscp').value = item['dscp'] || '';
    document.getElementById('editTtl').value = item['ttl'] || '';
    document.getElementById('editComment').value = item['comment'] || '';
    document.getElementById('editDisabled').value = item['disabled'] || 'false';
}

function toggleItem(id, disable) { const f = document.getElementById('toggleForm'); f.action = ROUTE_TOGGLE.replace('X', encodeURIComponent(id)); f.innerHTML += '<input type="hidden" name="disable" value="'+disable+'">'; f.submit(); }
function copyItem(id) { const f = document.getElementById('copyForm'); f.action = ROUTE_COPY.replace('X', encodeURIComponent(id)); f.submit(); }
function deleteItem(id, name) { if (!confirm('Delete rule "'+name+'"?')) return; const f = document.getElementById('deleteForm'); f.action = ROUTE_DESTROY.replace('X', encodeURIComponent(id)); f.submit(); }

function bulkOp(action) {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!confirm(action.toUpperCase() + ' ' + checked.length + ' selected rules?')) return;
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkActionInput').value = action;
    checked.forEach(cb => { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'item_ids[]'; inp.value = cb.value; form.appendChild(inp); });
    form.submit();
}
</script>
@endpush
