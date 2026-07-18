@extends('layouts.app')

@section('title', 'Internet Service Center — Dashboard')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-globe me-2" style="color:var(--primary);"></i>Internet Service Center</h2>
        <p class="section-subtitle mb-0 mt-1">DHCP · PPPoE · Hotspot — Layanan internet & manajemen IP</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if($stats['last_sync_at'])
        <span class="text-muted align-self-center" style="font-size:0.78rem;">
            <i class="fa-solid fa-clock me-1"></i>Sync: {{ $stats['last_sync_at']->diffForHumans() }}
        </span>
        @endif
        @if(($stats['low_pool_count'] ?? 0) > 0)
        <span class="badge bg-danger d-flex align-items-center" style="font-size:0.72rem;">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $stats['low_pool_count'] }} Pool Hampir Habis
        </span>
        @endif
        @if(($stats['conflict_count'] ?? 0) > 0)
        <span class="badge bg-warning text-dark d-flex align-items-center" style="font-size:0.72rem;">
            <i class="fa-solid fa-shield-halved me-1"></i>{{ $stats['conflict_count'] }} Konflik IP
        </span>
        @endif
    </div>
</div>

@if(!$router)
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>
        <strong>No active router found.</strong> Please add and configure a MikroTik router first.
    </div>
</div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
@if($routers->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $routerId == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif

@if($router)
{{-- ═══ ROW 1: PRIMARY STATS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);min-height:130px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-server"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-server"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['total_dhcp_servers'] }}</div>
                        <div class="stat-label">Total DHCP Servers</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#10b981,#059669);min-height:130px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-list-check"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format($stats['total_leases']) }}</div>
                        <div class="stat-label">Active Leases</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);min-height:130px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-link"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-link"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format($stats['ppp_online']) }}</div>
                        <div class="stat-label">PPPoE Online</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);min-height:130px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-wifi"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-wifi"></i></div>
                    <div>
                        <div class="stat-number">{{ number_format($stats['hotspot_active']) }}</div>
                        <div class="stat-label">Hotspot Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ROW 2: SECONDARY STATS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">IP Pools</small>
                <h4 class="fw-bold mb-1" style="color:var(--primary);">{{ number_format($stats['total_pools']) }}</h4>
                <small class="text-muted">DHCP Pool aktif</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Total Sessions</small>
                <h4 class="fw-bold mb-1 text-info">{{ number_format($stats['total_sessions']) }}</h4>
                <small class="text-muted">PPPoE + Hotspot</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">PPPoE Offline</small>
                <h4 class="fw-bold mb-1 text-danger">{{ number_format($stats['ppp_offline']) }}</h4>
                <small class="text-muted">Tidak terkoneksi</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Total PPP Secrets</small>
                <h4 class="fw-bold mb-1 text-secondary">{{ number_format($stats['total_ppp_secrets']) }}</h4>
                <small class="text-muted">Akun PPPoE terdaftar</small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ POOL USAGE ═══ --}}
