@extends('layouts.app')

@section('title', 'MikroTik Dashboard — NOC')

@php
    function nocFormatBytes($bytes) {
        if ($bytes == 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[$i];
    }
@endphp

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-router me-2" style="color:var(--primary);"></i>MikroTik Dashboard</h2>
        <p class="section-subtitle mb-0 mt-1">
            Monitoring seluruh Router MikroTik secara real-time
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <div class="d-flex align-items-center gap-2 me-2" style="font-size:0.8rem;">
            <span class="text-muted">Auto Refresh:</span>
            <select id="autoRefreshSelect" class="form-select form-select-sm" style="width:auto;">
                <option value="0">Manual</option>
                <option value="10" selected>10 detik</option>
                <option value="30">30 detik</option>
                <option value="60">1 menit</option>
                <option value="300">5 menit</option>
            </select>
            <span id="refreshCountdown" class="text-muted" style="min-width:40px;"></span>
        </div>
        <button type="button" class="btn btn-outline-primary px-3 py-2" id="btnRefresh" title="Refresh Sekarang">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
        <a href="{{ route('noc.mikrotik-devices.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-server me-1"></i>Device Manager
        </a>
    </div>
</div>

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Pencarian</label>
                <input type="text" name="search" id="filterSearch" class="form-control form-control-sm" placeholder="Nama, host, identity..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select name="status" id="filterStatus" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Site / POP</label>
                <select name="site" id="filterSite" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['sites'] as $site)
                        <option value="{{ $site }}" {{ request('site') === $site ? 'selected' : '' }}>{{ $site }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Tag</label>
                <select name="tag" id="filterTag" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['tags'] as $tag)
                        <option value="{{ $tag }}" {{ request('tag') === $tag ? 'selected' : '' }}>{{ $tag }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Model</label>
                <select name="model" id="filterModel" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['models'] as $model)
                        <option value="{{ $model }}" {{ request('model') === $model ? 'selected' : '' }}>{{ $model }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">RouterOS</label>
                <select name="version" id="filterVersion" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['versions'] as $ver)
                        <option value="{{ $ver }}" {{ request('version') === $ver ? 'selected' : '' }}>{{ $ver }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- ═══ SUMMARY STATS ═══ --}}
