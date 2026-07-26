@extends('layouts.app')

@section('title', 'GenieACS Dashboard — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-satellite-dish me-2" style="color:var(--primary);"></i>GenieACS Dashboard</h2>
        <p class="section-subtitle mb-0 mt-1">
            Monitoring & manajemen perangkat CPE via GenieACS
            @if($stats['connected'])
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>CONNECTED
            </span>
            @else
            <span class="badge bg-danger ms-2" style="font-size:0.65rem;">DISCONNECTED</span>
            @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.settings') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gear me-1"></i>Settings
        </a>
    </div>
</div>

@if($stats['error'])
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>
        <strong>GenieACS unreachable.</strong> {{ $stats['error'] }}
    </div>
</div>
@endif

{{-- ═══ SUMMARY STATS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white stat-card-gradient-blue">
            <div class="stat-bg"><i class="fa-solid fa-satellite-dish"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-satellite-dish"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['total_devices'] }}</div>
                        <div class="stat-label">Total Devices</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['online_devices'] }}</div>
                        <div class="stat-label">Online (10m)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card text-white" style="background:linear-gradient(135deg,#ef4444,#dc2626);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['offline_devices'] }}</div>
                        <div class="stat-label">Offline</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['fault_count'] }}</div>
                        <div class="stat-label">Active Faults</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card text-white" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-file-code"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-file-code"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['preset_count'] }}</div>
                        <div class="stat-label">Presets</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ QUICK LINKS ═══ --}}
<div class="row g-3">
    <div class="col-md-4">
        <a href="{{ route('noc.genieacs.devices') }}" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:16px;transition:transform .15s;">
            <div class="card-body py-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,rgba(59,130,246,0.15),rgba(139,92,246,0.15));display:flex;align-items:center;justify-content:center;border:1px solid rgba(99,102,241,0.2);">
                        <i class="fa-solid fa-hard-drive" style="font-size:20px;color:#60a5fa;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color:rgba(255,255,255,0.9);">Devices</h6>
                        <small class="text-muted">Daftar & detail perangkat CPE</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('noc.genieacs.presets') }}" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:16px;transition:transform .15s;">
            <div class="card-body py-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,rgba(139,92,246,0.15),rgba(236,72,153,0.15));display:flex;align-items:center;justify-content:center;border:1px solid rgba(139,92,246,0.2);">
                        <i class="fa-solid fa-file-code" style="font-size:20px;color:#a78bfa;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color:rgba(255,255,255,0.9);">Presets</h6>
                        <small class="text-muted">Template provisioning & schedule</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('noc.genieacs.faults') }}" class="card border-0 shadow-sm text-decoration-none h-100" style="border-radius:16px;transition:transform .15s;">
            <div class="card-body py-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(239,68,68,0.15));display:flex;align-items:center;justify-content:center;border:1px solid rgba(245,158,11,0.2);">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:20px;color:#f59e0b;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color:rgba(255,255,255,0.9);">Faults</h6>
                        <small class="text-muted">Error & fault perangkat</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
