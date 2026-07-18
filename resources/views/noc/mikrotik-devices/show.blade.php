@extends('layouts.app')

@section('title', $router->name . ' — MikroTik Device')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0">
            <i class="fa-solid fa-router me-2" style="color:var(--primary);"></i>{{ $router->display_identity }}
            <span class="badge bg-{{ $router->status_badge_color }} ms-2" style="font-size:0.65rem;vertical-align:middle;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:{{ $router->status === 'online' ? '#fff' : 'rgba(255,255,255,0.5)' }};margin-right:4px;{{ $router->status === 'online' ? 'animation:pulse 1.5s infinite;' : '' }}"></span>
                {{ $router->status_label }}
            </span>
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <code>{{ $router->host }}:{{ $router->port }}</code>
            @if($router->model) &mdash; {{ $router->model }} @endif
            @if($router->site) &mdash; {{ $router->site }} @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <form action="{{ route('noc.mikrotik-devices.test-connection', $router) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-success px-3 py-2" title="Test Koneksi">
                <i class="fa-solid fa-plug me-1"></i>Test Koneksi
            </button>
        </form>
        <form action="{{ route('noc.mikrotik-devices.toggle-status', $router) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-{{ $router->is_active ? 'warning' : 'success' }} px-3 py-2" title="{{ $router->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                <i class="fa-solid fa-{{ $router->is_active ? 'toggle-off' : 'toggle-on' }} me-1"></i>{{ $router->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
        </form>
        <a href="{{ route('noc.mikrotik-devices.edit', $router) }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-pen me-1"></i>Edit
        </a>
        <form action="{{ route('noc.mikrotik-devices.destroy', $router) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}? Tindakan ini tidak dapat dibatalkan.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger px-3 py-2"><i class="fa-solid fa-trash me-1"></i>Hapus</button>
        </form>
        <a href="{{ route('noc.mikrotik-devices.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
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

{{-- INFO CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0s">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Status</small>
                <span class="badge bg-{{ $router->status_badge_color }}" style="font-size:0.75rem;">{{ $router->status_label }}</span>
                @if($router->last_seen)
                    <br><small class="text-muted mt-1 d-block">{{ $router->last_seen->diffForHumans() }}</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">RouterOS</small>
                <strong>{{ $router->routeros_version ?? '-' }}</strong>
                @if($router->architecture)
                    <br><small class="text-muted">{{ $router->architecture }}</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-server"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $router->model ?? '-' }}</div>
                <div class="stat-label">Model</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Koneksi</small>
                <strong>{{ strtoupper($router->connection_type) }}</strong>
                <br><small class="text-muted">Timeout: {{ $router->timeout }}s</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- LEFT: Connection Details --}}
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(37,99,235,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plug me-2" style="color:var(--primary);"></i>Informasi Koneksi</h6>
            </div>
            <div class="card-body">
                
                    <tr>
                        <td class="text-muted" style="width:40%;">Nama</td>
                        <td class="fw-semibold">{{ $router->name }}</td>
                    </tr>
                    @if($router->identity && $router->identity !== $router->name)
                    <tr>
                        <td class="text-muted">Identity</td>
                        <td class="fw-semibold">{{ $router->identity }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Host</td>
                        <td><code>{{ $router->host }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Port REST</td>
                        <td>{{ $router->port }}</td>
                    </tr>
                    @if($router->ssh_port)
                    <tr>
                        <td class="text-muted">SSH Port</td>
                        <td>{{ $router->ssh_port }}</td>
                    </tr>
                    @endif
                    @if($router->api_ssl_port)
                    <tr>
                        <td class="text-muted">API SSL Port</td>
                        <td>{{ $router->api_ssl_port }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Username</td>
                        <td>{{ $router->username }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Password</td>
                        <td><code>••••••••</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tipe</td>
                        <td>
                            <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Hotspot Server</td>
                        <td>{{ $router->hotspot_server ?: 'all' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Aktif</td>
                        <td>
                            <span class="badge" style="background:{{ $router->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $router->is_active ? '#059669' : '#64748b' }};">
                                {{ $router->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>

        {{-- Tags --}}
        @if($router->tags && count($router->tags) > 0)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(139,92,246,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-tags me-2" style="color:#8b5cf6;"></i>Tags</h6>
            </div>
            <div class="card-body">
                @foreach($router->tags as $tag)
                    <span class="badge bg-primary" style="font-size:0.75rem;">{{ $tag }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- RIGHT: Site & System Info --}}
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(5,150,105,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-location-dot me-2" style="color:#059669;"></i>Site & Lokasi</h6>
            </div>
            <div class="card-body">
                
                    <tr>
                        <td class="text-muted" style="width:40%;">Site</td>
                        <td class="fw-semibold">{{ $router->site ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Lokasi</td>
                        <td>{{ $router->location ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Timezone</td>
                        <td>{{ $router->timezone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Latitude</td>
                        <td>{{ $router->latitude ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Longitude</td>
                        <td>{{ $router->longitude ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Management VLAN</td>
                        <td>{{ $router->management_vlan ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Management Interface</td>
                        <td>{{ $router->management_interface ?? '-' }}</td>
                    </tr>
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(245,158,11,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-microchip me-2" style="color:#f59e0b;"></i>System Info</h6>
            </div>
            <div class="card-body">
                
                    <tr>
                        <td class="text-muted" style="width:40%;">Serial Number</td>
                        <td><code>{{ $router->serial_number ?? '-' }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">RouterOS Version</td>
                        <td>{{ $router->routeros_version ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Model</td>
                        <td>{{ $router->model ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Architecture</td>
                        <td>{{ $router->architecture ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Last Connected</td>
                        <td>{{ $router->last_connected ? $router->last_connected->diffForHumans() : '-' }}</td>
                    </tr>
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>

        @if($router->notes)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-sticky-note me-1"></i> Catatan</h6>
                <p class="mb-0" style="white-space:pre-line;">{{ $router->notes }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