<div class="bento-grid mb-4" id="summaryStats">
    <div class="span-1">
        <div class="card stat-card text-white stat-card-gradient-blue">
            <div class="stat-bg"><i class="fa-solid fa-server"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-server"></i></div>
                    <div>
                        <div class="stat-number" id="stat-total">{{ $stats['total'] }}</div>
                        <div class="stat-label">Total Router</div>
                    </div>
                </div>
                <div class="stat-details">
                    <span><i class="fa-solid fa-toggle-on"></i> {{ $stats['total'] > 0 ? round($stats['online'] / $stats['total'] * 100) : 0 }}% uptime</span>
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
                        <div class="stat-number" id="stat-online">{{ $stats['online'] }}</div>
                        <div class="stat-label">Router Online</div>
                    </div>
                </div>
                <div class="stat-details">
                    <span><i class="fa-solid fa-network-wired"></i> {{ $stats['total_interfaces_up'] }} interface up</span>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#ef4444,#dc2626);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                    <div>
                        <div class="stat-number" id="stat-offline">{{ $stats['offline'] }}</div>
                        <div class="stat-label">Router Offline</div>
                    </div>
                </div>
                <div class="stat-details">
                    <span><i class="fa-solid fa-network-wired"></i> {{ $stats['total_interfaces_down'] }} interface down</span>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">CPU Rata-rata</small>
                <h4 class="fw-bold mb-1" id="stat-avg-cpu">{{ $stats['avg_cpu'] }}%</h4>
                @if($stats['highest_cpu'])
                    <small class="text-muted">Tertinggi: {{ $stats['highest_cpu']['name'] }} ({{ $stats['highest_cpu']['cpu_load'] }}%)</small>
                @endif
                <div class="progress mt-2" style="height:4px;">
                    <div class="progress-bar bg-primary" style="width:{{ $stats['avg_cpu'] }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ROW 2: More stats --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Memory Rata-rata</small>
                <h4 class="fw-bold mb-1" id="stat-avg-mem">{{ $stats['avg_memory'] }}%</h4>
                @if($stats['highest_memory'])
                    <small class="text-muted">Tertinggi: {{ $stats['highest_memory']['name'] }} ({{ $stats['highest_memory']['memory_pct'] }}%)</small>
                @endif
                <div class="progress mt-2" style="height:4px;">
                    <div class="progress-bar bg-warning" style="width:{{ $stats['avg_memory'] }}%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">PPPoE Aktif</small>
                <h4 class="fw-bold mb-1" id="stat-ppp">{{ number_format($stats['total_ppp']) }}</h4>
                <small class="text-muted">Total di seluruh router</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Hotspot Aktif</small>
                <h4 class="fw-bold mb-1" id="stat-hotspot">{{ number_format($stats['total_hotspot']) }}</h4>
                <small class="text-muted">Total di seluruh router</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Total Traffic</small>
                <h4 class="fw-bold mb-1" id="stat-traffic">{{ nocFormatBytes($stats['total_rx'] + $stats['total_tx']) }}</h4>
                <small class="text-muted">
                    <i class="fa-solid fa-arrow-down text-success"></i> {{ nocFormatBytes($stats['total_rx']) }}
                    <i class="fa-solid fa-arrow-up text-primary ms-2"></i> {{ nocFormatBytes($stats['total_tx']) }}
                </small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-blue"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:var(--primary);"></span>
                    CPU Usage per Router
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap">
                    <canvas id="cpuChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-amber"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#f59e0b;"></span>
                    Memory Usage per Router
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap">
                    <canvas id="memoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bento-grid mb-4">
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-green"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#059669;"></span>
                    Traffic Total per Router
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="bento-card">
            <div class="bento-accent bento-accent-green"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#059669;"></span>
                    Online / Offline
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:180px;">
                    <canvas id="statusDonut"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="bento-card">
            <div class="bento-accent bento-accent-purple"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#8b5cf6;"></span>
                    Sessions
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:180px;">
                    <canvas id="sessionDonut"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ROUTER CARDS ═══ --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold" style="font-size:1rem;"><i class="fa-solid fa-server me-1"></i> Semua Router ({{ count($routerData) }})</h5>
</div>

