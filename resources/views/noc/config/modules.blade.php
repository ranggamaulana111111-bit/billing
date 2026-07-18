@extends('layouts.app')

@section('title', 'Configuration Center — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-sliders me-2" style="color:var(--primary);"></i>Configuration Center</h2>
        <p class="section-subtitle mb-0 mt-1">Pusat pengelolaan konfigurasi RouterOS — {{ count($routers) }} router aktif</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.sync.dashboard') }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-rotate me-1"></i>Config Sync
        </a>
    </div>
</div>

{{-- ═══ LAST SYNC INFO ═══ --}}
@if($lastSync)
<div class="alert alert-info d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-info me-2"></i>
    Last sync: <strong class="mx-1">{{ $lastSync->started_at->diffForHumans() }}</strong>
    ({{ $lastSync->total_items }} items, {{ $lastSync->duration_human }})
    <a href="{{ route('noc.sync.dashboard') }}" class="ms-auto">View Sync Dashboard</a>
</div>
@endif

{{-- ═══ MODULE GRID ═══ --}}
@foreach($moduleGroups as $category => $modules)
<div class="mb-4">
    <h6 class="fw-bold text-muted mb-3" style="font-size:0.82rem; text-transform:uppercase; letter-spacing:0.5px;">
        <i class="{{ $categories[$category] ?? 'fa-solid fa-cube' }} me-1"></i>{{ $category }}
    </h6>
    <div class="row g-3">
        @foreach($modules as $mod)
        <div class="col-xl-2 col-md-3 col-sm-4 col-6">
            <a href="{{ route('noc.config.module', ['module' => $mod['key']]) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 module-card" style="transition:all 0.2s;">
                    <div class="card-body text-center py-3 px-2">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;background:rgba(var(--bs-primary-rgb),0.08);">
                            <i class="{{ $mod['icon'] }} fa-lg" style="color:var(--primary);"></i>
                        </div>
                        <div class="fw-semibold" style="font-size:0.82rem; color:var(--bs-body-color);">{{ $mod['label'] }}</div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection

@push('styles')
<style>
.module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
</style>
@endpush
