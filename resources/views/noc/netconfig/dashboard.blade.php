@extends('layouts.app')

@section('title', 'Network Configuration Center — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>Network Configuration Center</h2>
        <p class="section-subtitle mb-0 mt-1">Bridge · VLAN · IP Address — Layer 2 & Layer 3 management</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.netconfig.audit-logs') }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-scroll me-1"></i>Audit Log
        </a>
    </div>
</div>

{{-- ═══ ROUTER FILTER ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Routers</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($routerId ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- ═══ RESOURCE CARDS ═══ --}}
<div class="row g-3 mb-4">
    @foreach($stats['resources'] as $key => $res)
    <div class="col-md-4">
        <a href="{{ route('noc.netconfig.index', ['resource' => $key, 'router_id' => $routerId ?? '']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $res['label'] }}</h6>
                            <small class="text-muted">RouterOS Configuration</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(var(--bs-primary-rgb),0.08);">
                            <i class="fa-solid fa-{{ match($key) { 'bridge' => 'layer-group', 'vlan' => 'tag', 'ip_address' => 'globe', default => 'cube' } }} fa-lg" style="color:var(--primary);"></i>
                        </div>
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="fw-bold fs-4" style="color:var(--primary);">{{ number_format($res['total']) }}</div>
                            <small class="text-muted">Total</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold fs-4" style="color:var(--bs-success);">{{ number_format($res['active']) }}</div>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- ═══ LAST SYNC + RECENT AUDIT ═══ --}}
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0"><i class="fa-solid fa-rotate me-1"></i> Last Synchronization</h6></div>
            <div class="card-body">
                @if($stats['last_sync_at'])
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fa-solid fa-check text-success"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">{{ $stats['last_sync_at']->diffForHumans() }}</div>
                        <small class="text-muted">{{ $stats['last_sync_at']->format('Y-m-d H:i:s') }}</small>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-3">
                    <i class="fa-solid fa-cloud-arrow-up fa-2x mb-2 d-block"></i>
                    No sync data yet. Run a sync from the Config Sync dashboard.
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-scroll me-1"></i> Recent Changes</h6>
                <a href="{{ route('noc.netconfig.audit-logs') }}" style="font-size:0.8rem;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr><th>Action</th><th>Resource</th><th>Item</th><th>User</th><th>When</th><th></th></tr>

                        <tbody>
                            @forelse($stats['recent_logs'] as $log)
                            <tr>
                                <td><span class="badge bg-{{ $log->action_badge }}">{{ $log->action }}</span></td>
                                <td><span class="badge bg-light text-dark">{{ $log->resource_type }}</span></td>
                                <td class="fw-semibold" style="max-width:200px;" title="{{ $log->item_id }}">{{ Str::limit($log->item_name, 30) }}</td>
                                <td>{{ $log->user->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $log->created_at->diffForHumans() }}</td>
                                <td>
                                    <span class="badge bg-{{ $log->status_badge }}">{{ $log->status }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No changes recorded yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

