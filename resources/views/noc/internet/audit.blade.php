@extends('layouts.app')

@section('title', 'Audit Log — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard') }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Audit Log</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-clipboard-list me-2" style="color:var(--primary);"></i>Audit Log
        </h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('noc.internet.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm">
                    <option value="">All Routers</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}" {{ request('router_id') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Resource</label>
                <select name="resource_type" class="form-select form-select-sm">
                    <option value="">All Resources</option>
                    @foreach($resourceDefs as $key => $def)
                        <option value="{{ $key }}" {{ request('resource_type') == $key ? 'selected' : '' }}>{{ $def['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <option value="create" {{ request('action') == 'create' ? 'selected' : '' }}>Create</option>
                    <option value="update" {{ request('action') == 'update' ? 'selected' : '' }}>Update</option>
                    <option value="delete" {{ request('action') == 'delete' ? 'selected' : '' }}>Delete</option>
                    <option value="enable" {{ request('action') == 'enable' ? 'selected' : '' }}>Enable</option>
                    <option value="disable" {{ request('action') == 'disable' ? 'selected' : '' }}>Disable</option>
                    <option value="bulk" {{ request('action') == 'bulk' ? 'selected' : '' }}>Bulk</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                <a href="{{ route('noc.internet.audit') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.75rem;">Time</th>
                        <th style="font-size:0.75rem;">Router</th>
                        <th style="font-size:0.75rem;">Resource</th>
                        <th style="font-size:0.75rem;">Item</th>
                        <th style="font-size:0.75rem;">Action</th>
                        <th style="font-size:0.75rem;">Status</th>
                        <th style="font-size:0.75rem;">User</th>
                        <th style="font-size:0.75rem;" class="text-end">Detail</th>
                    </tr>

                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="font-size:0.8rem;">{{ $log->created_at->diffForHumans() }}</td>
                        <td style="font-size:0.8rem;">{{ $log->router->display_identity ?? '-' }}</td>
                        <td><span class="badge bg-info" style="font-size:0.68rem;">{{ str_replace('internet_service.', '', $log->resource_type) }}</span></td>
                        <td style="font-size:0.82rem;">{{ $log->item_name ?: $log->item_id ?: '-' }}</td>
                        <td><span class="badge bg-{{ $log->action_badge }}" style="font-size:0.68rem;">{{ ucfirst($log->action) }}</span></td>
                        <td><span class="badge bg-{{ $log->status_badge }}" style="font-size:0.68rem;">{{ ucfirst($log->status) }}</span></td>
                        <td style="font-size:0.8rem;">{{ $log->user->name ?? '-' }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal{{ $log->id }}">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>

                    <div class="modal fade" id="detailModal{{ $log->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title fw-bold">Audit Detail</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2" style="font-size:0.82rem;"><strong>Summary:</strong> {{ $log->summary }}</div>
                                    <div class="mb-2" style="font-size:0.82rem;"><strong>Time:</strong> {{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                                    <div class="mb-2" style="font-size:0.82rem;"><strong>User:</strong> {{ $log->user->name ?? 'System' }}</div>
                                    @if($log->api_error)
                                    <div class="mb-2" style="font-size:0.82rem;"><strong>Error:</strong> <span class="text-danger">{{ $log->api_error }}</span></div>
                                    @endif
                                    @if($log->before_data)
                                    <div class="mb-3">
                                        <strong style="font-size:0.82rem;">Before:</strong>
                                        <pre class="mt-1 p-2 rounded" style="background:var(--bs-body-bg);border:1px solid var(--bs-border-color);font-size:0.72rem;max-height:200px;overflow:auto;">{{ json_encode($log->before_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    @endif
                                    @if($log->after_data)
                                    <div class="mb-3">
                                        <strong style="font-size:0.82rem;">After:</strong>
                                        <pre class="mt-1 p-2 rounded" style="background:var(--bs-body-bg);border:1px solid var(--bs-border-color);font-size:0.72rem;max-height:200px;overflow:auto;">{{ json_encode($log->after_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fa-solid fa-clipboard-list fa-2x mb-2 d-block opacity-25"></i>
                            No audit logs found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-transparent">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection

