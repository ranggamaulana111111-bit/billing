@extends('layouts.app')

@section('title', 'Ping Monitoring')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fa-solid fa-network-wired text-info"></i> Ping Monitoring</h2>
        <p class="text-muted mb-0">Monitoring konektivitas ke target jaringan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('onu-health.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <button class="btn btn-info btn-sm text-white" id="btnPingAll" onclick="executePing()">
            <i class="fa-solid fa-play"></i> Jalankan Ping
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4 mb-4" id="pingCards">
    @foreach($targets as $target)
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 ping-card" data-host="{{ $target['host'] }}">
                <div class="card-body text-center py-4">
                    <div class="mb-2 ping-status-icon">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                    <h6 class="fw-bold mb-1">{{ $target['label'] }}</h6>
                    <small class="text-muted font-monospace">{{ $target['host'] }}</small>
                    <div class="mt-3 ping-results" style="display:none;">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">Latency</small>
                                <span class="fw-bold ping-latency">—</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Jitter</small>
                                <span class="fw-bold ping-jitter">—</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Packet Loss</small>
                                <span class="fw-bold ping-loss">—</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Status</small>
                                <span class="badge ping-status-badge">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 ping-loading" style="display:none;">
                        <div class="progress" style="height:4px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width:100%"></div>
                        </div>
                        <small class="text-muted">Sedang ping...</small>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-chart-line text-success"></i> Latency History</h6>
    </div>
    <div class="card-body">
        <canvas id="latencyChart" height="80"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const targets = @json($targets);
const latencyHistory = {};

targets.forEach(t => { latencyHistory[t.host] = { labels: [], data: [] }; });

let latencyChart = null;

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('latencyChart');
    if (ctx) {
        latencyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: targets.map((t, i) => ({
                    label: t.label,
                    data: [],
                    borderColor: ['#0d6efd','#dc3545','#198754','#ffc107'][i % 4],
                    tension: 0.4,
                    pointRadius: 2,
                    fill: false,
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                scales: {
                    y: { title: { display: true, text: 'ms' }, beginAtZero: true },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});

async function executePing() {
    const btn = document.getElementById('btnPingAll');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ping...';

    document.querySelectorAll('.ping-card').forEach(card => {
        card.querySelector('.ping-status-icon').innerHTML = '<div class="spinner-border spinner-border-sm text-secondary"></div>';
        card.querySelector('.ping-results').style.display = 'none';
        card.querySelector('.ping-loading').style.display = 'block';
    });

    try {
        const resp = await fetch('{{ route("onu-health.ping.execute") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });
        const json = await resp.json();

        if (json.success && json.data) {
            json.data.forEach(result => {
                const card = document.querySelector(`.ping-card[data-host="${result.host}"]`);
                if (!card) return;

                card.querySelector('.ping-loading').style.display = 'none';
                card.querySelector('.ping-results').style.display = 'block';

                const statusColor = result.status === 'online' ? 'success' : (result.status === 'warning' ? 'warning' : 'danger');
                const statusIcon = result.status === 'online' ? 'fa-check-circle' : (result.status === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle');

                card.querySelector('.ping-status-icon').innerHTML = `<i class="fa-solid ${statusIcon} fa-2x text-${statusColor}"></i>`;
                card.querySelector('.ping-latency').textContent = result.latency_ms !== null ? result.latency_ms + ' ms' : '—';
                card.querySelector('.ping-latency').className = 'fw-bold ping-latency text-' + statusColor;
                card.querySelector('.ping-jitter').textContent = result.jitter_ms !== null ? result.jitter_ms + ' ms' : '—';
                card.querySelector('.ping-loss').textContent = result.packet_loss_percent + '%';
                card.querySelector('.ping-loss').className = 'fw-bold ping-loss text-' + (result.packet_loss_percent > 0 ? 'danger' : 'success');

                const badge = card.querySelector('.ping-status-badge');
                badge.textContent = result.status.toUpperCase();
                badge.className = 'badge ping-status-badge bg-' + statusColor;
            });

            if (latencyChart) {
                const timeLabel = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
                latencyChart.data.labels.push(timeLabel);
                if (latencyChart.data.labels.length > 30) latencyChart.data.labels.shift();

                json.data.forEach((result, i) => {
                    if (latencyChart.data.datasets[i]) {
                        latencyChart.data.datasets[i].data.push(result.latency_ms);
                        if (latencyChart.data.datasets[i].data.length > 30) latencyChart.data.datasets[i].data.shift();
                    }
                });
                latencyChart.update();
            }
        }
    } catch (e) {
        console.error('Ping failed:', e);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-play"></i> Jalankan Ping';
}
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