<div class="row g-3" id="routerCards">
    @forelse($routerData as $router)
        <div class="col-xl-3 col-lg-4 col-md-6 router-card-wrapper"
             data-name="{{ strtolower($router['name'] . ' ' . ($router['identity'] ?? '')) }}"
             data-host="{{ $router['host'] }}"
             data-status="{{ $router['online'] ? 'online' : 'offline' }}"
             data-site="{{ strtolower($router['site'] ?? '') }}"
             data-model="{{ strtolower($router['model'] ?? '') }}"
             data-version="{{ strtolower($router['routeros_version'] ?? '') }}"
             data-tags="{{ strtolower(implode(' ', $router['tags'] ?? [])) }}">
            <div class="card shadow-sm border-0 h-100" style="border-left:3px solid {{ $router['online'] ? '#059669' : '#ef4444' }};">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <a href="{{ route('noc.mikrotik.detail', $router['id']) }}" class="fw-bold text-decoration-none" style="font-size:0.92rem;">
                                {{ $router['identity'] ?: $router['name'] }}
                            </a>
                            <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);">{{ $router['name'] }}</div>
                        </div>
                        <span class="badge bg-{{ $router['online'] ? 'success' : 'danger' }}" style="font-size:0.6rem;">
                            {{ $router['online'] ? 'ONLINE' : 'OFFLINE' }}
                        </span>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);margin-bottom:8px;">
                        <code>{{ $router['host'] }}:{{ $router['port'] }}</code>
                        @if($router['site']) &middot; {{ $router['site'] }} @endif
                    </div>

                    @if($router['online'])
                    <div class="row g-2 mb-2" style="font-size:0.72rem;">
                        <div class="col-4">
                            <div class="text-muted">CPU</div>
                            <div class="fw-semibold {{ $router['cpu_load'] > 80 ? 'text-danger' : ($router['cpu_load'] > 60 ? 'text-warning' : 'text-success') }}">
                                {{ $router['cpu_load'] }}%
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">RAM</div>
                            <div class="fw-semibold {{ $router['memory_pct'] > 80 ? 'text-danger' : ($router['memory_pct'] > 60 ? 'text-warning' : 'text-success') }}">
                                {{ $router['memory_pct'] }}%
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">Disk</div>
                            <div class="fw-semibold {{ $router['hdd_pct'] > 80 ? 'text-danger' : ($router['hdd_pct'] > 60 ? 'text-warning' : 'text-success') }}">
                                {{ $router['hdd_pct'] }}%
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-2" style="font-size:0.72rem;">
                        <div class="col-4">
                            <div class="text-muted">Uptime</div>
                            <div class="fw-semibold">{{ $router['uptime'] ?? '-' }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">Interfaces</div>
                            <div class="fw-semibold">
                                <span class="text-success">{{ $router['interfaces_up'] }}</span>/<span class="text-danger">{{ $router['interfaces_down'] }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">Latency</div>
                            <div class="fw-semibold">{{ $router['latency'] ? number_format($router['latency'], 1).'ms' : '-' }}</div>
                        </div>
                    </div>
                    <div class="row g-2" style="font-size:0.72rem;">
                        <div class="col-4">
                            <div class="text-muted">PPPoE</div>
                            <div class="fw-semibold text-primary">{{ $router['ppp_active'] }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">Hotspot</div>
                            <div class="fw-semibold" style="color:#8b5cf6;">{{ $router['hotspot_active'] }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted">Traffic</div>
                            <div class="fw-semibold" style="font-size:0.68rem;">
                                {{ nocFormatBytes($router['total_rx'] + $router['total_tx']) }}
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="text-center py-2" style="font-size:0.75rem;color:rgba(255,255,255,0.35);">
                        @if($router['last_seen'])
                            <i class="fa-solid fa-clock me-1"></i>Terakhir online: {{ \Carbon\Carbon::parse($router['last_seen'])->diffForHumans() }}
                        @else
                            <i class="fa-solid fa-question-circle me-1"></i>Belum pernah terhubung
                        @endif
                    </div>
                    @endif

                    <div class="d-flex gap-1 mt-2">
                        <a href="{{ route('noc.mikrotik.detail', $router['id']) }}" class="btn btn-sm btn-outline-primary flex-fill" style="font-size:0.7rem;">
                            <i class="fa-solid fa-eye me-1"></i>Detail
                        </a>
                        <a href="{{ route('noc.mikrotik-devices.edit', $router['id']) }}" class="btn btn-sm btn-outline-secondary" style="font-size:0.7rem;" title="Device Manager">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5">
            <div style="color:rgba(255,255,255,0.3);">
                <i class="fa-solid fa-router" style="font-size:2.5rem;"></i>
                <p class="mt-3 mb-0">Belum ada router terdaftar</p>
                <a href="{{ route('noc.mikrotik-devices.create') }}" class="btn btn-sm btn-primary mt-3">
                    <i class="fa-solid fa-plus me-1"></i>Tambah Router
                </a>
            </div>
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function() {
    const routerData = @json($routerData);
    const stats = @json($stats);

    let cpuChart, memoryChart, trafficChart, statusDonut, sessionDonut;
    let refreshTimer = null;
    let countdownTimer = null;
    let countdown = 10;
    let chartHistory = { labels: [], cpu: {}, memory: {}, rx: {}, tx: {} };
    const MAX_HISTORY = 30;

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function initCharts() {
        const onlineRouters = routerData.filter(r => r.online);
        const labels = onlineRouters.map(r => r.identity || r.name);

        cpuChart = new Chart(document.getElementById('cpuChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'CPU %',
                    data: onlineRouters.map(r => r.cpu_load || 0),
                    backgroundColor: onlineRouters.map(r => (r.cpu_load || 0) > 80 ? '#ef4444' : ((r.cpu_load || 0) > 60 ? '#f59e0b' : '#10b981')),
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8 } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => v + '%' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 } }
                }
            }
        });

        memoryChart = new Chart(document.getElementById('memoryChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Memory %',
                    data: onlineRouters.map(r => r.memory_pct || 0),
                    backgroundColor: onlineRouters.map(r => (r.memory_pct || 0) > 80 ? '#ef4444' : ((r.memory_pct || 0) > 60 ? '#f59e0b' : '#2563eb')),
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8 } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => v + '%' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 } }
                }
            }
        });

        trafficChart = new Chart(document.getElementById('trafficChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Download', data: onlineRouters.map(r => r.total_rx || 0), backgroundColor: '#10b981', borderRadius: 6, barPercentage: 0.5 },
                    { label: 'Upload', data: onlineRouters.map(r => r.total_tx || 0), backgroundColor: '#2563eb', borderRadius: 6, barPercentage: 0.5 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, pointStyleWidth: 6, font: { size: 10 }, color: '#94a3b8' } },
                    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8, callbacks: { label: ctx => ctx.dataset.label + ': ' + formatBytes(ctx.raw) } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => formatBytes(v) } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 } }
                }
            }
        });

        statusDonut = new Chart(document.getElementById('statusDonut'), {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline'],
                datasets: [{
                    data: [stats.online, stats.offline],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 6, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#94a3b8' } },
                    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8 }
                }
            }
        });

        sessionDonut = new Chart(document.getElementById('sessionDonut'), {
            type: 'doughnut',
            data: {
                labels: ['PPPoE', 'Hotspot'],
                datasets: [{
                    data: [stats.total_ppp, stats.total_hotspot],
                    backgroundColor: ['#2563eb', '#8b5cf6'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 6, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#94a3b8' } },
                    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8 }
                }
            }
        });
    }

    function updateCharts(data) {
        const onlineRouters = data.routers.filter(r => r.online);
        const labels = onlineRouters.map(r => r.identity || r.name);

        cpuChart.data.labels = labels;
        cpuChart.data.datasets[0].data = onlineRouters.map(r => r.cpu_load || 0);
        cpuChart.data.datasets[0].backgroundColor = onlineRouters.map(r => (r.cpu_load || 0) > 80 ? '#ef4444' : ((r.cpu_load || 0) > 60 ? '#f59e0b' : '#10b981'));
        cpuChart.update('none');

        memoryChart.data.labels = labels;
        memoryChart.data.datasets[0].data = onlineRouters.map(r => r.memory_pct || 0);
        memoryChart.data.datasets[0].backgroundColor = onlineRouters.map(r => (r.memory_pct || 0) > 80 ? '#ef4444' : ((r.memory_pct || 0) > 60 ? '#f59e0b' : '#2563eb'));
        memoryChart.update('none');

        trafficChart.data.labels = labels;
        trafficChart.data.datasets[0].data = onlineRouters.map(r => r.total_rx || 0);
        trafficChart.data.datasets[1].data = onlineRouters.map(r => r.total_tx || 0);
        trafficChart.update('none');

        statusDonut.data.datasets[0].data = [data.stats.online, data.stats.offline];
        statusDonut.update('none');

        sessionDonut.data.datasets[0].data = [data.stats.total_ppp, data.stats.total_hotspot];
        sessionDonut.update('none');
    }

    function updateStats(data) {
        document.getElementById('stat-total').textContent = data.stats.total;
        document.getElementById('stat-online').textContent = data.stats.online;
        document.getElementById('stat-offline').textContent = data.stats.offline;
        document.getElementById('stat-avg-cpu').textContent = data.stats.avg_cpu + '%';
        document.getElementById('stat-avg-mem').textContent = data.stats.avg_memory + '%';
        document.getElementById('stat-ppp').textContent = Number(data.stats.total_ppp).toLocaleString();
        document.getElementById('stat-hotspot').textContent = Number(data.stats.total_hotspot).toLocaleString();
        document.getElementById('stat-traffic').textContent = formatBytes(data.stats.total_rx + data.stats.total_tx);

        const progCpu = document.querySelector('#stat-avg-cpu').closest('.card').querySelector('.progress-bar');
        if (progCpu) progCpu.style.width = data.stats.avg_cpu + '%';
        const progMem = document.querySelector('#stat-avg-mem').closest('.card').querySelector('.progress-bar');
        if (progMem) progMem.style.width = data.stats.avg_memory + '%';
    }

    function updateRouterCards(data) {
        const container = document.getElementById('routerCards');
        if (!container) return;

        container.innerHTML = '';
        data.routers.forEach(r => {
            const border = r.online ? '#059669' : '#ef4444';
            let bodyHtml = '';

            if (r.online) {
                const cpuColor = (r.cpu_load || 0) > 80 ? 'text-danger' : ((r.cpu_load || 0) > 60 ? 'text-warning' : 'text-success');
                const memColor = (r.memory_pct || 0) > 80 ? 'text-danger' : ((r.memory_pct || 0) > 60 ? 'text-warning' : 'text-success');
                const hddColor = (r.hdd_pct || 0) > 80 ? 'text-danger' : ((r.hdd_pct || 0) > 60 ? 'text-warning' : 'text-success');

                bodyHtml = `
                    <div class="row g-2 mb-2" style="font-size:0.72rem;">
                        <div class="col-4"><div class="text-muted">CPU</div><div class="fw-semibold ${cpuColor}">${r.cpu_load}%</div></div>
                        <div class="col-4"><div class="text-muted">RAM</div><div class="fw-semibold ${memColor}">${r.memory_pct}%</div></div>
                        <div class="col-4"><div class="text-muted">Disk</div><div class="fw-semibold ${hddColor}">${r.hdd_pct}%</div></div>
                    </div>
                    <div class="row g-2 mb-2" style="font-size:0.72rem;">
                        <div class="col-4"><div class="text-muted">Uptime</div><div class="fw-semibold">${r.uptime || '-'}</div></div>
                        <div class="col-4"><div class="text-muted">Interfaces</div><div class="fw-semibold"><span class="text-success">${r.interfaces_up}</span>/<span class="text-danger">${r.interfaces_down}</span></div></div>
                        <div class="col-4"><div class="text-muted">Latency</div><div class="fw-semibold">${r.latency ? r.latency.toFixed(1)+'ms' : '-'}</div></div>
                    </div>
                    <div class="row g-2" style="font-size:0.72rem;">
                        <div class="col-4"><div class="text-muted">PPPoE</div><div class="fw-semibold text-primary">${r.ppp_active}</div></div>
                        <div class="col-4"><div class="text-muted">Hotspot</div><div class="fw-semibold" style="color:#8b5cf6;">${r.hotspot_active}</div></div>
                        <div class="col-4"><div class="text-muted">Traffic</div><div class="fw-semibold" style="font-size:0.68rem;">${formatBytes(r.total_rx + r.total_tx)}</div></div>
                    </div>`;
            } else {
                const lastSeen = r.last_seen ? new Date(r.last_seen).toLocaleDateString('id-ID') + ' ' + new Date(r.last_seen).toLocaleTimeString('id-ID') : null;
                bodyHtml = `<div class="text-center py-2" style="font-size:0.75rem;color:rgba(255,255,255,0.35);">
                    ${lastSeen ? '<i class="fa-solid fa-clock me-1"></i>Terakhir: ' + lastSeen : '<i class="fa-solid fa-question-circle me-1"></i>Belum pernah terhubung'}
                </div>`;
            }

            container.innerHTML += `
                <div class="col-xl-3 col-lg-4 col-md-6 router-card-wrapper"
                     data-name="${(r.name + ' ' + (r.identity || '')).toLowerCase()}"
                     data-host="${r.host}" data-status="${r.online ? 'online' : 'offline'}"
                     data-site="${(r.site || '').toLowerCase()}" data-model="${(r.model || '').toLowerCase()}"
                     data-version="${(r.routeros_version || '').toLowerCase()}" data-tags="${(r.tags || []).join(' ').toLowerCase()}">
                    <div class="card shadow-sm border-0 h-100" style="border-left:3px solid ${border};">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <a href="/noc/mikrotik/${r.id}" class="fw-bold text-decoration-none" style="font-size:0.92rem;">${r.identity || r.name}</a>
                                    <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);">${r.name}</div>
                                </div>
                                <span class="badge bg-${r.online ? 'success' : 'danger'}" style="font-size:0.6rem;">${r.online ? 'ONLINE' : 'OFFLINE'}</span>
                            </div>
                            <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);margin-bottom:8px;">
                                <code>${r.host}:${r.port}</code>${r.site ? ' · ' + r.site : ''}
                            </div>
                            ${bodyHtml}
                            <div class="d-flex gap-1 mt-2">
                                <a href="/noc/mikrotik/${r.id}" class="btn btn-sm btn-outline-primary flex-fill" style="font-size:0.7rem;"><i class="fa-solid fa-eye me-1"></i>Detail</a>
                                <a href="/noc/mikrotik-devices/${r.id}/edit" class="btn btn-sm btn-outline-secondary" style="font-size:0.7rem;" title="Device Manager"><i class="fa-solid fa-gear"></i></a>
                            </div>
                        </div>
                    </div>
                </div>`;
        });
    }

    function applyFilters() {
        const search = document.getElementById('filterSearch').value.toLowerCase();
        const status = document.getElementById('filterStatus').value;
        const site = document.getElementById('filterSite').value.toLowerCase();
        const tag = document.getElementById('filterTag').value.toLowerCase();
        const model = document.getElementById('filterModel').value.toLowerCase();
        const version = document.getElementById('filterVersion').value.toLowerCase();

        document.querySelectorAll('.router-card-wrapper').forEach(card => {
            let show = true;
            if (search && !card.dataset.name.includes(search) && !card.dataset.host.includes(search)) show = false;
            if (status && card.dataset.status !== status) show = false;
            if (site && card.dataset.site !== site) show = false;
            if (tag && !card.dataset.tags.includes(tag)) show = false;
            if (model && card.dataset.model !== model) show = false;
            if (version && card.dataset.version !== version) show = false;
            card.style.display = show ? '' : 'none';
        });
    }

    async function fetchLiveData() {
        try {
            const params = new URLSearchParams();
            const search = document.getElementById('filterSearch').value;
            const status = document.getElementById('filterStatus').value;
            const site = document.getElementById('filterSite').value;
            const tag = document.getElementById('filterTag').value;
            const model = document.getElementById('filterModel').value;
            const version = document.getElementById('filterVersion').value;
            if (search) params.set('search', search);
            if (status) params.set('status', status);
            if (site) params.set('site', site);
            if (tag) params.set('tag', tag);
            if (model) params.set('model', model);
            if (version) params.set('version', version);

            const resp = await fetch('{{ route("noc.mikrotik.live-api") }}?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!resp.ok) return;
            const data = await resp.json();

            updateStats(data);
            updateCharts(data);
            updateRouterCards(data);
            applyFilters();
        } catch (e) {
            console.error('Live refresh error:', e);
        }
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        const seconds = parseInt(document.getElementById('autoRefreshSelect').value);
        if (seconds <= 0) {
            document.getElementById('refreshCountdown').textContent = '';
            return;
        }
        countdown = seconds;
        document.getElementById('refreshCountdown').textContent = countdown + 's';

        countdownTimer = setInterval(() => {
            countdown--;
            document.getElementById('refreshCountdown').textContent = countdown + 's';
            if (countdown <= 0) {
                fetchLiveData();
                countdown = seconds;
            }
        }, 1000);
    }

    function stopAutoRefresh() {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    document.getElementById('btnRefresh').addEventListener('click', fetchLiveData);
    document.getElementById('autoRefreshSelect').addEventListener('change', startAutoRefresh);
    document.getElementById('filterSearch').addEventListener('input', applyFilters);
    document.getElementById('filterStatus').addEventListener('change', applyFilters);
    document.getElementById('filterSite').addEventListener('change', applyFilters);
    document.getElementById('filterTag').addEventListener('change', applyFilters);
    document.getElementById('filterModel').addEventListener('change', applyFilters);
    document.getElementById('filterVersion').addEventListener('change', applyFilters);

    initCharts();
    startAutoRefresh();
})();
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
