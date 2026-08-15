@extends('layouts.app')
@section('title', 'Integrasi MikroTik & OLT')
@push('styles')
<style>
.badge-shimmer {
    color: #fff;
    border: none;
    text-shadow: 0 1px 2px rgba(0,0,0,.25);
    background: linear-gradient(120deg, #667eea, #764ba2, #f093fb, #4facfe, #667eea);
    background-size: 300% 300%;
    animation: badgeShimmer 3s ease infinite;
    box-shadow: 0 0 14px rgba(102,126,234,.45);
}
@keyframes badgeShimmer {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.dev-icon {
    display: inline-block;
    animation: devGlow 2.4s ease-in-out infinite;
}
@keyframes devGlow {
    0%, 100% { filter: drop-shadow(0 0 0 rgba(100,110,240,0)); transform: scale(1); }
    50% { filter: drop-shadow(0 0 9px rgba(100,110,240,.75)); transform: scale(1.1); }
}
.dev-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin: 0 6px;
    vertical-align: middle;
}
.dev-dot-online { background: #22c55e; animation: pingGreen 1.6s infinite; }
.dev-dot-offline { background: #ef4444; animation: pingRed 1.6s infinite; }
.dev-dot-unknown { background: #cbd5e1; }
.conn-badge {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    border: 1px solid transparent;
}
.conn-badge::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
.badge-conn-online { background: #dcfce7; color: #16a34a; }
.badge-conn-online::before { background: #22c55e; animation: pingGreen 1.6s infinite; }
.badge-conn-offline { background: #fee2e2; color: #dc2626; }
.badge-conn-offline::before { background: #ef4444; animation: pingRed 1.6s infinite; }
.badge-conn-degraded { background: #fef9c3; color: #ca8a04; }
.badge-conn-degraded::before { background: #eab308; animation: pingYellow 1.6s infinite; }
.badge-conn-unknown { background: #f1f5f9; color: #64748b; }
.badge-conn-unknown::before { background: #94a3b8; }
@keyframes pingGreen {
    0% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    70%, 100% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
}
@keyframes pingRed {
    0% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
    70%, 100% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}
@keyframes pingYellow {
    0% { box-shadow: 0 0 0 0 rgba(234,179,8,.5); }
    70%, 100% { box-shadow: 0 0 0 6px rgba(234,179,8,0); }
}
.tt-wrap {
    position: relative;
    cursor: pointer;
    border-bottom: 1px dotted rgba(0,0,0,.25);
}
.tt-wrap .tt-bubble {
    visibility: hidden;
    opacity: 0;
    transition: opacity .2s;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 0;
    background: #0f172a;
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.4;
    white-space: normal;
    width: max-content;
    max-width: 280px;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
    pointer-events: none;
}
.tt-wrap .tt-bubble::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 24px;
    border: 6px solid transparent;
    border-top-color: #0f172a;
}
.tt-wrap:hover .tt-bubble,
.tt-wrap:focus .tt-bubble {
    visibility: visible;
    opacity: 1;
}
</style>
@endpush
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plug-circle-plus me-2" style="color:var(--primary);"></i>Integrasi MikroTik & OLT</h2>
        <p class="text-muted mb-0 mt-1 small">Kelola koneksi perangkat MikroTik (via tunnel / IP lokal) dan OLT untuk integrasi billing.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2 fs-5"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i> {{ session('error') }}
    </div>
@endif

@php
    $tunnelStatus = $routersTunnel->contains(fn ($r) => $r->status === 'online') ? 'online'
        : ($routersTunnel->contains(fn ($r) => $r->status === 'offline') ? 'offline' : 'unknown');
    $localStatus = $routersLocal->contains(fn ($r) => $r->status === 'online') ? 'online'
        : ($routersLocal->contains(fn ($r) => $r->status === 'offline') ? 'offline' : 'unknown');
    $oltStatus = $olts->contains(fn ($o) => $o->connection_status === 'online') ? 'online'
        : ($olts->contains(fn ($o) => $o->connection_status === 'offline') ? 'offline' : 'unknown');
@endphp

{{-- ══════════ MIKROTIK VIA TUNNEL ══════════ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div>
            <h5 class="mb-0"><i class="fa-solid fa-network-wired dev-icon me-1" style="color:var(--primary);"></i><span class="dev-dot dev-dot-{{ $tunnelStatus }}" title="Status: {{ ucfirst($tunnelStatus) }}"></span>MikroTik via Tunnel</h5>
            <small class="text-muted">Koneksi utama via tunnel publik (mis. cloud10.tunnel.id). Jika tunnel down, otomatis fallback ke IP Lokal.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-shimmer">{{ $routersTunnel->count() }} perangkat</span>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createMikrotikTunnelModal">
                <i class="fa-solid fa-plus me-1"></i>Tambah
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <tr>
                    <th>Nama</th>
                    <th>Host</th>
                    <th>IP Lokal</th>
                    <th>Port</th>
                    <th>Tipe</th>
                    <th>PPPoE <small class="text-muted fw-normal">On/Off</small></th>
                    <th>Hotspot <small class="text-muted fw-normal">On/Off</small></th>
                    <th>Status</th>
                    <th>Terakhir Koneksi</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tbody>
                    @forelse($routersTunnel as $router)
                        <tr data-router-live-url="{{ route('settings.integrations.mikrotik.live', $router) }}">
                            <td class="fw-semibold">
                                {{ $router->name }}
                                <span class="badge ms-1" style="background:#eff6ff;color:#2563eb;">Tunnel</span>
                            </td>
                            <td><code>{{ $router->host }}</code></td>
                            <td>
                                @if($router->local_ip)
                                    <code>{{ $router->local_ip }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $router->port }}</td>
                            <td>
                                <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                    {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                                </span>
                            </td>
                            <td>
                                @if($router->user_stats)
                                    <span class="fw-semibold text-success">{{ $router->user_stats['pppoe_online'] ?? 0 }}</span>
                                    <span class="text-muted mx-1">/</span>
                                    <span class="fw-semibold text-danger">{{ $router->user_stats['pppoe_offline'] ?? 0 }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($router->user_stats)
                                    <span class="fw-semibold text-success">{{ $router->user_stats['hotspot_online'] ?? 0 }}</span>
                                    <span class="text-muted mx-1">/</span>
                                    <span class="fw-semibold text-danger">{{ $router->user_stats['hotspot_offline'] ?? 0 }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge conn-badge {{ $router->status === 'online' ? 'badge-conn-online' : ($router->status === 'offline' ? 'badge-conn-offline' : ($router->status === 'degraded' ? 'badge-conn-degraded' : 'badge-conn-unknown')) }}" id="router-status-{{ $router->id }}">{{ $router->status_label }}</span>
                                @if($router->is_active)
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Aktif</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Nonaktif</span>
                                @endif
                            </td>
                            <td id="router-last-conn-{{ $router->id }}">
                                @if($router->last_connected)
                                    {{ $router->last_connected->diffForHumans() }}
                                @else
                                    <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.test', $router) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Koneksi / Test">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editMikrotikModal{{ $router->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">Belum ada router via tunnel. Klik "Tambah" untuk mengkoneksikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ MIKROTIK VIA IP LOKAL ══════════ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div>
            <h5 class="mb-0"><i class="fa-solid fa-house-laptop dev-icon me-1" style="color:var(--success);"></i><span class="dev-dot dev-dot-{{ $localStatus }}" title="Status: {{ ucfirst($localStatus) }}"></span>MikroTik via IP Lokal</h5>
            <small class="text-muted">Koneksi langsung via IP LAN lokal untuk router yang tidak punya tunnel publik.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-shimmer">{{ $routersLocal->count() }} perangkat</span>
            <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createMikrotikLocalModal">
                <i class="fa-solid fa-plus me-1"></i>Tambah
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <tr>
                    <th>Nama</th>
                    <th>Host</th>
                    <th>IP Lokal</th>
                    <th>Port</th>
                    <th>Tipe</th>
                    <th>PPPoE <small class="text-muted fw-normal">On/Off</small></th>
                    <th>Hotspot <small class="text-muted fw-normal">On/Off</small></th>
                    <th>Status</th>
                    <th>Terakhir Koneksi</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tbody>
                    @forelse($routersLocal as $router)
                        <tr data-router-live-url="{{ route('settings.integrations.mikrotik.live', $router) }}">
                            <td class="fw-semibold">
                                {{ $router->name }}
                                <span class="badge ms-1" style="background:#f0fdf4;color:#059669;">IP Lokal</span>
                            </td>
                            <td><code>{{ $router->host }}</code></td>
                            <td>
                                @if($router->local_ip)
                                    <code>{{ $router->local_ip }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $router->port }}</td>
                            <td>
                                <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                    {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                                </span>
                            </td>
                            <td>
                                @if($router->user_stats)
                                    <span class="fw-semibold text-success">{{ $router->user_stats['pppoe_online'] ?? 0 }}</span>
                                    <span class="text-muted mx-1">/</span>
                                    <span class="fw-semibold text-danger">{{ $router->user_stats['pppoe_offline'] ?? 0 }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($router->user_stats)
                                    <span class="fw-semibold text-success">{{ $router->user_stats['hotspot_online'] ?? 0 }}</span>
                                    <span class="text-muted mx-1">/</span>
                                    <span class="fw-semibold text-danger">{{ $router->user_stats['hotspot_offline'] ?? 0 }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge conn-badge {{ $router->status === 'online' ? 'badge-conn-online' : ($router->status === 'offline' ? 'badge-conn-offline' : ($router->status === 'degraded' ? 'badge-conn-degraded' : 'badge-conn-unknown')) }}" id="router-status-{{ $router->id }}">{{ $router->status_label }}</span>
                                @if($router->is_active)
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Aktif</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Nonaktif</span>
                                @endif
                            </td>
                            <td id="router-last-conn-{{ $router->id }}">
                                @if($router->last_connected)
                                    {{ $router->last_connected->diffForHumans() }}
                                @else
                                    <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.test', $router) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Koneksi / Test">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editMikrotikModal{{ $router->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">Belum ada router via IP lokal. Klik "Tambah" untuk mengkoneksikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ OLT ══════════ --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div>
            <h5 class="mb-0"><i class="fa-solid fa-tower-cell dev-icon me-1" style="color:var(--primary);"></i><span class="dev-dot dev-dot-{{ $oltStatus }}" title="Status: {{ ucfirst($oltStatus) }}"></span>OLT</h5>
            <small class="text-muted"><span class="tt-wrap" tabindex="0">Perangkat OLT multi-brand (Huawei, ZTE, FiberHome, C-Data, Global, VSOL, HSGQ, Hioso).<span class="tt-bubble">Jumlah ONU dibaca real-time dari perangkat</span></span></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-shimmer">{{ $olts->count() }} perangkat</span>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createOltModal">
                <i class="fa-solid fa-plus me-1"></i>Tambah
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <tr>
                    <th>Nama</th>
                    <th>Brand / Model</th>
                    <th>IP</th>
                    <th>SSH Port</th>
                    <th>ODC</th>
                    <th>ODP</th>
                    <th>ONU Online</th>
                    <th>ONU Offline</th>
                    <th>Status</th>
                    <th>Terakhir Polling</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tbody>
                    @forelse($olts as $olt)
                        <tr>
                            <td class="fw-semibold">{{ $olt->name }}</td>
                            <td>
                                <span class="badge" style="background:#f1f5f9;color:#334155;text-transform:capitalize;">{{ $olt->brand }}</span>
                                @if($olt->model)<small class="text-muted">{{ $olt->model }}</small>@endif
                            </td>
                            <td><code>{{ $olt->ip_address }}</code></td>
                            <td>{{ $olt->ssh_port }}</td>
                            <td><span class="fw-semibold">{{ $odcCount }}</span></td>
                            <td><span class="fw-semibold">{{ $odpCount }}</span></td>
                            <td><span class="fw-semibold text-success" id="olt-online-{{ $olt->id }}">—</span></td>
                            <td><span class="fw-semibold text-danger" id="olt-offline-{{ $olt->id }}">—</span></td>
                            <td>
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    <span class="badge conn-badge {{ $olt->connection_status === 'online' ? 'badge-conn-online' : ($olt->connection_status === 'offline' ? 'badge-conn-offline' : 'badge-conn-unknown') }}" id="olt-conn-status-{{ $olt->id }}">
                                        {{ $olt->connection_status === 'online' ? 'Online' : ($olt->connection_status === 'offline' ? 'Offline' : 'Belum dicek') }}
                                    </span>
                                    <span class="badge bg-{{ match($olt->status) { 'active' => 'success', 'maintenance' => 'warning', default => 'secondary' } }}">
                                        {{ match($olt->status) { 'active' => 'Active', 'maintenance' => 'Maintenance', default => 'Inactive' } }}
                                    </span>
                                </div>
                            </td>
                            <td id="olt-last-poll-{{ $olt->id }}">
                                @if($olt->last_polled_at)
                                    {{ $olt->last_polled_at->diffForHumans() }}
                                @else
                                    <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.integrations.olt.test', $olt) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Koneksi / Test">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-primary px-2" title="Muat ulang ONU (real-time)" data-olt-live-url="{{ route('settings.integrations.olt.live', $olt) }}">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editOltModal{{ $olt->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.integrations.olt.destroy', $olt) }}" class="d-inline" onsubmit="return confirm('Hapus OLT {{ $olt->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-4 text-muted">Belum ada OLT. Klik "Tambah" untuk mengkoneksikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ CREATE MIKROTIK VIA TUNNEL MODAL ══════════ --}}
<div class="modal fade" id="createMikrotikTunnelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.mikrotik.store') }}">
                @csrf
                <input type="hidden" name="connection_mode" value="tunnel">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah MikroTik via Tunnel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: RB-Main" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host Tunnel</label>
                            <input type="text" name="host" class="form-control" placeholder="cloud10.tunnel.id" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="8728" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">IP Lokal <small class="text-muted">(fallback saat tunnel down)</small></label>
                            <input type="text" name="local_ip" class="form-control" placeholder="172.10.0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port Lokal</label>
                            <input type="number" name="local_port" class="form-control" placeholder="80" min="1" max="65535">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Tipe Router</label>
                        <select name="type" class="form-select" required>
                            <option value="pppoe">PPPoE (Utama)</option>
                            <option value="bandwidth">Bandwidth (HTB)</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" placeholder="Kosongkan untuk default">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SSH Port <small class="text-muted">(isi jika RouterOS v6 / REST tidak support)</small></label>
                        <input type="number" name="ssh_port" class="form-control" placeholder="Kosongkan untuk REST API" min="1" max="65535">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createMikrotikTunnelIsActive" checked>
                        <label class="form-check-label" for="createMikrotikTunnelIsActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ CREATE MIKROTIK VIA IP LOKAL MODAL ══════════ --}}
<div class="modal fade" id="createMikrotikLocalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.mikrotik.store') }}">
                @csrf
                <input type="hidden" name="connection_mode" value="local_ip">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah MikroTik via IP Lokal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: RB-Lokal" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host (IP Lokal)</label>
                            <input type="text" name="host" class="form-control" placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="8728" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">IP Lokal Cadangan <small class="text-muted">(fallback jika host utama down)</small></label>
                            <input type="text" name="local_ip" class="form-control" placeholder="172.10.0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port Lokal</label>
                            <input type="number" name="local_port" class="form-control" placeholder="80" min="1" max="65535">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Tipe Router</label>
                        <select name="type" class="form-select" required>
                            <option value="pppoe">PPPoE (Utama)</option>
                            <option value="bandwidth">Bandwidth (HTB)</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" placeholder="Kosongkan untuk default">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SSH Port <small class="text-muted">(isi jika RouterOS v6 / REST tidak support)</small></label>
                        <input type="number" name="ssh_port" class="form-control" placeholder="Kosongkan untuk REST API" min="1" max="65535">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createMikrotikLocalIsActive" checked>
                        <label class="form-check-label" for="createMikrotikLocalIsActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ CREATE OLT MODAL ══════════ --}}
<div class="modal fade" id="createOltModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.olt.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: OLT-1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select" required>
                                <option value="">Pilih</option>
                                <option value="huawei">Huawei</option>
                                <option value="zte">ZTE</option>
                                <option value="fiberhome">FiberHome</option>
                                <option value="cdata">C-Data</option>
                                <option value="global">Global</option>
                                <option value="vsol">VSOL</option>
                                <option value="hsgq">HSGQ</option>
                                <option value="hioso">Hioso</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Model</label>
                            <input type="text" name="model" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip_address" class="form-control" placeholder="10.10.10.1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SSH Port</label>
                            <input type="number" name="ssh_port" class="form-control" value="22" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username SSH</label>
                            <input type="text" name="username" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password SSH</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Tunnel via Jump Host <small class="text-muted fw-normal">(opsional)</small></h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jump Host IP</label>
                            <input type="text" name="jump_host" class="form-control" placeholder="IP perantara">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="jump_port" class="form-control" value="22" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="jump_username" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="jump_password" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Version</label>
                            <select name="snmp_version" class="form-select">
                                <option value="">Pilih</option>
                                <option value="v1">v1</option>
                                <option value="v2c">v2c</option>
                                <option value="v3">v3</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control" value="161" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Lokasi</label>
                            <input type="text" name="location" class="form-control" placeholder="Alamat / nama lokasi">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ EDIT MIKROTIK MODALS ══════════ --}}
@foreach($routersTunnel->merge($routersLocal) as $router)
<div class="modal fade" id="editMikrotikModal{{ $router->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.mikrotik.update', $router) }}">
                @csrf @method('PUT')
                <input type="hidden" name="connection_mode" value="{{ $router->connection_mode }}">
                <div class="modal-header">
                    <h5 class="modal-title">Edit MikroTik via {{ $router->connection_mode === 'local_ip' ? 'IP Lokal' : 'Tunnel' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" value="{{ $router->name }}" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host/IP</label>
                            <input type="text" name="host" class="form-control" value="{{ $router->host }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="{{ $router->port }}" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">IP Lokal <small class="text-muted">(fallback saat host utama down)</small></label>
                            <input type="text" name="local_ip" class="form-control" value="{{ $router->local_ip }}" placeholder="172.10.0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port Lokal</label>
                            <input type="number" name="local_port" class="form-control" value="{{ $router->local_port }}" placeholder="80" min="1" max="65535">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" value="{{ $router->username }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Tipe Router</label>
                        <select name="type" class="form-select" required>
                            <option value="pppoe" {{ $router->type === 'pppoe' ? 'selected' : '' }}>PPPoE (Utama)</option>
                            <option value="bandwidth" {{ $router->type === 'bandwidth' ? 'selected' : '' }}>Bandwidth (HTB)</option>
                            <option value="general" {{ $router->type === 'general' ? 'selected' : '' }}>General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" value="{{ $router->hotspot_server }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SSH Port <small class="text-muted">(isi jika RouterOS v6 / REST tidak support)</small></label>
                        <input type="number" name="ssh_port" class="form-control" value="{{ $router->ssh_port }}" placeholder="Kosongkan untuk REST API" min="1" max="65535">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editMikrotikIsActive{{ $router->id }}" {{ $router->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editMikrotikIsActive{{ $router->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- ══════════ EDIT OLT MODALS ══════════ --}}
@foreach($olts as $olt)
<div class="modal fade" id="editOltModal{{ $olt->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.olt.update', $olt) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $olt->name }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select" required>
                                <option value="huawei" {{ $olt->brand === 'huawei' ? 'selected' : '' }}>Huawei</option>
                                <option value="zte" {{ $olt->brand === 'zte' ? 'selected' : '' }}>ZTE</option>
                                <option value="fiberhome" {{ $olt->brand === 'fiberhome' ? 'selected' : '' }}>FiberHome</option>
                                <option value="cdata" {{ $olt->brand === 'cdata' ? 'selected' : '' }}>C-Data</option>
                                <option value="global" {{ $olt->brand === 'global' ? 'selected' : '' }}>Global</option>
                                <option value="vsol" {{ $olt->brand === 'vsol' ? 'selected' : '' }}>VSOL</option>
                                <option value="hsgq" {{ $olt->brand === 'hsgq' ? 'selected' : '' }}>HSGQ</option>
                                <option value="hioso" {{ $olt->brand === 'hioso' ? 'selected' : '' }}>Hioso</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Model</label>
                            <input type="text" name="model" class="form-control" value="{{ $olt->model }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip_address" class="form-control" value="{{ $olt->ip_address }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SSH Port</label>
                            <input type="number" name="ssh_port" class="form-control" value="{{ $olt->ssh_port }}" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username SSH</label>
                            <input type="text" name="username" class="form-control" value="{{ $olt->username }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password SSH</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Tunnel via Jump Host <small class="text-muted fw-normal">(opsional)</small></h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jump Host IP</label>
                            <input type="text" name="jump_host" class="form-control" value="{{ $olt->jump_host }}" placeholder="IP perantara">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="jump_port" class="form-control" value="{{ $olt->jump_port ?? 22 }}" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="jump_username" class="form-control" value="{{ $olt->jump_username }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="jump_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control" value="{{ $olt->snmp_community }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Version</label>
                            <select name="snmp_version" class="form-select">
                                <option value="">Pilih</option>
                                <option value="v1" {{ $olt->snmp_version === 'v1' ? 'selected' : '' }}>v1</option>
                                <option value="v2c" {{ $olt->snmp_version === 'v2c' ? 'selected' : '' }}>v2c</option>
                                <option value="v3" {{ $olt->snmp_version === 'v3' ? 'selected' : '' }}>v3</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control" value="{{ $olt->snmp_port }}" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ $olt->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="maintenance" {{ $olt->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                <option value="inactive" {{ $olt->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Lokasi</label>
                            <input type="text" name="location" class="form-control" value="{{ $olt->location }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2">{{ $olt->notes }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection

@push('scripts')
<script>
function setConnBadge(el, status) {
    if (!el) return;
    var map = { online: 'badge-conn-online', offline: 'badge-conn-offline', degraded: 'badge-conn-degraded' };
    var cls = map[status] || 'badge-conn-unknown';
    el.classList.remove('badge-conn-online', 'badge-conn-offline', 'badge-conn-degraded', 'badge-conn-unknown');
    el.classList.add(cls);
    var labels = { online: 'Online', offline: 'Offline', degraded: 'Degraded' };
    el.textContent = labels[status] || 'Belum dicek';
}

function relativeTime(iso) {
    if (!iso) return 'Belum pernah';
    var d = new Date(iso), now = new Date();
    var s = Math.floor((now - d) / 1000);
    if (s < 45) return 'Baru saja';
    var m = Math.floor(s / 60);
    if (m < 60) return m + ' menit lalu';
    var h = Math.floor(m / 60);
    if (h < 24) return h + ' jam lalu';
    return Math.floor(h / 24) + ' hari lalu';
}

function refreshOltLive(url, btn) {
    if (btn) {
        btn.disabled = true;
        var icon = btn.querySelector('i');
        if (icon) icon.classList.add('fa-spin');
    }

    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        var online = document.getElementById('olt-online-' + d.olt_id);
        var offline = document.getElementById('olt-offline-' + d.olt_id);
        if (online) online.textContent = d.error ? '—' : (d.online_onus ?? 0);
        if (offline) offline.textContent = d.error ? '—' : (d.offline_onus ?? 0);
        var conn = document.getElementById('olt-conn-status-' + d.olt_id);
        if (conn) setConnBadge(conn, d.connection_status);
        var lastPoll = document.getElementById('olt-last-poll-' + d.olt_id);
        if (lastPoll) lastPoll.textContent = d.error && !d.last_polled_at ? 'Belum pernah' : relativeTime(d.last_polled_at);
    })
    .catch(function () {
        if (btn && btn.dataset.oltLiveUrl) {
            var parts = btn.dataset.oltLiveUrl.split('/');
            var oltId = parts[parts.length - 2] || parts[parts.length - 1];
            var online = document.getElementById('olt-online-' + oltId);
            var offline = document.getElementById('olt-offline-' + oltId);
            if (online) online.textContent = '—';
            if (offline) offline.textContent = '—';
        }
    })
    .finally(function () {
        if (btn) {
            btn.disabled = false;
            var icon = btn.querySelector('i');
            if (icon) icon.classList.remove('fa-spin');
        }
    });
}

function refreshMikrotikLive(url, btn) {
    if (btn) {
        btn.disabled = true;
        var icon = btn.querySelector('i');
        if (icon) icon.classList.add('fa-spin');
    }

    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        var badge = document.getElementById('router-status-' + d.router_id);
        if (badge) setConnBadge(badge, d.status);
        var cell = document.getElementById('router-last-conn-' + d.router_id);
        if (cell) cell.textContent = relativeTime(d.last_connected);
    })
    .catch(function () {})
    .finally(function () {
        if (btn) {
            btn.disabled = false;
            var icon = btn.querySelector('i');
            if (icon) icon.classList.remove('fa-spin');
        }
    });
}

function pollIntegrations() {
    document.querySelectorAll('[data-olt-live-url]').forEach(function (el) {
        refreshOltLive(el.getAttribute('data-olt-live-url'), el);
    });
    document.querySelectorAll('[data-router-live-url]').forEach(function (el) {
        refreshMikrotikLive(el.getAttribute('data-router-live-url'), el);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    pollIntegrations();
    setInterval(pollIntegrations, 60000);
});
</script>
@endpush
