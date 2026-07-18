@extends('layouts.app')

@section('title', 'QoS Policy — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">QoS Policy</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-sliders me-2" style="color:#10b981;"></i>QoS Policy Overview</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <form method="GET" class="d-inline">
            <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($routers as $r)
                <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#6366f1,#6366f1dd);border-radius:16px;min-height:100px;">
            <div class="card-body"><div class="stat-number">{{ count($policies['queues'] ?? []) }}</div><div class="stat-label">Simple Queues</div></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#8b5cf6,#8b5cf6dd);border-radius:16px;min-height:100px;">
            <div class="card-body"><div class="stat-number">{{ count($policies['queue_trees'] ?? []) }}</div><div class="stat-label">Queue Trees</div></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#06b6d4,#06b6d4dd);border-radius:16px;min-height:100px;">
            <div class="card-body"><div class="stat-number">{{ count($policies['interfaces'] ?? []) }}</div><div class="stat-label">Interfaces</div></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#f59e0b,#f59e0bdd);border-radius:16px;min-height:100px;">
            <div class="card-body"><div class="stat-number">{{ count($policies['ppp_profiles'] ?? []) + count($policies['hotspot_profiles'] ?? []) }}</div><div class="stat-label">PPPoE + Hotspot Profiles</div></div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- ═══ SIMPLE QUEUES BY INTERFACE ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-layer-group me-2" style="color:#6366f1;"></i>Simple Queues</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap" style="max-height:350px;">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Name</th><th>Target</th><th>Max Limit</th><th>Queue</th><th>Priority</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($policies['queues'] ?? [] as $q)
                            <tr>
                                <td class="fw-semibold">{{ $q['name'] ?? '' }}</td>
                                <td><code style="font-size:0.72rem;">{{ $q['target'] ?? '—' }}</code></td>
                                <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $q['max-limit'] ?? '—' }}</span></td>
                                <td style="font-size:0.78rem;">{{ $q['queue'] ?? 'default' }}</td>
                                <td><span class="badge bg-info" style="font-size:0.65rem;">{{ $q['priority'] ?? '1' }}</span></td>
                                <td><span class="badge bg-{{ ($q['disabled'] ?? 'false') === 'true' ? 'secondary' : 'success' }}" style="font-size:0.62rem;">{{ ($q['disabled'] ?? 'false') === 'true' ? 'Disabled' : 'Active' }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No queues</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ INTERFACES ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-network-wired me-2" style="color:#10b981;"></i>Interfaces</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap" style="max-height:350px;">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Name</th><th>Type</th><th>Rate</th><th>MTU</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($policies['interfaces'] ?? [] as $if)
                            <tr>
                                <td class="fw-semibold">{{ $if['name'] ?? '' }}</td>
                                <td><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $if['type'] ?? '' }}</span></td>
                                <td style="font-size:0.78rem;">{{ $if['rate'] ?? '0' }}</td>
                                <td style="font-size:0.78rem;">{{ $if['mtu'] ?? '—' }}</td>
                                <td><span class="badge bg-{{ ($if['disabled'] ?? 'false') === 'true' ? 'secondary' : (($if['running'] ?? 'false') === 'true' ? 'success' : 'warning') }}" style="font-size:0.62rem;">{{ ($if['running'] ?? 'false') === 'true' ? 'Running' : (($if['disabled'] ?? 'false') === 'true' ? 'Disabled' : 'Down') }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No interfaces</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ PPP PROFILES ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-user-check me-2" style="color:#8b5cf6;"></i>PPPoE Profiles</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap" style="max-height:300px;">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Name</th><th>Local Address</th><th>Remote Address</th><th>Rate Limit</th></tr></thead>
                        <tbody>
                            @forelse($policies['ppp_profiles'] ?? [] as $p)
                            <tr>
                                <td class="fw-semibold">{{ $p['name'] ?? '' }}</td>
                                <td style="font-size:0.78rem;">{{ $p['local-address'] ?? '—' }}</td>
                                <td style="font-size:0.78rem;">{{ $p['remote-address'] ?? '—' }}</td>
                                <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $p['rate-limit'] ?? '—' }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No PPPoE profiles</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ HOTSPOT PROFILES ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-wifi me-2" style="color:#f59e0b;"></i>Hotspot Profiles</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap" style="max-height:300px;">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Name</th><th>Address Pool</th><th>Rate Limit</th><th>Auth</th></tr></thead>
                        <tbody>
                            @forelse($policies['hotspot_profiles'] ?? [] as $hp)
                            <tr>
                                <td class="fw-semibold">{{ $hp['name'] ?? '' }}</td>
                                <td style="font-size:0.78rem;">{{ $hp['address-pool'] ?? '—' }}</td>
                                <td><span class="badge bg-warning text-dark" style="font-size:0.65rem;">{{ $hp['rate-limit'] ?? '—' }}</span></td>
                                <td style="font-size:0.78rem;">{{ $hp['login-by'] ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No hotspot profiles</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ QUEUE TREE ═══ --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-sitemap me-2" style="color:#8b5cf6;"></i>Queue Tree Policies</h6></div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
<thead class="mon-thead">
    <tr><th>#</th><th>Name</th><th>Parent</th><th>Packet Mark</th><th>Queue</th><th>Max Limit</th><th>Priority</th><th>Rate</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($policies['queue_trees'] ?? [] as (int) $idx => $t)
                    @php $disabled = ($t['disabled'] ?? 'false') === 'true'; @endphp
                    <tr>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td class="fw-semibold">{{ $t['name'] ?? '' }}</td>
                        <td><code style="font-size:0.72rem;">{{ $t['parent'] ?? '—' }}</code></td>
                        <td><span class="badge bg-warning text-dark" style="font-size:0.65rem;">{{ $t['packet-mark'] ?? '—' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $t['queue'] ?? 'default' }}</td>
                        <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $t['max-limit'] ?? '—' }}</span></td>
                        <td><span class="badge bg-info" style="font-size:0.65rem;">{{ $t['priority'] ?? '1' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $t['rate'] ?? '0' }}</td>
                        <td><span class="badge bg-{{ $disabled ? 'secondary' : 'success' }}" style="font-size:0.62rem;">{{ $disabled ? 'Disabled' : 'Active' }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-3">No queue trees</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

