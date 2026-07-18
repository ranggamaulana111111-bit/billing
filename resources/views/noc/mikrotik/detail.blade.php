@extends('layouts.app')

@section('title', ($router->identity ?: $router->name) . ' — MikroTik Detail')

@php
    function nocDetailFormatBytes($bytes) {
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
        <h2 class="mb-0">
            <i class="fa-solid fa-router me-2" style="color:var(--primary);"></i>{{ $data['identity'] ?: $router->name }}
            <span class="badge bg-{{ $data['online'] ? 'success' : 'danger' }} ms-2" style="font-size:0.65rem;vertical-align:middle;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:{{ $data['online'] ? '#fff' : 'rgba(255,255,255,0.5)' }};margin-right:4px;{{ $data['online'] ? 'animation:pulse 1.5s infinite;' : '' }}"></span>
                {{ $data['online'] ? 'ONLINE' : 'OFFLINE' }}
            </span>
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <code>{{ $router->host }}:{{ $router->port }}</code>
            @if($data['board_name']) &mdash; {{ $data['board_name'] }} @endif
            @if($router->site) &mdash; {{ $router->site }} @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <span class="badge bg-success me-2" id="live-badge" style="font-size:0.65rem;">
            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
        </span>
        <a href="{{ route('noc.mikrotik.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
        </a>
        <a href="{{ route('noc.mikrotik-devices.edit', $router) }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-pen me-1"></i>Edit
        </a>
    </div>
</div>

@if($data['error'] && !$data['online'])
    <div class="alert alert-custom alert-danger mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
        Router offline — {{ $data['error'] }}
    </div>
@endif

{{-- ═══ SYSTEM INFO ═══ --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header border-0" style="background:rgba(37,99,235,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-microchip me-2" style="color:var(--primary);"></i>System Information</h6>
            </div>
            <div class="card-body">
                @if($data['online'])
                <div class="row g-3">
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Identity</div>
                        <div class="fw-semibold">{{ $data['identity'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Hostname</div>
                        <div class="fw-semibold">{{ $router->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">IP Address</div>
                        <div class="fw-semibold"><code>{{ $router->host }}:{{ $router->port }}</code></div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Serial Number</div>
                        <div class="fw-semibold"><code>{{ $data['serial_number'] ?: '-' }}</code></div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Model</div>
                        <div class="fw-semibold">{{ $data['model'] ?: $data['board_name'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Architecture</div>
                        <div class="fw-semibold">{{ $data['architecture'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">RouterOS Version</div>
                        <div class="fw-semibold">{{ $data['version'] ?: $data['routeros_version'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Board Name</div>
                        <div class="fw-semibold">{{ $data['board_name'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Uptime</div>
                        <div class="fw-semibold">{{ $data['uptime'] ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Current Time</div>
                        <div class="fw-semibold" id="currentTime">-</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Timezone</div>
                        <div class="fw-semibold">{{ $router->timezone ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Latency</div>
                        <div class="fw-semibold">{{ $data['latency'] ? number_format($data['latency'], 1) . 'ms' : '-' }}</div>
                    </div>
                </div>
                @else
                <div class="text-center py-4" style="color:rgba(255,255,255,0.35);">
                    <i class="fa-solid fa-cloud" style="font-size:2rem;"></i>
                    <p class="mt-2 mb-0">Router offline — data system tidak tersedia</p>
                    @if($data['last_seen'])
                        <small>Terakhir online: {{ \Carbon\Carbon::parse($data['last_seen'])->diffForHumans() }}</small>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- RESOURCE USAGE --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(5,150,105,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-gauge-high me-2" style="color:#059669;"></i>Resource Usage</h6>
            </div>
            <div class="card-body">
                @if($data['online'])
                {{-- CPU --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                        <span class="fw-semibold">CPU</span>
                        <span class="{{ $data['cpu_load'] > 80 ? 'text-danger' : ($data['cpu_load'] > 60 ? 'text-warning' : 'text-success') }}">{{ $data['cpu_load'] }}%</span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar bg-{{ $data['cpu_load'] > 80 ? 'danger' : ($data['cpu_load'] > 60 ? 'warning' : 'success') }}" style="width:{{ $data['cpu_load'] }}%"></div>
                    </div>
                </div>
                {{-- Memory --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                        <span class="fw-semibold">Memory</span>
                        <span class="{{ $data['memory_pct'] > 80 ? 'text-danger' : ($data['memory_pct'] > 60 ? 'text-warning' : 'text-primary') }}">{{ $data['memory_pct'] }}%</span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar bg-{{ $data['memory_pct'] > 80 ? 'danger' : ($data['memory_pct'] > 60 ? 'warning' : 'primary') }}" style="width:{{ $data['memory_pct'] }}%"></div>
                    </div>
                    <div style="font-size:0.72rem;color:rgba(255,255,255,0.4);">{{ nocDetailFormatBytes($data['total_memory'] - $data['free_memory']) }} / {{ nocDetailFormatBytes($data['total_memory']) }}</div>
                </div>
                {{-- Storage --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                        <span class="fw-semibold">Storage</span>
                        <span class="{{ $data['hdd_pct'] > 80 ? 'text-danger' : ($data['hdd_pct'] > 60 ? 'text-warning' : 'text-info') }}">{{ $data['hdd_pct'] }}%</span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar bg-{{ $data['hdd_pct'] > 80 ? 'danger' : ($data['hdd_pct'] > 60 ? 'warning' : 'info') }}" style="width:{{ $data['hdd_pct'] }}%"></div>
                    </div>
                    <div style="font-size:0.72rem;color:rgba(255,255,255,0.4);">{{ nocDetailFormatBytes($data['total_hdd'] - $data['free_hdd']) }} / {{ nocDetailFormatBytes($data['total_hdd']) }}</div>
                </div>
                @else
                <div class="text-center py-3" style="color:rgba(255,255,255,0.35);font-size:0.85rem;">
                    <i class="fa-solid fa-chart-simple me-1"></i>Data tidak tersedia
                </div>
                @endif
            </div>
        </div>

        {{-- SESSIONS --}}
        <div class="card shadow-sm border-0">
            <div class="card-header border-0" style="background:rgba(139,92,246,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-users me-2" style="color:#8b5cf6;"></i>Sessions</h6>
            </div>
            <div class="card-body">
                @if($data['online'])
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-center p-2" style="background:rgba(37,99,235,0.06);border-radius:10px;">
                            <div class="fw-bold" style="font-size:1.3rem;color:#2563eb;">{{ $data['ppp_active'] }}</div>
                            <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);">PPPoE Aktif</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2" style="background:rgba(139,92,246,0.06);border-radius:10px;">
                            <div class="fw-bold" style="font-size:1.3rem;color:#8b5cf6;">{{ $data['hotspot_active'] }}</div>
                            <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Hotspot Aktif</div>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-3" style="color:rgba(255,255,255,0.35);font-size:0.85rem;">
                    <i class="fa-solid fa-users me-1"></i>Data tidak tersedia
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══ INTERFACES ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background:rgba(37,99,235,0.06);">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>Interfaces ({{ $data['interfaces_total'] }} total — <span class="text-success">{{ $data['interfaces_up'] }} up</span>, <span class="text-danger">{{ $data['interfaces_down'] }} down</span>)</h6>
        <span class="badge bg-success" style="font-size:0.6rem;">
            <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;animation:pulse 1.5s infinite;"></span>LIVE
        </span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>RX Total</th>
                        <th>TX Total</th>
                        <th>RX Rate</th>
                        <th>TX Rate</th>
                    </tr>

                <tbody id="interfaces-tbody">
                    @if($data['online'] && !empty($data['interfaces']))
                        @foreach($data['interfaces'] as $iface)
                            <tr>
                                <td class="fw-semibold">{{ $iface['name'] ?? '-' }}</td>
                                <td><span class="badge bg-secondary" style="font-size:0.68rem;">{{ $iface['type'] ?? '-' }}</span></td>
                                <td>
                                    @if(isset($iface['running']) && $iface['running'] === 'true')
                                        <span class="badge bg-success" style="font-size:0.65rem;">UP</span>
                                    @else
                                        <span class="badge bg-danger" style="font-size:0.65rem;">DOWN</span>
                                    @endif
                                </td>
                                <td>{{ nocDetailFormatBytes($iface['rx-byte'] ?? 0) }}</td>
                                <td>{{ nocDetailFormatBytes($iface['tx-byte'] ?? 0) }}</td>
                                <td id="rx-rate-{{ Str::slug($iface['name'] ?? '') }}">-</td>
                                <td id="tx-rate-{{ Str::slug($iface['name'] ?? '') }}">-</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                @if(!$data['online']) Router offline — data interface tidak tersedia @else Belum ada data @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ TRAFFIC SUMMARY ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-green"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#059669;"></span>
                    Traffic Summary
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="row g-3">
                    <div class="col-md-3 text-center">
                        <div class="fw-bold" style="font-size:1.5rem;">{{ nocDetailFormatBytes($data['total_rx']) }}</div>
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Total Download</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="fw-bold" style="font-size:1.5rem;">{{ nocDetailFormatBytes($data['total_tx']) }}</div>
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Total Upload</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="fw-bold text-primary" style="font-size:1.5rem;">{{ $data['ppp_active'] }}</div>
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">PPPoE Aktif</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="fw-bold" style="font-size:1.5rem;color:#8b5cf6;">{{ $data['hotspot_active'] }}</div>
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Hotspot Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-blue"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:var(--primary);"></span>
                    Interface Traffic
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:200px;">
                    <canvas id="interfaceTrafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ QUICK ACTIONS ═══ --}}
@if($data['online'])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span class="fw-bold" style="font-size:0.92rem;">Aksi Cepat</span>
    </div>
    <div class="card-body">
        <div class="d-flex gap-3 flex-wrap">
            <a href="{{ route('mikrotik.profiles', ['router' => $router->id]) }}" class="btn btn-outline-primary">
                <i class="fa-solid fa-layer-group me-1"></i>Profiles
            </a>
            <a href="{{ route('mikrotik.ppp', ['router' => $router->id]) }}" class="btn btn-outline-success">
                <i class="fa-solid fa-network-wired me-1"></i>PPP Secrets
            </a>
            <a href="{{ route('mikrotik.hotspot-users', ['router' => $router->id]) }}" class="btn btn-outline-info">
                <i class="fa-solid fa-wifi me-1"></i>Hotspot Users
            </a>
            <a href="{{ route('mikrotik.ppp-profiles', ['router' => $router->id]) }}" class="btn btn-outline-info">
                <i class="fa-solid fa-user-group me-1"></i>PPPoE Profiles
            </a>
            <a href="{{ route('mikrotik.queues', ['router' => $router->id]) }}" class="btn btn-outline-warning">
                <i class="fa-solid fa-gauge-high me-1"></i>Queue Bandwidth
            </a>
            <form method="POST" action="{{ route('mikrotik.backup', ['router' => $router->id]) }}" class="d-inline" onsubmit="return confirm('Buat backup MikroTik sekarang?')">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Backup
                </button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- NOTES --}}
@if($router->notes)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="fa-solid fa-sticky-note me-1"></i> Catatan</h6>
        <p class="mb-0" style="white-space:pre-line;">{{ $router->notes }}</p>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function() {
    const routerId = {{ $router->id }};
    const online = {{ $data['online'] ? 'true' : 'false' }};

    if (!online) return;

    const interfaces = @json($data['interfaces']);
    const interfaceNames = interfaces.filter(i => i.running === 'true').map(i => i.name);
    const prevRx = {};
    const prevTx = {};
    const prevTime = {};

    let chart;
    const chartData = { labels: [], datasets: [] };

    if (interfaceNames.length > 0) {
        const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'];
        chartData.datasets = interfaceNames.slice(0, 8).map((name, i) => ({
            label: name,
            data: [],
            borderColor: colors[i % colors.length],
            tension: 0.4,
            pointRadius: 0,
            fill: false,
            borderWidth: 2,
        }));

        chart = new Chart(document.getElementById('interfaceTrafficChart'), {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 6, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#94a3b8' } },
                    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 8, cornerRadius: 6, callbacks: { label: ctx => ctx.dataset.label + ': ' + formatRate(ctx.raw) } }
                },
                scales: {
                    y: { title: { display: true, text: 'bps', color: '#94a3b8' }, beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => formatRate(v) } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 10 } }
                }
            }
        });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function formatRate(bps) {
        if (!bps || bps === 0) return '0 bps';
        if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
        if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
        if (bps >= 1e3) return (bps / 1e3).toFixed(2) + ' Kbps';
        return bps.toFixed(0) + ' bps';
    }

    async function refreshDetail() {
        try {
            const resp = await fetch('{{ url("/api/noc/mikrotik") }}/' + routerId + '/live', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!resp.ok) return;
            const data = await resp.json();

            const now = new Date();
            const timeLabel = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            if (data.interfaces && chart) {
                data.interfaces.forEach(iface => {
                    if (iface.running !== 'true') return;
                    const name = iface.name;
                    const idx = interfaceNames.indexOf(name);
                    if (idx < 0 || idx >= chart.data.datasets.length) return;

                    const curRx = parseInt(iface['rx-byte'] || 0);
                    const curTx = parseInt(iface['tx-byte'] || 0);
                    const curTime = Date.now();

                    if (prevRx[name] !== undefined) {
                        const elapsed = (curTime - prevTime[name]) / 1000;
                        if (elapsed > 0) {
                            const rxRate = Math.max(0, (curRx - prevRx[name]) * 8 / elapsed);
                            const txRate = Math.max(0, (curTx - prevTx[name]) * 8 / elapsed);
                            chart.data.datasets[idx].data.push(rxRate);
                            if (chart.data.datasets[idx].data.length > 60) {
                                chart.data.datasets[idx].data.shift();
                            }
                        }
                    }

                    prevRx[name] = curRx;
                    prevTx[name] = curTx;
                    prevTime[name] = curTime;

                    const rxEl = document.getElementById('rx-rate-' + name.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
                    const txEl = document.getElementById('tx-rate-' + name.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
                    if (rxEl && prevRx[name] !== undefined) {
                        const elapsed = (Date.now() - prevTime[name]) / 1000 || 1;
                        rxEl.textContent = formatRate(Math.max(0, (curRx - (prevRx[name] || curRx)) * 8 / elapsed));
                        txEl.textContent = formatRate(Math.max(0, (curTx - (prevTx[name] || curTx)) * 8 / elapsed));
                    }
                });

                chart.data.labels.push(timeLabel);
                if (chart.data.labels.length > 60) chart.data.labels.shift();
                chart.update('none');
            }
        } catch (e) {
            console.error('Detail refresh error:', e);
        }
    }

    setInterval(refreshDetail, 3000);
})();
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
