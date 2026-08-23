@extends('layouts.app')

@section('title', 'NOC Dashboard')

@push('styles')
<style>
    .noc-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        border-radius: 16px;
        padding: 24px 28px;
        color: #fff;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }
    .noc-hero h2 { font-weight: 800; margin: 0; font-size: 1.5rem; }
    .noc-hero .noc-hero-sub { color: rgba(255,255,255,0.55); font-size: 0.82rem; margin-top: 2px; }
    .noc-hero .noc-live {
        display: inline-flex; align-items: center; gap: 8px; font-size: 0.78rem;
        color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.08);
        padding: 6px 12px; border-radius: 999px;
    }
    .noc-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .noc-dot.ping { animation: nocPing 1.4s ease-out infinite; }
    @keyframes nocPing {
        0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.6); }
        70% { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
        100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
    }
    .noc-card .card-body { padding: 18px 20px; }
    .noc-stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
    }
    .noc-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; letter-spacing: 0.03em; }
    .noc-stat-value { font-size: 1.55rem; font-weight: 800; line-height: 1.1; }
    .noc-status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;
    }
</style>
@endpush

@section('content')
@php
    $greeting = match(true) {
        now()->hour < 6  => 'Selamat Malam',
        now()->hour < 12 => 'Selamat Pagi',
        now()->hour < 17 => 'Selamat Siang',
        now()->hour < 21 => 'Selamat Sore',
        default          => 'Selamat Malam',
    };
    $fmtRate = function ($bps) {
        if ($bps >= 1e9) return number_format($bps / 1e9, 2).' Gbps';
        if ($bps >= 1e6) return number_format($bps / 1e6, 1).' Mbps';
        if ($bps >= 1e3) return number_format($bps / 1e3, 1).' Kbps';
        return (int) $bps.' bps';
    };
@endphp

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- ═══ HERO ═══ --}}
<div class="noc-hero mb-4">
    <div>
        <h2><i class="fa-solid fa-satellite-dish me-2" style="color:#60a5fa;"></i>{{ $greeting }}, {{ Auth::user()->name }}</h2>
        <div class="noc-hero-sub">NOC Control Center &middot; {{ now()->format('l, d M Y H:i') }} WIB</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="noc-live"><span class="noc-dot ping" style="background:#22c55e;"></span> Real-time Monitoring Aktif</span>
        <a href="{{ route('noc.features.map') }}" class="btn btn-sm btn-light"><i class="fa-solid fa-map-location-dot me-1"></i>Panel FTTH</a>
        <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()"><i class="fa-solid fa-arrows-rotate me-1"></i>Refresh</button>
    </div>
</div>

{{-- ═══ STATUS CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4 col-6">
        <div class="card border-0 shadow-sm noc-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="noc-stat-icon" style="background:rgba(16,185,129,0.12);color:#10b981;"><i class="fa-solid fa-network-wired"></i></div>
                <div>
                    <div class="noc-stat-label">ROUTER ONLINE</div>
                    <div class="noc-stat-value" style="color:#10b981;">{{ $routerOnline }}<small style="font-size:0.7rem;color:var(--text-muted);">/{{ $routers->count() }}</small></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="card border-0 shadow-sm noc-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="noc-stat-icon" style="background:rgba(16,185,129,0.12);color:#10b981;"><i class="fa-solid fa-tower-broadcast"></i></div>
                <div>
                    <div class="noc-stat-label">OLT ONLINE</div>
                    <div class="noc-stat-value" style="color:#10b981;">{{ $oltOnline }}<small style="font-size:0.7rem;color:var(--text-muted);">/{{ $olts->count() }}</small></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="card border-0 shadow-sm noc-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="noc-stat-icon" style="background:rgba(59,130,246,0.12);color:#3b82f6;"><i class="fa-solid fa-wifi"></i></div>
                <div>
                    <div class="noc-stat-label">ONU ONLINE</div>
                    <div class="noc-stat-value" style="color:#3b82f6;">{{ $onuOnline }}<small style="font-size:0.7rem;color:var(--text-muted);">/{{ $onuTotal }}</small></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ NETWORK STATUS ═══ --}}
<div class="row g-3 mb-4">
    {{-- ROUTER --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3 pb-0">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-server me-2" style="color:var(--primary);"></i>Router MikroTik</h6>
                <span class="badge bg-light text-dark">{{ $routers->count() }} router</span>
            </div>
            <div class="card-body px-3">
                @forelse($routers as $r)
                @php
                    $online = $r->status === 'online';
                    $s = $r->user_stats ?? [];
                    $pp = (int) ($s['pppoe_online'] ?? 0);
                    $hs = (int) ($s['hotspot_online'] ?? 0);
                @endphp
                <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:rgba(0,0,0,0.05) !important;">
                    <span class="noc-dot ping" style="background:{{ $online ? '#22c55e' : '#ef4444' }};"></span>
                    <div style="flex:1;min-width:0;">
                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $r->name }}</div>
                        <div class="text-muted" style="font-size:0.7rem;">{{ $r->host ?: $r->local_ip }}</div>
                    </div>
                    <div class="text-center" style="min-width:70px;">
                        <div style="font-size:0.62rem;color:var(--text-muted);">PPPoE</div>
                        <div class="fw-bold" style="font-size:0.85rem;">{{ $pp }}</div>
                    </div>
                    <div class="text-center" style="min-width:70px;">
                        <div style="font-size:0.62rem;color:var(--text-muted);">Hotspot</div>
                        <div class="fw-bold" style="font-size:0.85rem;">{{ $hs }}</div>
                    </div>
                    <div class="text-end" style="min-width:90px;">
                        <span class="noc-status-badge" style="background:{{ $online ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)' }};color:{{ $online ? '#059669' : '#dc2626' }};">
                            <i class="fa-solid fa-circle" style="font-size:0.4rem;"></i>{{ $online ? 'Online' : 'Offline' }}
                        </span>
                        <div class="text-muted" style="font-size:0.62rem;">{{ $r->last_connected ? $r->last_connected->diffForHumans() : 'Belum pernah' }}</div>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-4 mb-0">Belum ada router MikroTik aktif.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- OLT --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3 pb-0">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-tower-broadcast me-2" style="color:var(--primary);"></i>OLT</h6>
                <span class="badge bg-light text-dark">{{ $olts->count() }} OLT</span>
            </div>
            <div class="card-body px-3">
                @forelse($olts as $o)
                @php
                    $st = $o->connection_status;
                    $color = match ($st) { 'online' => '#22c55e', 'offline' => '#ef4444', 'degraded' => '#f59e0b', default => '#94a3b8' };
                    $label = match ($st) { 'online' => 'Online', 'offline' => 'Offline', 'degraded' => 'Degraded', default => 'Unknown' };
                @endphp
                <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:rgba(0,0,0,0.05) !important;">
                    <span class="noc-dot ping" style="background:{{ $color }};"></span>
                    <div style="flex:1;min-width:0;">
                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $o->name }}</div>
                        <div class="text-muted" style="font-size:0.7rem;">{{ $o->brand ?: '-' }} &middot; {{ $o->ip_address }}</div>
                    </div>
                    <div class="text-end" style="min-width:90px;">
                        <span class="noc-status-badge" style="background:rgba(0,0,0,0.05);color:{{ $color }};">
                            <i class="fa-solid fa-circle" style="font-size:0.4rem;"></i>{{ $label }}
                        </span>
                        <div class="text-muted" style="font-size:0.62rem;">{{ $o->last_polled_at ? 'Poll '.$o->last_polled_at->diffForHumans() : 'Belum dipoll' }}</div>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-4 mb-0">Belum ada OLT terdaftar.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ═══ METRICS + INCIDENTS ═══ --}}
