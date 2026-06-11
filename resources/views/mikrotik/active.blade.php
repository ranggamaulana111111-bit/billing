@extends('layouts.app')

@section('title', 'Sesi Aktif')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plug me-2" style="color:var(--primary);"></i>Sesi Aktif</h2>
        <p class="section-subtitle mb-0 mt-1">Pantau dan kelola sesi hotspot & PPP aktif di MikroTik</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('mikrotik.dashboard') }}" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Monitor
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4">
    {{-- HOTSPOT ACTIVE --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
                    <span>Hotspot Aktif</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($hotspot) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr><th>User</th><th>Address</th><th>Uptime</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse($hotspot as $s)
                            <tr>
                                <td class="fw-medium">{{ $s['user'] ?? $s['name'] ?? '-' }}</td>
                                <td><code style="font-size:0.75rem;">{{ $s['address'] ?? '-' }}</code></td>
                                <td>{{ $s['uptime'] ?? '-' }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('mikrotik.active.disconnect', $s['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Putuskan sesi {{ $s['user'] ?? $s['name'] ?? '' }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Putuskan">
                                            <i class="fa-solid fa-power-off"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada sesi hotspot aktif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PPP ACTIVE --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span>PPP Aktif</span>
                    <span class="badge badge-premium ms-2" style="background:#f0fdf4;color:#059669;">{{ count($ppp) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr><th>User</th><th>Service</th><th>Address</th><th>Uptime</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse($ppp as $s)
                            <tr>
                                <td class="fw-medium">{{ $s['name'] ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">
                                        {{ $s['service'] ?? 'pppoe' }}
                                    </span>
                                </td>
                                <td><code style="font-size:0.75rem;">{{ $s['address'] ?? '-' }}</code></td>
                                <td>{{ $s['uptime'] ?? '-' }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('mikrotik.active.ppp-disconnect', $s['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Putuskan sesi {{ $s['name'] ?? '' }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Putuskan">
                                            <i class="fa-solid fa-power-off"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada sesi PPP aktif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
