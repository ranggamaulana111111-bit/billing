@extends('layouts.app')

@section('title', 'MikroTik Monitor')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-router me-2" style="color:var(--primary);"></i>Monitor MikroTik</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $identity['name'] ?? 'MikroTik' }} — {{ $resource['version'] ?? '-' }}</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-sliders me-1"></i>Pengaturan
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- RESOURCE CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:var(--primary);font-size:1.6rem;">
                {{ $resource['board-name'] ?? '-' }}
            </div>
            <small class="text-muted">Board</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#059669;font-size:1.6rem;">
                {{ $resource['cpu-load'] ?? '0' }}%
            </div>
            <small class="text-muted">CPU Load</small>
            <div class="progress mt-2" style="height:6px;">
                <div class="progress-bar" style="width:{{ $resource['cpu-load'] ?? 0 }}%;background:{{ ($resource['cpu-load'] ?? 0) > 80 ? '#dc2626' : '#059669' }};"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            @php
                $totalMem = $resource['total-memory'] ?? 1;
                $freeMem = $resource['free-memory'] ?? 0;
                $usedMem = $totalMem - $freeMem;
                $memPct = $totalMem > 0 ? round(($usedMem / $totalMem) * 100) : 0;
            @endphp
            <div class="stat-number" style="color:#d97706;font-size:1.6rem;">
                {{ $memPct }}%
            </div>
            <small class="text-muted">Memory</small>
            <small class="d-block mt-1" style="font-size:0.7rem;">
                {{ round($usedMem / 1048576) }}MB / {{ round($totalMem / 1048576) }}MB
            </small>
            <div class="progress mt-1" style="height:6px;">
                <div class="progress-bar" style="width:{{ $memPct }}%;background:#d97706;"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            @php
                $upDays = floor($uptimeSeconds / 86400);
                $upHours = floor(($uptimeSeconds % 86400) / 3600);
                $upMins = floor(($uptimeSeconds % 3600) / 60);
            @endphp
            <div class="stat-number" style="color:#6366f1;font-size:1.3rem;">
                {{ $upDays }}h {{ $upHours }}j {{ $upMins }}m
            </div>
            <small class="text-muted">Uptime</small>
            <small class="d-block mt-1" style="font-size:0.7rem;">
                RouterOS {{ $resource['version'] ?? '-' }}
            </small>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- INTERFACES --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Interface</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Interface</th><th>Type</th><th class="text-end">RX</th><th class="text-end">TX</th></tr>
                    </thead>
                    <tbody>
                        @forelse($interfaces as $iface)
                            <tr>
                                <td class="fw-medium">{{ $iface['name'] ?? '-' }}</td>
                                <td>
                                    @if(($iface['running'] ?? false) && $iface['name'] !== 'all')
                                        <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">
                                            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#059669;margin-right:4px;"></span>Up
                                        </span>
                                    @else
                                        <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">Down</span>
                                    @endif
                                </td>
                                <td class="text-end text-muted" style="font-size:0.8rem;">
                                    {{ $iface['rx-byte'] ?? '0' }}
                                </td>
                                <td class="text-end text-muted" style="font-size:0.8rem;">
                                    {{ $iface['tx-byte'] ?? '0' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-3 text-muted">Tidak ada interface</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ACTIVE SESSIONS SUMMARY --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;#059669;"></div>
                    <span>Sesi Aktif</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 text-center">
                        <div class="stat-number" style="color:#2563eb;font-size:2rem;">{{ count($activeHotspot) }}</div>
                        <small class="text-muted">Hotspot Aktif</small>
                        <a href="{{ route('mikrotik.active') }}" class="d-block mt-2">
                            <small><i class="fa-solid fa-arrow-right me-1"></i>Lihat Detail</small>
                        </a>
                    </div>
                    <div class="col-6 text-center">
                        <div class="stat-number" style="color:#059669;font-size:2rem;">{{ count($activePpp) }}</div>
                        <small class="text-muted">PPP Aktif</small>
                        <a href="{{ route('mikrotik.active') }}" class="d-block mt-2">
                            <small><i class="fa-solid fa-arrow-right me-1"></i>Lihat Detail</small>
                        </a>
                    </div>
                    <div class="col-6 text-center">
                        <div class="stat-number" style="color:#d97706;font-size:2rem;">{{ count($hotspotUsers) }}</div>
                        <small class="text-muted">Total User Hotspot</small>
                    </div>
                    <div class="col-6 text-center">
                        <div class="stat-number" style="color:#6366f1;font-size:2rem;">
                            @php $totalSessions = count($activeHotspot) + count($activePpp); @endphp
                            {{ $totalSessions }}
                        </div>
                        <small class="text-muted">Total Sesi</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;#64748b;"></div>
                    <span>Aksi Cepat</span>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 flex-wrap">
                    <a href="{{ route('mikrotik.profiles') }}" class="btn btn-outline-primary">
                        <i class="fa-solid fa-layer-group me-1"></i>Profiles
                    </a>
                    <a href="{{ route('mikrotik.ppp') }}" class="btn btn-outline-success">
                        <i class="fa-solid fa-network-wired me-1"></i>PPP Secrets
                    </a>
                    <a href="{{ route('mikrotik.queues') }}" class="btn btn-outline-warning">
                        <i class="fa-solid fa-gauge-high me-1"></i>Queue Bandwidth
                    </a>
                    <form method="POST" action="{{ route('mikrotik.backup') }}" class="d-inline" onsubmit="return confirm('Buat backup MikroTik sekarang?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
