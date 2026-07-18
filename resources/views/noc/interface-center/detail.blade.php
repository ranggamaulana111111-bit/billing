@extends('layouts.app')

@section('title', ($data['name'] ?? $interfaceName) . ' — Interface Detail')

@php
    function ifDetFmtBytes($bytes) {
        if ($bytes == 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[$i];
    }

    function ifDetFmtRate($bps) {
        if (!$bps || $bps == 0) return '0 bps';
        if ($bps >= 1e9) return ($bps / 1e9)->toFixed(2) . ' Gbps';
        if ($bps >= 1e6) return ($bps / 1e6)->toFixed(2) . ' Mbps';
        if ($bps >= 1e3) return ($bps / 1e3)->toFixed(2) . ' Kbps';
        return round($bps) . ' bps';
    }
@endphp

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0">
            <i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>{{ $data['name'] ?? $interfaceName }}
            @if(!empty($data['alias']))
                <span class="text-muted fw-normal" style="font-size:0.9rem;">({{ $data['alias'] }})</span>
            @endif
            @if(isset($data['running']))
                <span class="badge bg-{{ ($data['running'] && !$data['disabled']) ? 'success' : 'danger' }} ms-2" style="font-size:0.65rem;vertical-align:middle;">
                    {{ $data['disabled'] ? 'DISABLED' : ($data['running'] ? 'UP' : 'DOWN') }}
                </span>
            @endif
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <code>{{ $router->host }}:{{ $router->port }}</code>
            &mdash; {{ $router->display_identity }}
            @if($router->site) &mdash; {{ $router->site }} @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <span class="badge bg-success me-2" style="font-size:0.65rem;">
            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:3px;animation:pulse 1.5s infinite;"></span>LIVE
        </span>
        <a href="{{ route('noc.interface-center.index', ['router' => $router->id]) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(!empty($data['error']))
    <div class="alert alert-custom alert-danger mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
        {{ $data['error'] }}
    </div>
@endif

@if(isset($data['name']))
<div class="row g-4 mb-4">
    {{-- ═══ INFO PANEL ═══ --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header border-0" style="background:rgba(37,99,235,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-circle-info me-2" style="color:var(--primary);"></i>Informasi Interface</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Nama</div>
                        <div class="fw-semibold">{{ $data['name'] }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Alias</div>
                        <div class="fw-semibold">{{ $data['alias'] ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Tipe</div>
                        <div class="fw-semibold"><span class="badge bg-secondary" style="font-size:0.7rem;">{{ $data['type'] }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">MTU</div>
                        <div class="fw-semibold">{{ $data['mtu'] ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">MAC Address</div>
                        <div class="fw-semibold"><code>{{ $data['mac_address'] ?? '-' }}</code></div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Status</div>
                        <div class="fw-semibold">
                            @if($data['disabled'])
                                <span class="badge" style="background:#64748b;">DISABLED</span>
                            @elseif($data['running'])
                                <span class="badge bg-success">UP</span>
                            @else
                                <span class="badge bg-danger">DOWN</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Auto Negotiation</div>
                        <div class="fw-semibold">{{ $data['auto_negotiation'] ? 'Ya' : 'Tidak' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Speed</div>
                        <div class="fw-semibold">{{ $data['speed'] ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Full Duplex</div>
                        <div class="fw-semibold">{{ $data['full_duplex'] ? 'Ya' : 'Tidak' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Comment</div>
                        <div class="fw-semibold">{{ $data['comment'] ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Link Downs</div>
                        <div class="fw-semibold">{{ $data['link_downs'] ?? 0 }}</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Tag</div>
                        <div class="fw-semibold">
                            @foreach(($data['tags'] ?? []) as $tag)
                                <span class="badge bg-info" style="font-size:0.6rem;">{{ $tag }}</span>
                            @endforeach
                            @if(empty($data['tags'])) - @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ RATE & STATISTICS ═══ --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-0" style="background:rgba(5,150,105,0.06);">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-gauge-high me-2" style="color:#059669;"></i>Traffic Rate</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                        <span class="fw-semibold text-success">Download</span>
                        <span class="fw-bold">{{ ifDetFmtRate($data['rate_rx'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:0.82rem;">
                        <span class="fw-semibold text-primary">Upload</span>
                        <span class="fw-bold">{{ ifDetFmtRate($data['rate_tx'] ?? 0) }}</span>
                    </div>
                </div>
                <hr>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Total RX</div>
                    <div class="fw-semibold">{{ ifDetFmtBytes($data['rx_byte'] ?? 0) }}</div>
                </div>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">Total TX</div>
                    <div class="fw-semibold">{{ ifDetFmtBytes($data['tx_byte'] ?? 0) }}</div>
                </div>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">RX Errors</div>
                    <div class="fw-semibold {{ ($data['rx_error'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $data['rx_error'] ?? 0 }}</div>
                </div>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">TX Errors</div>
                    <div class="fw-semibold {{ ($data['tx_error'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $data['tx_error'] ?? 0 }}</div>
                </div>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">RX Drops</div>
                    <div class="fw-semibold {{ ($data['rx_drop'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $data['rx_drop'] ?? 0 }}</div>
                </div>
                <div class="mb-2">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);">TX Drops</div>
                    <div class="fw-semibold {{ ($data['tx_drop'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $data['tx_drop'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CONFIGURATION ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header border-0" style="background:rgba(139,92,246,0.06);">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-gear me-2" style="color:#8b5cf6;"></i>Konfigurasi</h6>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <form method="POST" action="{{ route('noc.interface-center.update', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="disabled" value="{{ $data['disabled'] ? '0' : '1' }}">
                    <button type="submit" class="btn btn-sm {{ $data['disabled'] ? 'btn-outline-success' : 'btn-outline-danger' }} w-100" onclick="return confirm('{{ $data['disabled'] ? 'Enable' : 'Disable' }} interface ini?')">
                        <i class="fa-solid fa-toggle-{{ $data['disabled'] ? 'on' : 'off' }} me-1"></i>{{ $data['disabled'] ? 'Enable' : 'Disable' }}
                    </button>
                </form>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Rename</label>
                <form method="POST" action="{{ route('noc.interface-center.update', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <div class="input-group input-group-sm">
                        <input type="text" name="name" class="form-control" placeholder="Nama baru" required maxlength="100">
                        <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Ganti nama interface?')"><i class="fa-solid fa-check"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">MTU</label>
                <form method="POST" action="{{ route('noc.interface-center.update', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <div class="input-group input-group-sm">
                        <input type="number" name="mtu" class="form-control" value="{{ $data['mtu'] ?? '' }}" min="64" max="65535">
                        <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Ubah MTU?')"><i class="fa-solid fa-check"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Comment</label>
                <form method="POST" action="{{ route('noc.interface-center.update', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <div class="input-group input-group-sm">
                        <input type="text" name="comment" class="form-control" value="{{ $data['comment'] ?? '' }}" placeholder="Comment" maxlength="500">
                        <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Ubah comment?')"><i class="fa-solid fa-check"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Alias / Tag</label>
                <form method="POST" action="{{ route('noc.interface-center.update-metadata', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <div class="input-group input-group-sm">
                        <input type="text" name="alias" class="form-control" value="{{ $data['alias'] ?? '' }}" placeholder="Alias" maxlength="100">
                        <button type="submit" class="btn btn-outline-info"><i class="fa-solid fa-tag"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Catatan</label>
                <form method="POST" action="{{ route('noc.interface-center.update-metadata', [$router->id, $data['name']]) }}">
                    @csrf @method('PUT')
                    <div class="input-group input-group-sm">
                        <input type="text" name="notes" class="form-control" value="{{ $data['notes'] ?? '' }}" placeholder="Catatan lokal..." maxlength="1000">
                        <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-save"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ═══ TRAFFIC CHART ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-2">
        <div class="bento-card">
            <div class="bento-accent bento-accent-green"></div>
            <div class="bento-card-header">
                <div class="bento-title">
                    <span class="dot" style="background:#059669;"></span>
                    Traffic Realtime
                </div>
            </div>
            <div class="bento-card-body" style="padding:12px 16px 16px;">
                <div class="bento-chart-wrap" style="height:220px;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CHANGE HISTORY ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header border-0" style="background:rgba(245,158,11,0.06);">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2" style="color:#f59e0b;"></i>Riwayat Perubahan</h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Status</th>
                        <th>Pesan</th>
                    </tr>

                <tbody>
                    @forelse(($data['change_history'] ?? []) as $log)
                        <tr>
                            <td style="font-size:0.78rem;">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td>{{ $log->user->name ?? '-' }}</td>
                            <td><span class="badge bg-secondary" style="font-size:0.65rem;">{{ $log->change_type }}</span></td>
                            <td>
                                <span class="badge {{ $log->status === 'success' ? 'bg-success' : 'bg-danger' }}" style="font-size:0.6rem;">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td style="font-size:0.78rem;">{{ $log->message ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-3 text-muted">Belum ada riwayat perubahan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function() {
    @if(isset($data['name']))
    const routerId = {{ $router->id }};
    const interfaceName = @json($data['name']);
    let chart;
    const chartData = { labels: [], datasets: [] };
    const MAX_POINTS = 60;

    function formatRate(bps) {
        if (!bps || bps === 0) return '0 bps';
        if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
        if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
        if (bps >= 1e3) return (bps / 1e3).toFixed(2) + ' Kbps';
        return bps.toFixed(0) + ' bps';
    }

    // Init chart
    chart = new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Download', data: [], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.4, pointRadius: 0, fill: true, borderWidth: 2 },
                { label: 'Upload', data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4, pointRadius: 0, fill: true, borderWidth: 2 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 300 },
            plugins: {
                legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, pointStyleWidth: 6, font: { size: 10 }, color: '#94a3b8' } },
                tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 8, cornerRadius: 6, callbacks: { label: ctx => ctx.dataset.label + ': ' + formatRate(ctx.raw) } }
            },
            scales: {
                y: { title: { display: true, text: 'bps', color: '#94a3b8' }, beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => formatRate(v) } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 10 } }
            }
        }
    });

    let prevRx = {{ $data['rx_byte'] ?? 0 }};
    let prevTx = {{ $data['tx_byte'] ?? 0 }};
    let prevTime = Date.now();

    async function refresh() {
        try {
            const resp = await fetch('/api/noc/interface-center/' + routerId + '/live/' + encodeURIComponent(interfaceName), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!resp.ok) return;
            const data = await resp.json();

            const now = Date.now();
            const elapsed = (now - prevTime) / 1000;
            const timeLabel = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            if (elapsed > 0) {
                const curRx = parseInt(data.rx_byte || 0);
                const curTx = parseInt(data.tx_byte || 0);
                const rateRx = Math.max(0, (curRx - prevRx) * 8 / elapsed);
                const rateTx = Math.max(0, (curTx - prevTx) * 8 / elapsed);

                chart.data.labels.push(timeLabel);
                chart.data.datasets[0].data.push(rateRx);
                chart.data.datasets[1].data.push(rateTx);

                if (chart.data.labels.length > MAX_POINTS) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                    chart.data.datasets[1].data.shift();
                }

                chart.update('none');
                prevRx = curRx;
                prevTx = curTx;
            }
            prevTime = now;

            // Update rate display
            if (data.rate_rx !== undefined) {
                const dlEl = document.querySelector('.text-success.fw-bold');
                if (dlEl) dlEl.textContent = formatRate(data.rate_rx);
            }
            if (data.rate_tx !== undefined) {
                const ulEls = document.querySelectorAll('.text-primary.fw-bold');
                ulEls.forEach(el => { if (el.textContent.includes('bps')) el.textContent = formatRate(data.rate_tx); });
            }
        } catch (e) {
            console.error('Live refresh error:', e);
        }
    }

    setInterval(refresh, 3000);
    @endif
})();
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
