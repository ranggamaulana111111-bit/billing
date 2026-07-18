@extends('layouts.app')

@section('title', 'Interface Center — Dashboard')

@php
    function ifFmtBytes($bytes) {
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
        <h2 class="mb-0"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>Interface Center</h2>
        <p class="section-subtitle mb-0 mt-1">
            Monitoring & konfigurasi seluruh interface MikroTik
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <div class="d-flex align-items-center gap-2 me-2" style="font-size:0.8rem;">
            <span class="text-muted">Auto Refresh:</span>
            <select id="autoRefreshSelect" class="form-select form-select-sm" style="width:auto;">
                <option value="0">Manual</option>
                <option value="10" selected>10 detik</option>
                <option value="30">30 detik</option>
                <option value="60">1 menit</option>
            </select>
            <span id="refreshCountdown" class="text-muted" style="min-width:40px;"></span>
        </div>
        <button type="button" class="btn btn-outline-primary px-3 py-2" id="btnRefresh">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
        <a href="{{ route('noc.interface-center.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-list me-1"></i>Semua Interface
        </a>
    </div>
</div>

{{-- ═══ SUMMARY STATS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white stat-card-gradient-blue">
            <div class="stat-bg"><i class="fa-solid fa-network-wired"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-network-wired"></i></div>
                    <div>
                        <div class="stat-number">{{ $stats['total'] }}</div>
                        <div class="stat-label">Total Interface</div>
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
                        <div class="stat-number">{{ $stats['up'] }}</div>
                        <div class="stat-label">Interface Up</div>
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
                        <div class="stat-number">{{ $stats['down'] }}</div>
                        <div class="stat-label">Interface Down</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Interface Disabled</small>
                <h4 class="fw-bold mb-1">{{ $stats['disabled'] }}</h4>
                <small class="text-muted">Non-aktif oleh admin</small>
            </div>
        </div>
    </div>
</div>

{{-- ROW 2: Traffic stats --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Total Download</small>
                <h4 class="fw-bold mb-1 text-success">{{ ifFmtBytes($stats['total_rx']) }}</h4>
                <div class="progress mt-2" style="height:4px;">
                    <div class="progress-bar bg-success" style="width:100%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Total Upload</small>
                <h4 class="fw-bold mb-1 text-primary">{{ ifFmtBytes($stats['total_tx']) }}</h4>
                <div class="progress mt-2" style="height:4px;">
                    <div class="progress-bar bg-primary" style="width:100%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Router Terbanyak</small>
                <h4 class="fw-bold mb-1" style="font-size:1rem;">
                    @if($stats['top_router'])
                        {{ $stats['top_router']['router_name'] }}
                    @else
                        -
                    @endif
                </h4>
                <small class="text-muted">{{ $stats['top_router']['count'] ?? 0 }} interface</small>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Traffic Tertinggi</small>
                <h4 class="fw-bold mb-1" style="font-size:1rem;">
                    @if($stats['top_traffic']['bytes'] > 0)
                        {{ $stats['top_traffic']['name'] }}
                    @else
                        -
                    @endif
                </h4>
                <small class="text-muted">{{ $stats['top_traffic']['router'] ?? '-' }} — {{ ifFmtBytes($stats['top_traffic']['bytes'] ?? 0) }}</small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ INTERFACE TYPE CHART ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-blue"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:var(--primary);"></span>
                    Interface per Tipe
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:200px;">
                    <canvas id="typeChart"></canvas>
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
                    Status Ringkasan
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:180px;">
                    <canvas id="statusDonut"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ INTERFACE PER ROUTER ═══ --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold" style="font-size:1rem;"><i class="fa-solid fa-server me-1"></i> Interface per Router</h5>
    <a href="{{ route('noc.interface-center.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="fa-solid fa-list me-1"></i>Lihat Semua
    </a>
</div>

<div class="row g-3">
    @forelse($stats['router_counts'] as $rc)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100" style="border-left:3px solid var(--primary);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <a href="{{ route('noc.interface-center.index', ['router' => $rc['router_id']]) }}" class="fw-bold text-decoration-none" style="font-size:0.92rem;">
                                {{ $rc['router_name'] }}
                            </a>
                        </div>
                        <span class="badge bg-primary" style="font-size:0.65rem;">{{ $rc['count'] }} iface</span>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-4">
            <div class="text-muted">
                <i class="fa-solid fa-network-wired" style="font-size:2rem;"></i>
                <p class="mt-2 mb-0">Belum ada data interface</p>
            </div>
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function() {
    const stats = @json($stats);
    const types = @json($interfaceTypes);

    // Type bar chart
    const typeLabels = Object.keys(types);
    const typeValues = Object.values(types);
    const typeColors = typeLabels.map((_, i) => {
        const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6', '#6366f1', '#84cc16'];
        return colors[i % colors.length];
    });

    if (typeLabels.length > 0) {
        new Chart(document.getElementById('typeChart'), {
            type: 'bar',
            data: {
                labels: typeLabels,
                datasets: [{
                    label: 'Jumlah',
                    data: typeValues,
                    backgroundColor: typeColors,
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45 } }
                }
            }
        });
    }

    // Status donut
    new Chart(document.getElementById('statusDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Up', 'Down', 'Disabled'],
            datasets: [{
                data: [stats.up, stats.down, stats.disabled],
                backgroundColor: ['#10b981', '#ef4444', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 6, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#94a3b8' } }
            }
        }
    });

    // Auto-refresh
    let refreshTimer = null;
    let countdownTimer = null;
    let countdown = 10;

    function startAutoRefresh() {
        stopAutoRefresh();
        const seconds = parseInt(document.getElementById('autoRefreshSelect').value);
        if (seconds <= 0) { document.getElementById('refreshCountdown').textContent = ''; return; }
        countdown = seconds;
        document.getElementById('refreshCountdown').textContent = countdown + 's';
        countdownTimer = setInterval(() => {
            countdown--;
            document.getElementById('refreshCountdown').textContent = countdown + 's';
            if (countdown <= 0) { location.reload(); countdown = seconds; }
        }, 1000);
    }

    function stopAutoRefresh() {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    document.getElementById('btnRefresh')?.addEventListener('click', () => location.reload());
    document.getElementById('autoRefreshSelect')?.addEventListener('change', startAutoRefresh);
    startAutoRefresh();
})();
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
