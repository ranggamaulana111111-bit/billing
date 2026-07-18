@extends('layouts.app')

@section('title', 'Security Audit Logs — Security Policy Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.security.dashboard', ['router_id' => request('router_id')]) }}">Security Policy Center</a></li>
                <li class="breadcrumb-item active">Security Audit Logs</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-clipboard-list me-2" style="color:#6b7280;"></i>Security Audit Logs</h2>
    </div>
</div>

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
                    <option value="">All Routers</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ request('router_id') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Resource Type</label>
                <select name="resource_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['raw' => 'Raw Firewall', 'filter' => 'Firewall Filter', 'layer7' => 'Layer7', 'nat' => 'NAT', 'mangle' => 'Mangle', 'address-list' => 'Address List'] as $k => $v)
                    <option value="{{ $k }}" {{ request('resource_type') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Action</label>
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['create','update','delete','enable','disable','move','copy'] as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search logs..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                <a href="{{ route('noc.security.audit') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                        <th style="font-size:0.75rem;">Time</th>
                        <th style="font-size:0.75rem;">Router</th>
                        <th style="font-size:0.75rem;">Resource</th>
                        <th style="font-size:0.75rem;">Action</th>
                        <th style="font-size:0.75rem;">User</th>
                        <th style="font-size:0.75rem;">Status</th>
                        <th style="font-size:0.75rem;">Summary</th>
                        <th style="font-size:0.75rem;">Error</th>
                    </tr>

                <tbody>
                    @php $actionBadges = ['create'=>'success','update'=>'primary','delete'=>'danger','enable'=>'success','disable'=>'warning','move'=>'info','copy'=>'secondary']; @endphp
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted" style="white-space:nowrap;font-size:0.78rem;">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td style="font-size:0.8rem;">{{ $log->router->display_identity ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ str_replace('security_policy.', '', $log->resource_type) }}</span></td>
                        <td><span class="badge bg-{{ $actionBadges[$log->action] ?? 'secondary' }}" style="font-size:0.65rem;">{{ ucfirst($log->action) }}</span></td>
                        <td style="font-size:0.8rem;">{{ $log->user->name ?? 'System' }}</td>
                        <td><span class="badge bg-{{ $log->status_badge ?? ($log->status === 'success' ? 'success' : 'danger') }}" style="font-size:0.65rem;">{{ ucfirst($log->status) }}</span></td>
                        <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.78rem;" title="{{ $log->summary ?? '' }}">{{ Str::limit($log->summary ?? '—', 60) }}</td>
                        <td style="font-size:0.78rem;">
                            @if($log->api_error)
                                <span class="text-danger" title="{{ $log->api_error }}"><i class="fa-solid fa-circle-exclamation"></i> {{ Str::limit($log->api_error, 30) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="fa-solid fa-clipboard-list" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No security audit logs found</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->count() > 0)
    <div class="card-footer bg-transparent border-0 text-muted" style="font-size:0.8rem;">
        <i class="fa-solid fa-info-circle me-1"></i>Showing {{ $logs->count() }} of {{ $logs->count() }} logs (max 30 per query)
    </div>
    @endif
</div>
@endsection

