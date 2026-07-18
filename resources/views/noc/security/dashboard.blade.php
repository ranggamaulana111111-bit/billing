@extends('layouts.app')

@section('title', 'Security Policy Center — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">NOC Control Center</a></li>
                <li class="breadcrumb-item active">Security Policy Center</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-shield-halved me-2" style="color:#ef4444;"></i>Security Policy Center</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }}</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="GET" class="d-inline">
            <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($routers as $r)
                <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;">
        <i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ═══ STAT CARDS ═══ --}}
<div class="bento-grid mb-4">
    @php $colors = ['firewall_filter' => '#ef4444', 'firewall_nat' => '#f59e0b', 'mangle' => '#8b5cf6', 'address_list' => '#10b981', 'raw' => '#6366f1', 'layer7' => '#06b6d4']; @endphp
    @foreach($stats['stats'] ?? [] as $key => $s)
    <div class="span-1">
        <a href="{{ route('noc.security.'.$key === 'firewall_filter' ? 'firewall-filter' : ($key === 'firewall_nat' ? 'nat' : $key), ['router_id' => $router->id ?? '']) }}" class="text-decoration-none">
            <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,{{ $colors[$key] ?? '#6b7280' }},{{ $colors[$key] ?? '#6b7280' }}dd);min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
                <div class="stat-bg"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-number">{{ $s['total'] }}</div>
                            <div class="stat-label">{{ $s['label'] }}</div>
                        </div>
                        @if($s['disabled'] > 0)
                        <span class="badge bg-dark bg-opacity-50" style="font-size:0.65rem;">{{ $s['disabled'] }} disabled</span>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

<div class="row g-4">
    {{-- ═══ QUICK LINKS ═══ --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-link me-2"></i>Quick Access</h6></div>
            <div class="card-body" style="font-size:0.82rem;">
                @php $links = [
                    ['route' => 'firewall-filter', 'icon' => 'fa-filter', 'label' => 'Firewall Filter', 'color' => '#ef4444'],
                    ['route' => 'nat', 'icon' => 'fa-arrows-split-up-and-left', 'label' => 'NAT Rules', 'color' => '#f59e0b'],
                    ['route' => 'mangle', 'icon' => 'fa-tag', 'label' => 'Mangle Rules', 'color' => '#8b5cf6'],
                    ['route' => 'address-list', 'icon' => 'fa-address-book', 'label' => 'Address List', 'color' => '#10b981'],
                    ['route' => 'raw', 'icon' => 'fa-fire', 'label' => 'Raw Firewall', 'color' => '#6366f1'],
                    ['route' => 'layer7', 'icon' => 'fa-layer-group', 'label' => 'Layer7 Protocol', 'color' => '#06b6d4'],
                    ['route' => 'audit', 'icon' => 'fa-clipboard-list', 'label' => 'Audit Logs', 'color' => '#6b7280'],
                ]; @endphp
                @foreach($links as $link)
                <a href="{{ route('noc.security.'.$link['route'], ['router_id' => $router->id ?? '']) }}" class="d-flex align-items-center mb-2 text-decoration-none py-1 rounded px-2 hover-bg-light">
                    <i class="fa-solid {{ $link['icon'] }} me-2" style="color:{{ $link['color'] }};width:18px;"></i>
                    <span>{{ $link['label'] }}</span>
                    <i class="fa-solid fa-chevron-right ms-auto" style="font-size:0.65rem;color:#ccc;"></i>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══ POLICY VALIDATION ═══ --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-triangle-exclamation me-2" style="color:#f59e0b;"></i>Policy Validation & Recommendations</h6>
                <span class="badge {{ count($recommendations) > 0 ? 'bg-warning text-dark' : 'bg-success' }}" style="font-size:0.7rem;">{{ count($recommendations) }} issues</span>
            </div>
            <div class="card-body p-0">
                @if(count($recommendations) > 0)
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr>
                                <th>Type</th>
                                <th>Resource</th>
                                <th>Recommendation</th>
                                <th class="text-center">Severity</th>
                            </tr>

                        <tbody>
                            @foreach($recommendations as $rec)
                            <tr>
                                <td><span class="badge bg-{{ $rec['type'] === 'duplicate' ? 'danger' : ($rec['type'] === 'unused' ? 'warning text-dark' : 'info') }}" style="font-size:0.62rem;">{{ strtoupper($rec['type']) }}</span></td>
                                <td>{{ $rec['resource'] }}</td>
                                <td>{{ $rec['message'] }}</td>
                                <td class="text-center"><span class="badge bg-{{ $rec['severity'] === 'warning' ? 'danger' : ($rec['severity'] === 'info' ? 'secondary' : 'warning') }}" style="font-size:0.6rem;">{{ strtoupper($rec['severity']) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fa-solid fa-shield-check" style="font-size:2.5rem;color:#10b981;"></i>
                    <p class="mt-2 mb-0 fw-semibold" style="color:#10b981;">All policies look good!</p>
                    <small class="text-muted">No issues detected in current firewall configuration.</small>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

<style>.hover-bg-light:hover{background:rgba(0,0,0,0.03);}</style>

