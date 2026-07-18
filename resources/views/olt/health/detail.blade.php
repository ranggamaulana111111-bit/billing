@extends('layouts.app')

@section('title', 'Detail ONU — ' . ($onu->onu_id ?? ''))

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fa-solid fa-circle-info text-primary"></i> Detail ONU</h2>
        <p class="text-muted mb-0">{{ $onu->onu_id }} — {{ $onu->customer->name ?? 'Unlinked' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('onu-health.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <a href="{{ route('onu-health.diagnosis', $onu->id) }}" class="btn btn-outline-warning btn-sm">
            <i class="fa-solid fa-stethoscope"></i> Diagnosis
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-4">
                <div class="mb-2">
                    <span class="badge bg-{{ $statusBadge['color'] }} fs-6 px-3 py-2">{{ $statusBadge['label'] }}</span>
                </div>
                <h4 class="fw-bold mb-1">{{ $onu->onu_id }}</h4>
                <small class="text-muted">{{ $onu->serial_number ?? 'No SN' }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-heart-pulse fa-2x text-{{ $health['color'] }} mb-2"></i>
                <h3 class="fw-bold mb-0">{{ $health['score'] }}</h3>
                <span class="badge bg-{{ $health['color'] }}">{{ $health['grade'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-signal fa-2x {{ $onu->rx_power && $onu->rx_power > -25 ? 'text-success' : ($onu->rx_power && $onu->rx_power > -28 ? 'text-warning' : 'text-danger') }} mb-2"></i>
                <h5 class="fw-bold mb-0">{{ $onu->rx_power !== null ? number_format($onu->rx_power, 1).' dBm' : '—' }}</h5>
                <small class="text-muted">RX Optical Power</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        @if($distanceKm)
            @php
                $distColor = $distanceKm < 5 ? 'success' : ($distanceKm <= 10 ? 'warning' : 'danger');
            @endphp
                    <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fa-solid fa-route fa-2x text-{{ $distColor }} mb-2"></i>
                    <h5 class="fw-bold mb-0">{{ $distanceKm }} Km</h5>
                    <small class="text-muted">Jarak dari OLT <span class="badge bg-success bg-opacity-10 text-success" style="font-size:0.65rem">Realtime</span></small>
                    <div class="mt-2">
                        <span class="badge bg-{{ $distColor }}">{{ $distanceKm < 5 ? 'Optimal' : ($distanceKm <= 10 ? 'Cukup Jauh' : 'Sangat Jauh') }}</span>
                    </div>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fa-solid fa-route fa-2x text-secondary mb-2"></i>
                    <h5 class="fw-bold mb-0 text-muted">—</h5>
                    <small class="text-muted">Jarak belum diketahui</small>
                </div>
            </div>
        @endif
    </div>
</div>

@if($health['factors'] !== [])
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check text-primary"></i> Health Score Breakdown</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($health['factors'] as $factor)
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-{{ $factor['severity'] === 'critical' ? 'danger' : ($factor['severity'] === 'warning' ? 'warning' : ($factor['severity'] === 'excellent' ? 'success' : 'info') ) }} bg-opacity-10">
                        <span>{{ $factor['factor'] }}</span>
                        <span class="fw-bold text-{{ $factor['impact'] < 0 ? 'danger' : 'success' }}">{{ $factor['impact'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-server text-primary"></i> Informasi Umum</h6>
            </div>
            <div class="card-body">
                <table class="table table-hover align-middle mb-0 mon-table">
                    <tr><td class="text-muted" style="width:40%">ONU Name</td><td class="fw-bold">{{ $onu->onu_id }}</td></tr>
                    <tr><td class="text-muted">Customer</td><td>{{ $onu->customer->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Customer Code</td><td>{{ $onu->customer->customer_code ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Serial Number</td><td>{{ $onu->serial_number ?? '—' }}</td></tr>
                    <tr><td class="text-muted">MAC Address</td><td>{{ $onu->mac_address ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Vendor</td><td>{{ $onu->vendor ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Model</td><td>{{ $onu->model ?? '—' }}</td></tr>
                    <tr><td class="text-muted">ODP Port</td><td>{{ $onu->odpPort ? ($onu->odpPort->odp->nama_odp ?? 'ODP').' · Port '.$onu->odpPort->port_number : '—' }}</td></tr>
                    <tr><td class="text-muted">PON Port</td><td>{{ $onu->oltPort->slot_number ?? '—' }}/{{ $onu->oltPort->port_number ?? '—' }}</td></tr>
                    <tr><td class="text-muted">OLT</td><td>{{ $onu->oltPort->olt->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">ONU Index</td><td>{{ $onu->onu_id }}</td></tr>
                    <tr><td class="text-muted">Notes</td><td>{{ $onu->notes ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Last Seen</td><td>{{ $onu->last_seen_at ? $onu->last_seen_at->diffForHumans() : '—' }}</td></tr>
                </table>
                <div class="mt-3">
                    <a href="{{ route('onu-hotspot.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-pen me-1"></i> Edit / Atur ODP di ONU Hotspot
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-satellite-dish text-success"></i> Optical Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 rounded bg-{{ $onu->rx_power && $onu->rx_power > -25 ? 'success' : ($onu->rx_power && $onu->rx_power > -28 ? 'warning' : 'danger') }} bg-opacity-10 text-center">
                            <h6 class="text-muted mb-1">RX Power</h6>
                            <h4 class="fw-bold mb-0">{{ $onu->rx_power !== null ? number_format($onu->rx_power, 2).' dBm' : '—' }}</h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded bg-info bg-opacity-10 text-center">
                            <h6 class="text-muted mb-1">TX Power</h6>
                            <h4 class="fw-bold mb-0">{{ $onu->tx_power !== null ? number_format($onu->tx_power, 2).' dBm' : '—' }}</h4>
                        </div>
                    </div>
                </div>

                @if($distanceKm)
                    @php
                        $distColor = $distanceKm < 5 ? 'success' : ($distanceKm <= 10 ? 'warning' : 'danger');
                        $distLabel = $distanceKm < 5 ? 'Optimal' : ($distanceKm <= 10 ? 'Cukup Jauh' : 'Sangat Jauh — Risiko tinggi');
                    @endphp
                    <div class="p-3 rounded bg-{{ $distColor }} bg-opacity-10 text-center mb-3">
                        <i class="fa-solid fa-route fa-lg text-{{ $distColor }}"></i>
                        <h5 class="fw-bold mb-0 mt-1">{{ $distanceKm }} Km</h5>
                        <small>Jarak dari OLT <span class="badge bg-success bg-opacity-10 text-success" style="font-size:0.6rem">Realtime</span> — <span class="text-{{ $distColor }}">{{ $distLabel }}</span></small>
                        <div class="progress mt-2" style="height:6px;">
                            <div class="progress-bar bg-{{ $distColor }}" style="width:{{ min(100, $distanceKm * 10) }}%"></div>
                        </div>
                    </div>
                @endif

                <table class="table table-hover align-middle mb-0 mon-table">
                    <tr><td class="text-muted" style="width:50%">Temperature</td><td>—</td></tr>
                    <tr><td class="text-muted">Voltage</td><td>—</td></tr>
                    <tr><td class="text-muted">Bias Current</td><td>—</td></tr>
                    <tr><td class="text-muted">Laser Status</td><td>{!! ($onu->status === 'online') ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>' !!}</td></tr>
                    <tr><td class="text-muted">Uptime</td><td id="uptimeVal">{{ $onu->uptime ? '' : '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@if($history->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-chart-line text-info"></i> RX Power History</h6>
    </div>
    <div class="card-body">
        <canvas id="rxHistoryChart" height="100"></canvas>
    </div>
</div>
@endif

@if($diagnosis !== [])
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-stethoscope text-warning"></i> Diagnosis Ringkas</h6>
    </div>
    <div class="card-body">
        @foreach($diagnosis as $d)
            <div class="alert alert-{{ $d['severity'] === 'critical' ? 'danger' : ($d['severity'] === 'warning' ? 'warning' : 'info') }} mb-3">
                <h6 class="alert-heading fw-bold">{{ $d['title'] }}</h6>
                <div class="mb-2">
                    <strong>Kemungkinan:</strong>
                    <ul class="mb-1 mt-1">
                        @foreach($d['causes'] as $cause)
                            <li>{{ $cause }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="mb-1">
                    <strong>Rekomendasi:</strong>
                    <ul class="mb-1 mt-1">
                        @foreach($d['recommendations'] as $rec)
                            <li>{{ $rec }}</li>
                        @endforeach
                    </ul>
                </div>
                <small class="text-muted">Confidence: {{ $d['confidence'] }}% | Priority: {{ $d['priority'] }}</small>
            </div>
        @endforeach
    </div>
</div>
@endif

@if($speedEstimate && $speedEstimate['supported'])
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-speedometer text-warning"></i> Estimasi Throughput</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3"><i class="fa-solid fa-circle-info me-2"></i>{{ $speedEstimate['message'] }}</div>
        <div class="row g-3">
            <div class="col-md-3 text-center">
                <div class="p-3 rounded bg-success bg-opacity-10">
                    <h6 class="text-muted">Download</h6>
                    <h4 class="fw-bold">{{ $speedEstimate['download_mbps'] }} Mbps</h4>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="p-3 rounded bg-primary bg-opacity-10">
                    <h6 class="text-muted">Upload</h6>
                    <h4 class="fw-bold">{{ $speedEstimate['upload_mbps'] }} Mbps</h4>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="p-3 rounded bg-info bg-opacity-10">
                    <h6 class="text-muted">Latency</h6>
                    <h4 class="fw-bold">{{ $speedEstimate['latency_ms'] }} ms</h4>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="p-3 rounded bg-{{ $speedEstimate['packet_loss'] > 0 ? 'danger' : 'success' }} bg-opacity-10">
                    <h6 class="text-muted">Packet Loss</h6>
                    <h4 class="fw-bold">{{ $speedEstimate['packet_loss'] }}%</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($history->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const historyData = @json($history->reverse()->values());
    const labels = historyData.map(h => new Date(h.created_at).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}));
    const rxValues = historyData.map(h => h.rx_power);

    new Chart(document.getElementById('rxHistoryChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'RX Power (dBm)',
                data: rxValues,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    title: { display: true, text: 'dBm' },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
@endif
<script>
function formatUptime(seconds) {
    if (!seconds) return '—';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
    if (h > 0) return h + 'h ' + m + 'm';
    return m + 'm';
}

document.addEventListener('DOMContentLoaded', function() {
    const uptimeEl = document.getElementById('uptimeVal');
    if (uptimeEl) {
        const seconds = {{ $onu->uptime ?? 'null' }};
        uptimeEl.textContent = formatUptime(seconds);
    }
});
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