<div class="bento-card mb-4">
    <div class="bento-accent bento-accent-blue"></div>
    <div class="bento-card-header">
        <div class="bento-title">
            <span class="dot" style="background:var(--primary);"></span>
            Pool Usage
        </div>
    </div>
    <div class="bento-card-body" style="padding:12px 16px 16px;">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Name</th>
                        <th>Ranges</th>
                        <th class="text-center">Total IPs</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Free</th>
                        <th style="min-width:180px;">Usage %</th>
                    </tr>

                <tbody>
                    @forelse($poolUsage as $pool)
                    <tr>
                        <td class="fw-semibold">{{ $pool['name'] }}</td>
                        <td style="font-size:0.76rem;">
                            @foreach(explode("\n", $pool['ranges']) as $range)
                                @if(trim($range) !== '')
                                <code>{{ trim($range) }}</code>@if(!$loop->last)<br>@endif
                                @endif
                            @endforeach
                        </td>
                        <td class="text-center">{{ number_format($pool['total_ips']) }}</td>
                        <td class="text-center fw-semibold">{{ number_format($pool['total_used']) }}</td>
                        <td class="text-center">{{ number_format($pool['free']) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @php
                                    $pct = $pool['percent'];
                                    $barClass = $pct > 95 ? 'bg-danger' : ($pct > 85 ? 'bg-warning' : 'bg-success');
                                @endphp
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="fw-semibold" style="font-size:0.78rem;min-width:40px;">{{ number_format($pct, 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-database" style="font-size:1.5rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada IP pool ditemukan</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ DHCP SERVERS ═══ --}}
<div class="bento-card mb-4">
    <div class="bento-accent bento-accent-green"></div>
    <div class="bento-card-header">
        <div class="bento-title">
            <span class="dot" style="background:#059669;"></span>
            DHCP Servers
        </div>
    </div>
    <div class="bento-card-body" style="padding:12px 16px 16px;">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Server Name</th>
                        <th>Interface</th>
                        <th class="text-center">Lease Count</th>
                    </tr>

                <tbody>
                    @forelse($stats['dhcp_leases_per_server'] as $server)
                    <tr>
                        <td class="fw-semibold">{{ $server['name'] }}</td>
                        <td><span class="badge bg-light text-dark">{{ $server['interface'] }}</span></td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ $server['lease_count'] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            <i class="fa-solid fa-server" style="font-size:1.5rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada DHCP server ditemukan</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ QUICK LINKS ═══ --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold" style="font-size:1rem;"><i class="fa-solid fa-bolt me-1" style="color:var(--primary);"></i> Quick Links</h5>
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.ippool', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(37,99,235,0.1);">
                            <i class="fa-solid fa-layer-group" style="color:#2563eb;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">IP Pool Manager</div>
                            <small class="text-muted">Kelola DHCP pools</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.dhcp', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(16,185,129,0.1);">
                            <i class="fa-solid fa-server" style="color:#10b981;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">DHCP Server</div>
                            <small class="text-muted">Konfigurasi DHCP</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.dhcplease', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(139,92,246,0.1);">
                            <i class="fa-solid fa-list-check" style="color:#8b5cf6;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">DHCP Lease</div>
                            <small class="text-muted">Daftar lease aktif</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.ppp-profile', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(245,158,11,0.1);">
                            <i class="fa-solid fa-sliders" style="color:#f59e0b;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">PPP Profile</div>
                            <small class="text-muted">Profile PPPoE</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.ppp-secret', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(236,72,153,0.1);">
                            <i class="fa-solid fa-key" style="color:#ec4899;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">PPP Secret</div>
                            <small class="text-muted">Akun PPPoE</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.hotspot-server', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(6,182,212,0.1);">
                            <i class="fa-solid fa-wifi" style="color:#06b6d4;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Hotspot Server</div>
                            <small class="text-muted">Konfigurasi hotspot</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.hotspot-user', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(99,102,241,0.1);">
                            <i class="fa-solid fa-users" style="color:#6366f1;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Hotspot User</div>
                            <small class="text-muted">Pengguna hotspot</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.active-sessions', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(14,165,233,0.1);">
                            <i class="fa-solid fa-signal" style="color:#0ea5e9;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Active Sessions</div>
                            <small class="text-muted">Sesi aktif saat ini</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.audit-log', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(100,116,139,0.1);">
                            <i class="fa-solid fa-scroll" style="color:#64748b;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Audit Log</div>
                            <small class="text-muted">Riwayat perubahan</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.monitoring', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(239,68,68,0.1);">
                            <i class="fa-solid fa-heart-pulse" style="color:#ef4444;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Monitoring Center</div>
                            <small class="text-muted">Status realtime router</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.conflicts', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(245,158,11,0.1);">
                            <i class="fa-solid fa-shield-halved" style="color:#f59e0b;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">IP Conflicts</div>
                            <small class="text-muted">Deteksi konflik IP</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="{{ route('noc.internet.host', ['router_id' => $routerId]) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(139,92,246,0.1);">
                            <i class="fa-solid fa-desktop" style="color:#8b5cf6;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.88rem;">Hotspot Hosts</div>
                            <small class="text-muted">Host terdeteksi</small>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- ═══ ALERTS: POOL WARNINGS ═══ --}}
@if(!empty($stats['pool_warnings']))
<div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #f59e0b !important;">
    <div class="card-header bg-transparent d-flex align-items-center">
        <i class="fa-solid fa-triangle-exclamation me-2" style="color:#f59e0b;"></i>
        <h6 class="mb-0 fw-bold">Pool Hampir Habis</h6>
        <span class="badge bg-warning text-dark ms-2" style="font-size:0.68rem;">{{ count($stats['pool_warnings']) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Pool Name</th>
                        <th class="text-center">Total IPs</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Free</th>
                        <th style="min-width:160px;">Usage</th>
                        <th class="text-center">Status</th>
                    </tr>

                <tbody>
                    @foreach($stats['pool_warnings'] as $pw)
                    <tr>
                        <td class="fw-semibold">{{ $pw['name'] }}</td>
                        <td class="text-center">{{ number_format($pw['total_ips']) }}</td>
                        <td class="text-center fw-semibold">{{ number_format($pw['total_used']) }}</td>
                        <td class="text-center">{{ number_format($pw['free']) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:6px;">
                                    <div class="progress-bar {{ $pw['critical'] ? 'bg-danger' : 'bg-warning' }}" style="width:{{ $pw['percent'] }}%"></div>
                                </div>
                                <span class="fw-semibold" style="font-size:0.78rem;">{{ number_format($pw['percent'], 1) }}%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            @if($pw['critical'])
                                <span class="badge bg-danger" style="font-size:0.62rem;">CRITICAL</span>
                            @else
                                <span class="badge bg-warning text-dark" style="font-size:0.62rem;">LOW</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ═══ ALERTS: IP CONFLICTS ═══ --}}
@if(!empty($stats['conflicts']))
<div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #dc3545 !important;">
    <div class="card-header bg-transparent d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2" style="color:#dc3545;"></i>
        <h6 class="mb-0 fw-bold">Konflik IP Terdeteksi</h6>
        <span class="badge bg-danger ms-2" style="font-size:0.68rem;">{{ $stats['conflict_count'] }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>IP Address</th>
                        <th>Entry 1</th>
                        <th>Entry 2</th>
                        <th>Type</th>
                    </tr>

                <tbody>
                    @foreach(array_slice($stats['conflicts'], 0, 10) as $conflict)
                    <tr>
                        <td><code class="fw-bold text-danger">{{ $conflict['ip'] }}</code></td>
                        <td>
                            @if(isset($conflict['entries'][0]))
                            <span class="badge bg-{{ $conflict['entries'][0]['type'] === 'dhcp' ? 'primary' : ($conflict['entries'][0]['type'] === 'ppp' ? 'info' : 'secondary') }}" style="font-size:0.65rem;">{{ strtoupper($conflict['entries'][0]['type']) }}</span>
                            {{ $conflict['entries'][0]['owner'] }}
                            <code style="font-size:0.7rem;">{{ $conflict['entries'][0]['mac'] }}</code>
                            @endif
                        </td>
                        <td>
                            @if(isset($conflict['entries'][1]))
                            <span class="badge bg-{{ $conflict['entries'][1]['type'] === 'dhcp' ? 'primary' : ($conflict['entries'][1]['type'] === 'ppp' ? 'info' : 'secondary') }}" style="font-size:0.65rem;">{{ strtoupper($conflict['entries'][1]['type']) }}</span>
                            {{ $conflict['entries'][1]['owner'] }}
                            <code style="font-size:0.7rem;">{{ $conflict['entries'][1]['mac'] }}</code>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-danger" style="font-size:0.62rem;">CONFLICT</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($stats['conflict_count'] > 10)
        <div class="text-center py-2">
            <a href="{{ route('noc.internet.conflicts', ['router_id' => $routerId]) }}" style="font-size:0.82rem;">Lihat semua {{ $stats['conflict_count'] }} konflik &rarr;</a>
        </div>
        @endif
    </div>
</div>
@endif
@endif
@endsection
