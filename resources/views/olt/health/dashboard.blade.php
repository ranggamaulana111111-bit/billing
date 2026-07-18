@extends('layouts.app')

@section('title', 'ONU Health Dashboard')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fa-solid fa-heart-pulse text-danger"></i> ONU Health Dashboard</h2>
        <p class="text-muted mb-0">Monitoring kesehatan semua ONU secara real-time</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" onclick="refreshDashboard()">
            <i class="fa-solid fa-arrows-rotate"></i> Refresh
        </button>
        <a href="{{ route('olt.monitoring') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Monitor Gangguan
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="fa-solid fa-tower-broadcast fa-lg"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="mb-0 fw-bold">{{ $totalOnu }}</h3>
                        <small class="text-muted">Total ONU</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="fa-solid fa-circle-check fa-lg"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="mb-0 fw-bold text-success">{{ $onlineCount }}</h3>
                        <small class="text-muted">Online</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                        <i class="fa-solid fa-circle-xmark fa-lg"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="mb-0 fw-bold text-danger">{{ $offlineCount }}</h3>
                        <small class="text-muted">Offline</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="mb-0 fw-bold text-warning">{{ $warningCount }}</h3>
                        <small class="text-muted">Warning / LOS</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="height:280px;">
            <div class="card-body d-flex flex-column">
                <h6 class="card-title fw-bold mb-2"><i class="fa-solid fa-chart-pie text-primary"></i> Status Distribution</h6>
                <div class="flex-grow-1 d-flex align-items-center justify-content-center" style="min-height:0;">
                    <div style="width:200px;height:200px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="height:280px;">
            <div class="card-body d-flex flex-column">
                <h6 class="card-title fw-bold mb-2"><i class="fa-solid fa-heart text-danger"></i> Health Score Distribution</h6>
                <div class="flex-grow-1" style="min-height:0;">
                    <canvas id="healthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="height:280px;">
            <div class="card-body d-flex flex-column justify-content-center align-items-center py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div style="position:relative;width:120px;height:68px;">
                        <canvas id="gaugeCanvas" width="120" height="68"></canvas>
                        <div style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);text-align:center;">
                            <span class="fw-bold fs-5" id="avgScoreText">{{ $avgScore }}</span>
                            <br><small class="text-muted" style="font-size:0.65rem;">/ 100</small>
                        </div>
                    </div>
                    <div>
                        @php
                            $avgGrade = $avgScore >= 85 ? 'Excellent' : ($avgScore >= 70 ? 'Good' : ($avgScore >= 50 ? 'Warning' : 'Critical'));
                            $avgColor = $avgScore >= 85 ? 'success' : ($avgScore >= 70 ? 'info' : ($avgScore >= 50 ? 'warning' : 'danger'));
                        @endphp
                        <span class="badge bg-{{ $avgColor }} fs-6 px-3 py-2">{{ $avgGrade }}</span>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="text-center">
                        <span class="fw-bold text-success">{{ $scoreDistribution['excellent'] }}</span>
                        <br><small class="text-muted">Excellent</small>
                    </div>
                    <div class="text-center">
                        <span class="fw-bold text-info">{{ $scoreDistribution['good'] }}</span>
                        <br><small class="text-muted">Good</small>
                    </div>
                    <div class="text-center">
                        <span class="fw-bold text-warning">{{ $scoreDistribution['warning'] }}</span>
                        <br><small class="text-muted">Warning</small>
                    </div>
                    <div class="text-center">
                        <span class="fw-bold text-danger">{{ $scoreDistribution['critical'] }}</span>
                        <br><small class="text-muted">Critical</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="d-flex gap-2 mb-3">
            <a href="{{ route('onu-health.topology') }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-diagram-project"></i> Topologi Fiber
            </a>
            <a href="{{ route('onu-health.ping') }}" class="btn btn-outline-info btn-sm">
                <i class="fa-solid fa-network-wired"></i> Ping Monitor
            </a>
            <a href="{{ route('onu-health.speedtest') }}" class="btn btn-outline-warning btn-sm">
                <i class="fa-solid fa-speedometer"></i> Speed Test
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list"></i> Daftar ONU — Kesehatan</h6>
            </div>
            <div class="col-auto">
                <div class="input-group input-group-sm" style="width:250px;">
                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                    <input type="text" class="form-control" id="searchOnu" placeholder="Cari ONU / Pelanggan..." oninput="filterOnuTable()">
                </div>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="filterStatus" onchange="filterOnuTable()">
                    <option value="">Semua Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="los">LOS</option>
                    <option value="dying-gasp">Dying Gasp</option>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="filterHealth" onchange="filterOnuTable()">
                    <option value="">Semua Health</option>
                    <option value="Excellent">Excellent</option>
                    <option value="Good">Good</option>
                    <option value="Warning">Warning</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>ONU</th>
                        <th>Pelanggan</th>
                        <th>Status</th>
                        <th>RX Power</th>
                        <th>TX Power</th>
                        <th>Health Score</th>
                        <th>Grade</th>
                        <th>Aksi</th>
                    </tr>

                <tbody>
                    @forelse($sortedOnus as $item)
                        @php
                            $onu = $item['onu'];
                            $health = $item['health'];
                            $badge = $item['status_badge'];
                        @endphp
                        <tr data-status="{{ $onu->status ?? 'unknown' }}" data-health="{{ $health['grade'] }}">
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-{{ $badge['color'] }} me-2" style="width:10px;height:10px;border-radius:50%;padding:0;"></span>
                                    <div>
                                        <strong>{{ $onu->onu_id }}</strong>
                                        @if($onu->serial_number)
                                            <br><small class="text-muted">{{ $onu->serial_number }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($onu->customer)
                                    <span>{{ $onu->customer->name }}</span>
                                    <br><small class="text-muted">{{ $onu->customer->customer_code }}</small>
                                @else
                                    <span class="text-muted fst-italic">Unlinked</span>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $badge['color'] }}">{{ $badge['label'] }}</span></td>
                            <td>
                                @if($onu->rx_power !== null)
                                    <span class="fw-bold {{ $onu->rx_power < -28 ? 'text-danger' : ($onu->rx_power < -25 ? 'text-warning' : 'text-success') }}">
                                        {{ number_format($onu->rx_power, 1) }} dBm
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($onu->tx_power !== null)
                                    <span class="fw-bold">{{ number_format($onu->tx_power, 1) }} dBm</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:8px;width:80px;">
                                        <div class="progress-bar bg-{{ $health['color'] }}" style="width:{{ $health['score'] }}%"></div>
                                    </div>
                                    <small class="fw-bold">{{ $health['score'] }}</small>
                                </div>
                            </td>
                            <td><span class="badge bg-{{ $health['color'] }}">{{ $health['grade'] }}</span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('onu-health.detail', $onu->id) }}" class="btn btn-outline-primary" title="Detail">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </a>
                                    <a href="{{ route('onu-health.diagnosis', $onu->id) }}" class="btn btn-outline-warning" title="Diagnosis">
                                        <i class="fa-solid fa-stethoscope"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-tower-broadcast fa-2x mb-2 d-block"></i>
                                Belum ada data ONU
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusData = @json($statusDistribution);
    const scoreData = @json($scoreDistribution);

    const statusColors = {
        'online': '#198754',
        'offline': '#dc3545',
        'los': '#ffc107',
        'dying-gasp': '#fd7e14',
        'auth-failed': '#dc3545',
        'unknown': '#6c757d',
    };

    const statusLabels = Object.keys(statusData);
    const statusValues = Object.values(statusData);
    const statusColorsArr = statusLabels.map(l => statusColors[l] || '#6c757d');

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels.map(l => l.toUpperCase()),
            datasets: [{
                data: statusValues,
                backgroundColor: statusColorsArr,
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } }
            }
        }
    });

    const healthColors = ['#198754', '#0dcaf0', '#ffc107', '#dc3545'];
    const healthLabels = ['Excellent', 'Good', 'Warning', 'Critical'];
    const healthValues = [scoreData.excellent, scoreData.good, scoreData.warning, scoreData.critical];

    new Chart(document.getElementById('healthChart'), {
        type: 'bar',
        data: {
            labels: healthLabels,
            datasets: [{
                data: healthValues,
                backgroundColor: healthColors,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    drawGauge({{ $avgScore }});
});

function drawGauge(score) {
    const canvas = document.getElementById('gaugeCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    const cx = w / 2, cy = h - 4;
    const r = Math.min(cx, cy) - 8;

    ctx.clearRect(0, 0, w, h);

    const segments = [
        { end: 0.5, color: '#dc3545' },
        { end: 0.7, color: '#ffc107' },
        { end: 0.85, color: '#0dcaf0' },
        { end: 1.0, color: '#198754' },
    ];

    let start = Math.PI;
    segments.forEach(seg => {
        const end = Math.PI + (seg.end * Math.PI);
        ctx.beginPath();
        ctx.arc(cx, cy, r, start, end);
        ctx.lineWidth = 12;
        ctx.strokeStyle = seg.color;
        ctx.stroke();
        start = end;
    });

    const needleAngle = Math.PI + (score / 100) * Math.PI;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(needleAngle) * (r - 14), cy + Math.sin(needleAngle) * (r - 14));
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = '#212529';
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(cx, cy, 5, 0, 2 * Math.PI);
    ctx.fillStyle = '#212529';
    ctx.fill();
}

function filterOnuTable() {
    const search = document.getElementById('searchOnu').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const healthFilter = document.getElementById('filterHealth').value;

    document.querySelectorAll('#onuHealthTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.dataset.status || '';
        const health = row.dataset.health || '';

        const matchSearch = !search || text.includes(search);
        const matchStatus = !statusFilter || status === statusFilter;
        const matchHealth = !healthFilter || health === healthFilter;

        row.style.display = (matchSearch && matchStatus && matchHealth) ? '' : 'none';
    });
}

function refreshDashboard() {
    location.reload();
}
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
