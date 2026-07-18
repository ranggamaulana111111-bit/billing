@extends('layouts.app')

@section('title', 'Layer7 Protocols — Security Policy Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.security.dashboard', ['router_id' => $router->id ?? '']) }}">Security Policy Center</a></li>
                <li class="breadcrumb-item active">Layer7 Protocols</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-layer-group me-2" style="color:#06b6d4;"></i>Layer7 Protocols</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ count($items) }} protocols</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i>Add Protocol</button>
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
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search by name, regex, or comment..." onkeyup="filterTable()">
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
                        <th style="width:40px;">#</th>
                        <th>Name</th>
                        <th>Regex</th>
                        <th>Comment</th>
                        <th style="width:100px;"></th>
                    </tr>

                <tbody>
                    @forelse($items as $idx => $item)
                    <tr data-search="{{ strtolower(implode(' ', [$item['name'] ?? '', $item['regexp'] ?? '', $item['comment'] ?? ''])) }}">
                        <td class="text-muted">{{ (int) $idx + 1 }}</td>
                        <td class="fw-semibold" style="font-size:0.82rem;">{{ $item['name'] ?? '' }}</td>
                        <td><code style="font-size:0.72rem;word-break:break-all;">{{ Str::limit($item['regexp'] ?? '', 60) }}</code></td>
                        <td style="font-size:0.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $item['comment'] ?? '—' }}</td>
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
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fa-solid fa-layer-group" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No Layer7 protocols found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ CREATE MODAL ═══ --}}
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('noc.security.layer7-store') }}">
        @csrf
        <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
        <div class="modal-header"><h5 class="modal-title fw-bold">Add Layer7 Protocol</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="my-protocol">
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Comment</label>
                    <input type="text" name="comment" class="form-control">
                </div>
                <div class="col-12"><label class="form-label fw-semibold">Regexp <span class="text-danger">*</span></label>
                    <textarea name="regexp" class="form-control" rows="3" required placeholder="^https?://.*example\.com"></textarea>
                    <div class="form-text">Regular expression to match application layer traffic.</div>
                </div>
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
        <div class="modal-header"><h5 class="modal-title fw-bold">Edit Layer7 Protocol</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Name</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Comment</label>
                    <input type="text" name="comment" id="editComment" class="form-control">
                </div>
                <div class="col-12"><label class="form-label fw-semibold">Regexp</label>
                    <textarea name="regexp" id="editRegexp" class="form-control" rows="3" required></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Update</button></div>
    </form>
</div></div></div>

<form method="POST" id="deleteForm" style="display:none;">@csrf @method('DELETE')</form>
@endsection

@push('scripts')
<script>
const ROUTE_UPDATE = @json(route('noc.security.layer7-update', ['itemId' => 'X']));
const ROUTE_DESTROY = @json(route('noc.security.layer7-destroy', ['itemId' => 'X']));

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}

function prefillEdit(item) {
    document.getElementById('editForm').action = ROUTE_UPDATE.replace('X', encodeURIComponent(item['.id']));
    document.getElementById('editName').value = item['name'] || '';
    document.getElementById('editRegexp').value = item['regexp'] || '';
    document.getElementById('editComment').value = item['comment'] || '';
}

function deleteItem(id, name) { if (!confirm('Delete protocol "'+name+'"?')) return; const f = document.getElementById('deleteForm'); f.action = ROUTE_DESTROY.replace('X', encodeURIComponent(id)); f.submit(); }
</script>
@endpush

