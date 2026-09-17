@extends('layouts.app')

@section('title', 'GenieACS Overview — NOC')

@push('styles')
<style>
    .acs-panel { border-radius: 16px; }
    .acs-panel-card { transition: transform .15s var(--ease-out), box-shadow .15s var(--ease-out); }
    .acs-panel-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-card-hov); }
    .acs-panel-title { color: var(--text-primary); font-size: 0.88rem; font-weight: 700; letter-spacing: 0.01em; }
    .acs-donut-wrap { position: relative; width: 104px; height: 104px; flex: 0 0 104px; }
    .acs-donut { width: 100%; height: 100%; border-radius: 50%;
        -webkit-mask: radial-gradient(closest-side, transparent 64%, #000 65%);
                mask: radial-gradient(closest-side, transparent 64%, #000 65%); }
    .acs-donut-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; font-weight: 800; color: var(--text-primary); }
    .acs-legend { min-width: 0; }
    .acs-legend-row { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; line-height: 1.75; min-width: 0; }
    .acs-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 9px; }
    .acs-legend-label { color: var(--text-secondary); flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .acs-legend-count { color: var(--text-primary); margin-left: auto; padding-left: 8px; flex: 0 0 auto; white-space: nowrap; }
    .acs-legend-pct { color: var(--text-muted); min-width: 56px; text-align: right; flex: 0 0 auto; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .acs-nav-btn { border-radius: 10px; padding: 9px 18px; font-size: 0.8rem; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-satellite-dish me-2" style="color:var(--primary);"></i>ACS Config &amp; Monitoring</h2>
        <p class="section-subtitle mb-0 mt-1">
            Monitoring &amp; manajemen perangkat CPE via GenieACS
            @if($connected)
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>CONNECTED
            </span>
            @else
            <span class="badge bg-danger ms-2" style="font-size:0.65rem;">DISCONNECTED</span>
            @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-primary shadow-sm acs-nav-btn"><i class="fa-solid fa-gauge-high me-1"></i>Overview</a>
        <a href="{{ route('noc.genieacs.devices') }}" class="btn btn-outline-secondary acs-nav-btn"><i class="fa-solid fa-hard-drive me-1"></i>Device</a>
        <a href="{{ route('noc.genieacs.settings') }}" class="btn btn-outline-secondary acs-nav-btn"><i class="fa-solid fa-gear me-1"></i>Settings</a>
    </div>
</div>

@if(! $connected)
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>
        <strong>GenieACS unreachable.</strong> {{ $error }}
    </div>
</div>
@endif

{{-- ═══ OVERVIEW PANELS ═══ --}}
<div class="row g-3">
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Status',
                'icon' => 'fa-signal',
                'total' => $overview['status']['total'],
                'items' => $overview['status']['items'],
            ])
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Access Type',
                'icon' => 'fa-network-wired',
                'total' => $overview['access']['total'],
                'items' => $overview['access']['items'],
            ])
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Optical RX',
                'icon' => 'fa-tower-broadcast',
                'total' => $overview['rx']['total'],
                'items' => $overview['rx']['items'],
            ])
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Merk Perangkat',
                'icon' => 'fa-sitemap',
                'total' => $overview['brands']['total'],
                'items' => $overview['brands']['items'],
            ])
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Devices Register',
                'icon' => 'fa-calendar-plus',
                'total' => $overview['register']['total'],
                'items' => $overview['register']['items'],
            ])
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="acs-panel-card h-100">
            @include('noc.genieacs.partials._stat-panel', [
                'title' => 'Optical Temperatur',
                'icon' => 'fa-temperature-half',
                'total' => $overview['temp']['total'],
                'items' => $overview['temp']['items'],
            ])
        </div>
    </div>
</div>
@endsection