@extends('layouts.app')

@section('title', 'Network Config Audit Log — NOC')

@section('content')
<div class="page-header mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
            <li class="breadcrumb-item"><a href="{{ route('noc.netconfig.dashboard') }}">Network Config</a></li>
            <li class="breadcrumb-item active">Audit Log</li>
        </ol>
    </nav>
    <h2 class="mb-0"><i class="fa-solid fa-scroll me-2" style="color:var(--primary);"></i>Network Config Audit Log</h2>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Resource</label>
                <select name="resource_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['bridge' => 'Bridge', 'vlan' => 'VLAN', 'ip_address' => 'IP Address'] as $k => $v)
                    <option value="{{ $k }}" {{ request('resource_type') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ request('router_id') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Action</label>
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['create','update','delete','enable','disable'] as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr><th>Time</th><th>Resource</th><th>Action</th><th>Item</th><th>Summary</th><th>User</th><th>Router</th><th>Status</th></tr>

                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted" style="white-space:nowrap;">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td><span class="badge bg-light text-dark">{{ $log->resource_type }}</span></td>
                        <td><span class="badge bg-{{ $log->action_badge }}">{{ $log->action }}</span></td>
                        <td class="fw-semibold" style="max-width:200px;" title="{{ $log->item_id }}">{{ Str::limit($log->item_name, 30) }}</td>
                        <td style="max-width:250px;">{{ Str::limit($log->summary ?? '—', 60) }}</td>
                        <td>{{ $log->user->name ?? 'System' }}</td>
                        <td>{{ $log->router->display_identity ?? 'N/A' }}</td>
                        <td><span class="badge bg-{{ $log->status_badge }}">{{ $log->status }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No audit logs found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $logs->withQueryString()->links() }}</div>
    @endif
</div>
@endsection