<div class="row g-3">
    {{-- METRICS --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3 pb-0">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-gauge me-2" style="color:var(--primary);"></i>Load Perangkat</h6>
                <span class="badge bg-light text-dark">↓ {{ $fmtRate($totalBwDl) }} &middot; ↑ {{ $fmtRate($totalBwUl) }}</span>
            </div>
            <div class="card-body px-3">
                @if($metrics->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Belum ada data metrik. Jalankan <code>network:data-collect</code> untuk mengumpulkan.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle" style="font-size:0.78rem;">
                        <thead class="text-muted" style="font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;">
                            <tr>
                                <th>Router</th>
                                <th class="text-center">CPU</th>
                                <th class="text-center">RAM</th>
                                <th class="text-center">↓ / ↑</th>
                                <th class="text-center">Latency</th>
                                <th class="text-center">Loss</th>
                                <th class="text-end">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metrics as $m)
                            <tr>
                                <td class="fw-semibold">{{ $m->router }}</td>
                                <td class="text-center">
                                    @php $cpuColor = $m->cpu_load > 80 ? '#dc2626' : ($m->cpu_load > 60 ? '#f59e0b' : '#059669'); @endphp
                                    <span class="fw-bold" style="color:{{ $cpuColor }};">{{ $m->cpu_load }}%</span>
                                </td>
                                <td class="text-center">
                                    @php $memColor = $m->memory_usage_pct > 85 ? '#dc2626' : ($m->memory_usage_pct > 70 ? '#f59e0b' : '#059669'); @endphp
                                    <span class="fw-bold" style="color:{{ $memColor }};">{{ $m->memory_usage_pct }}%</span>
                                </td>
                                <td class="text-center text-muted">{{ $fmtRate($m->bandwidth_download) }} / {{ $fmtRate($m->bandwidth_upload) }}</td>
                                <td class="text-center text-muted">{{ $m->latency_idle }} ms</td>
                                <td class="text-center text-muted">{{ $m->packet_loss }}%</td>
                                <td class="text-end text-muted" style="font-size:0.68rem;">{{ $m->collected_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- INCIDENTS --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3 pb-0">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-triangle-exclamation me-2" style="color:#ef4444;"></i>Incident Terbaru</h6>
                <div class="d-flex gap-2">
                    @if($incidentBreached > 0)
                        <span class="noc-status-badge" style="background:rgba(239,68,68,0.12);color:#dc2626;">{{ $incidentBreached }} SLA breach</span>
                    @endif
                    <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-primary">Lihat</a>
                </div>
            </div>
            <div class="card-body px-3">
                @forelse($recentIncidents as $inc)
                @php
                    $sevClass = match ($inc->severity) { 'critical' => 'bg-danger', 'high' => 'bg-warning text-dark', 'medium' => 'bg-info', default => 'bg-secondary' };
                    $statusClass = match ($inc->status) { 'open' => 'bg-primary', 'investigating' => 'bg-info', 'resolved' => 'bg-success', 'closed' => 'bg-secondary', default => 'bg-secondary' };
                @endphp
                <a href="{{ route('incidents.show', $inc) }}" class="text-decoration-none" style="color:inherit;">
                    <div class="d-flex align-items-start gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:rgba(0,0,0,0.05) !important;">
                        <div style="flex:1;min-width:0;">
                            <div class="fw-semibold" style="font-size:0.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $inc->title }}</div>
                            <div class="text-muted" style="font-size:0.68rem;">#{{ $inc->id }} &middot; {{ $inc->detected_at->diffForHumans() }}</div>
                        </div>
                        <span class="badge {{ $sevClass }}" style="border-radius:6px;font-size:0.62rem;">{{ ucfirst($inc->severity) }}</span>
                        <span class="badge {{ $statusClass }}" style="border-radius:6px;font-size:0.62rem;">{{ ucfirst($inc->status) }}</span>
                    </div>
                </a>
                @empty
                <p class="text-muted text-center py-4 mb-0">Tidak ada incident terbaru. Jaringan aman.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
