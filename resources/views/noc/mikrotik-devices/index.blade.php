@extends('layouts.app')

@section('title', 'MikroTik Device Manager — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-router me-2" style="color:var(--primary);"></i>MikroTik Device Manager</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar dan monitoring seluruh perangkat MikroTik</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.mikrotik-devices.create') }}" class="btn btn-primary px-3 py-2">
            <i class="fa-solid fa-plus me-1"></i>Tambah Device
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

{{-- STATS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(37,99,235,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-server" style="color:var(--primary);font-size:18px;"></i>
                    </div>
                    <div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.05em;">Total Devices</div>
                        <div class="fw-bold" style="font-size:1.5rem;">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['online'] }}</div>
                <div class="stat-label">Online</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#ef4444,#dc2626);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['offline'] }}</div>
                <div class="stat-label">Offline</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.2s">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(100,116,139,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-question-circle" style="color:#64748b;font-size:18px;"></i>
                    </div>
                    <div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.05em;">Unknown</div>
                        <div class="fw-bold" style="font-size:1.5rem;">{{ $stats['unknown'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('noc.mikrotik-devices.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama, host, identity, site, lokasi..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="unknown" {{ request('status') === 'unknown' ? 'selected' : '' }}>Unknown</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Tipe</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="pppoe" {{ request('type') === 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                    <option value="bandwidth" {{ request('type') === 'bandwidth' ? 'selected' : '' }}>Bandwidth</option>
                    <option value="general" {{ request('type') === 'general' ? 'selected' : '' }}>General</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                </button>
                <a href="{{ route('noc.mikrotik-devices.index') }}" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- DEVICE TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Status</th>
                        <th>Nama / Identity</th>
                        <th>Host</th>
                        <th>Tipe</th>
                        <th>Model</th>
                        <th>Site</th>
                        <th>Last Seen</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($routers as $router)
                        <tr>
                            <td>
                                <span class="badge bg-{{ $router->status_badge_color }}" style="font-size:0.65rem;">
                                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:{{ $router->status === 'online' ? '#fff' : 'rgba(255,255,255,0.5)' }};margin-right:4px;{{ $router->status === 'online' ? 'animation:pulse 1.5s infinite;' : '' }}"></span>
                                    {{ $router->status_label }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('noc.mikrotik-devices.show', $router) }}" class="fw-semibold text-decoration-none">
                                    {{ $router->name }}
                                </a>
                                @if($router->identity && $router->identity !== $router->name)
                                    <br><small class="text-muted">{{ $router->identity }}</small>
                                @endif
                            </td>
                            <td><code style="font-size:0.8rem;">{{ $router->host }}:{{ $router->port }}</code></td>
                            <td>
                                <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                    {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                                </span>
                            </td>
                            <td>{{ $router->model ?? '-' }}</td>
                            <td>{{ $router->site ?? '-' }}</td>
                            <td>
                                @if($router->last_seen)
                                    <small>{{ $router->last_seen->diffForHumans() }}</small>
                                @else
                                    <small class="text-muted">Belum pernah</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('noc.mikrotik-devices.test-connection', $router) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Test Koneksi">
                                        <i class="fa-solid fa-plug"></i>
                                    </button>
                                </form>
                                <a href="{{ route('noc.mikrotik-devices.show', $router) }}" class="btn btn-sm btn-outline-info px-2" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('noc.mikrotik-devices.edit', $router) }}" class="btn btn-sm btn-outline-primary px-2" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="POST" action="{{ route('noc.mikrotik-devices.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div style="color:rgba(255,255,255,0.3);">
                                    <i class="fa-solid fa-router" style="font-size:2rem;"></i>
                                    <p class="mt-2 mb-0">Belum ada device MikroTik</p>
                                    <a href="{{ route('noc.mikrotik-devices.create') }}" class="btn btn-sm btn-primary mt-3">
                                        <i class="fa-solid fa-plus me-1"></i>Tambah Device Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($routers->hasPages())
        <div class="card-footer border-0">
            {{ $routers->links() }}
        </div>
    @endif
</div>
@endsection

