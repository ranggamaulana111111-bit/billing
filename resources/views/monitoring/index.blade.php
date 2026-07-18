@extends('layouts.app')

@section('title', 'Monitoring Real-Time')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-signal me-2" style="color:#2563eb;"></i>Monitoring Real-Time</h2>
        <p class="section-subtitle mb-0 mt-1">
            Total trafik pengguna & status ONU
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
    <div class="mt-2 mt-md-0 d-flex align-items-center gap-2">
        <select id="router-select" class="form-select form-select-sm" style="width:200px;">
            @forelse($routers as $r)
                <option value="{{ $r->id }}" {{ ($router?->id ?? '') === $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
            @empty
                <option value="">Tidak ada router</option>
            @endforelse
        </select>
    </div>
</div>

@if(! $router)
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-server" style="font-size:2rem;color:#94a3b8;display:block;margin-bottom:12px;"></i>
            <p class="text-muted mb-0">Tidak ada MikroTik router aktif.</p>
        </div>
    </div>
@else

{{-- ═══ WAN TRAFFIC CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 wan-card" style="border-radius:16px;background:linear-gradient(135deg,#0ea5e9,#0284c7);overflow:hidden;">
            <div class="card-body py-3 px-3" style="color:#fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div style="font-size:0.65rem;opacity:0.8;text-transform:uppercase;letter-spacing:0.5px;">Total Download</div>
                        <div style="font-size:1.7rem;font-weight:800;line-height:1.1;" id="wan-rx">—</div>
                    </div>
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-arrow-down" style="font-size:0.9rem;"></i>
                    </div>
                </div>
                <div style="font-size:0.65rem;opacity:0.7;margin-top:4px;">Akumulasi interface WAN-ISP</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 wan-card" style="border-radius:16px;background:linear-gradient(135deg,#8b5cf6,#7c3aed);overflow:hidden;">
            <div class="card-body py-3 px-3" style="color:#fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div style="font-size:0.65rem;opacity:0.8;text-transform:uppercase;letter-spacing:0.5px;">Total Upload</div>
                        <div style="font-size:1.7rem;font-weight:800;line-height:1.1;" id="wan-tx">—</div>
                    </div>
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-arrow-up" style="font-size:0.9rem;"></i>
                    </div>
                </div>
                <div style="font-size:0.65rem;opacity:0.7;margin-top:4px;">Akumulasi interface WAN-ISP</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 wan-card" style="border-radius:16px;background:linear-gradient(135deg,#f59e0b,#d97706);overflow:hidden;">
            <div class="card-body py-3 px-3" style="color:#fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div style="font-size:0.65rem;opacity:0.8;text-transform:uppercase;letter-spacing:0.5px;">ONU PPP Aktif</div>
                        <div style="font-size:1.7rem;font-weight:800;line-height:1.1;" id="onu-ppp-count">{{ $onuStats['ppp']['online'] }}</div>
                    </div>
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-tower-cell" style="font-size:0.9rem;"></i>
                    </div>
                </div>
                <div style="font-size:0.65rem;opacity:0.7;margin-top:4px;">Online dari {{ $onuStats['ppp']['total'] }} total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 wan-card" style="border-radius:16px;background:linear-gradient(135deg,#10b981,#059669);overflow:hidden;">
            <div class="card-body py-3 px-3" style="color:#fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div style="font-size:0.65rem;opacity:0.8;text-transform:uppercase;letter-spacing:0.5px;">ONU Hotspot Aktif</div>
                        <div style="font-size:1.7rem;font-weight:800;line-height:1.1;" id="onu-hs-count">{{ $onuStats['hotspot']['online'] }}</div>
                    </div>
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-wifi" style="font-size:0.9rem;"></i>
                    </div>
                </div>
                <div style="font-size:0.65rem;opacity:0.7;margin-top:4px;">Online dari {{ $onuStats['hotspot']['total'] }} total</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ TRAFFIC GRAPH (MikroTik style) ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header mon-card-head d-flex align-items-center justify-content-between" style="border-bottom:1px solid #f1f5f9;">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Grafik Trafik — {{ $wanName ?? 'WAN-ISP' }}</span>
        </div>
        <div class="d-flex align-items-center gap-3" style="font-size:0.72rem;color:rgba(255,255,255,0.85);">
            <span class="d-flex align-items-center gap-1"><span style="width:14px;height:2px;background:#7dd3fc;display:inline-block;"></span>Download</span>
            <span class="d-flex align-items-center gap-1"><span style="width:14px;height:2px;background:#fda4af;display:inline-block;"></span>Upload</span>
            <span style="color:rgba(255,255,255,0.7);">[{{ $wanName ?? 'WAN-ISP' }}] realtime</span>
        </div>
    </div>
    <div class="card-body py-2 px-2">
        <div style="position:relative;height:150px;">
            <canvas id="trafficChart"></canvas>
        </div>
    </div>
</div>

{{-- ═══ TABLES GRID ═══ --}}
<div class="row g-4">
    {{-- ONU PPP Aktif --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header mon-card-head d-flex align-items-center gap-2" style="border-bottom:1px solid #f1f5f9;">
                <span class="tbl-dot" style="background:#f59e0b;"></span>
                <span class="fw-semibold" style="font-size:0.85rem;">ONU PPP Aktif</span>
                <span class="badge tbl-badge tbl-badge-amber ms-auto" id="onu-ppp-badge">{{ $onuPpps->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive mon-table-wrap">
                    <table class="table table-hover align-middle mb-0 mon-table">
                        <thead class="table-light mon-thead">
                            <tr>
                                <th>Pelanggan</th>
                                <th>Serial</th>
                                <th>OLT</th>
                                <th class="text-center">RX</th>
                                <th>Seen</th>
                            </tr>
                        </thead>
                        <tbody id="onu-ppp-tbody">
                            @forelse($onuPpps as $onu)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $onu->customer->name ?? '-' }}</span>
                                    <div class="mon-sub">{{ $onu->customer->customer_code ?? '' }}</div>
                                </td>
                                <td><code class="mon-code">{{ $onu->serial_number ?? '-' }}</code></td>
                                <td class="mon-muted">{{ $onu->oltPort?->olt?->name ?? '-' }}</td>
                                <td class="text-center mon-rx">
                                    @if($onu->rx_power)
                                        <span style="color:{{ $onu->rx_power > -27 ? '#059669' : '#dc2626' }};">{{ number_format($onu->rx_power, 1) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="mon-muted">{{ $onu->last_seen_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada ONU PPP online</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ONU Hotspot Aktif --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header mon-card-head d-flex align-items-center gap-2" style="border-bottom:1px solid #f1f5f9;">
                <span class="tbl-dot" style="background:#10b981;"></span>
                <span class="fw-semibold" style="font-size:0.85rem;">ONU Hotspot Aktif</span>
                <span class="badge tbl-badge tbl-badge-green ms-auto" id="onu-hs-badge">{{ $onuHotspots->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive mon-table-wrap">
                    <table class="table table-hover align-middle mb-0 mon-table">
                        <thead class="table-light mon-thead">
                            <tr>
                                <th>Pelanggan</th>
                                <th>Serial</th>
                                <th>OLT</th>
                                <th class="text-center">RX</th>
                                <th>Seen</th>
                            </tr>
                        </thead>
                        <tbody id="onu-hs-tbody">
                            @forelse($onuHotspots as $onu)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $onu->customer->name ?? '-' }}</span>
                                    <div class="mon-sub">{{ $onu->customer->customer_code ?? '' }}</div>
                                </td>
                                <td><code class="mon-code">{{ $onu->serial_number ?? '-' }}</code></td>
                                <td class="mon-muted">{{ $onu->oltPort?->olt?->name ?? '-' }}</td>
                                <td class="text-center mon-rx">
                                    @if($onu->rx_power)
                                        <span style="color:{{ $onu->rx_power > -27 ? '#059669' : '#dc2626' }};">{{ number_format($onu->rx_power, 1) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="mon-muted">{{ $onu->last_seen_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada ONU Hotspot online</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Akun PPP Aktif --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header mon-card-head d-flex align-items-center gap-2" style="border-bottom:1px solid #f1f5f9;">
                <span class="tbl-dot" style="background:#0ea5e9;"></span>
                <span class="fw-semibold" style="font-size:0.85rem;">Akun PPP Aktif</span>
                <span class="badge tbl-badge tbl-badge-blue ms-auto" id="ppp-active-badge">{{ count($pppActive) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive mon-table-wrap">
                    <table class="table table-hover align-middle mb-0 mon-table">
                        <thead class="table-light mon-thead">
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>MAC / Caller-ID</th>
                                <th class="text-end">Uptime</th>
                            </tr>
                        </thead>
                        <tbody id="ppp-active-tbody">
                            @forelse($pppActive as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s['name'] ?? $s['user'] ?? '—' }}</td>
                                <td><code class="mon-code">{{ $s['address'] ?? '—' }}</code></td>
                                <td class="mon-muted">{{ $s['caller-id'] ?? '—' }}</td>
                                <td class="text-end mon-muted">{{ $s['uptime'] ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada akun PPP aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Voucher Digunakan --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header mon-card-head d-flex align-items-center gap-2" style="border-bottom:1px solid #f1f5f9;">
                <span class="tbl-dot" style="background:#10b981;"></span>
                <span class="fw-semibold" style="font-size:0.85rem;">Voucher Digunakan</span>
                <span class="badge tbl-badge tbl-badge-green ms-auto" id="voucher-active-badge">{{ count($hotspotActive) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive mon-table-wrap">
                    <table class="table table-hover align-middle mb-0 mon-table">
                        <thead class="table-light mon-thead">
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>MAC</th>
                                <th class="text-end">Uptime</th>
                            </tr>
                        </thead>
                        <tbody id="voucher-active-tbody">
                            @forelse($hotspotActive as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s['user'] ?? $s['name'] ?? '—' }}</td>
                                <td><code class="mon-code">{{ $s['address'] ?? '—' }}</code></td>
                                <td class="mon-muted">{{ $s['mac-address'] ?? '—' }}</td>
                                <td class="text-end mon-muted">{{ $s['uptime'] ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada voucher digunakan</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endif
@endsection

@push('scripts')
<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
.wan-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.wan-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.rate-pulse { animation: ratePulse 0.5s ease; }
@keyframes ratePulse { 0%{opacity:0.4} 100%{opacity:1} }

/* ── Monitoring tables (unified) ── */
.tbl-dot { width:8px; height:8px; border-radius:50%; flex:0 0 auto; }
.tbl-badge { font-size:0.68rem; font-weight:600; }
.tbl-badge-amber { background:#fef3c7; color:#d97706; }
.tbl-badge-green { background:#d1fae5; color:#059669; }
.tbl-badge-blue  { background:#e0f2fe; color:#0369a1; }

/* Card header gradient */
.mon-card-head {
    background:linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%) !important;
    color:#fff !important;
}
.mon-card-head .fw-semibold { color:#fff !important; }

.mon-table-wrap { max-height:380px; overflow:auto; padding-right:2px; scrollbar-width:thin; scrollbar-color:transparent transparent; }
.mon-table-wrap:hover { scrollbar-color:#cbd5e1 transparent; }
.mon-table { width:100%; }
.mon-table th, .mon-table td { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mon-table-wrap::-webkit-scrollbar { width:6px; height:6px; background:transparent; }
.mon-table-wrap::-webkit-scrollbar-track { background:transparent; margin:6px 1px; }
.mon-table-wrap::-webkit-scrollbar-thumb { background:transparent; border-radius:10px; min-height:40px; min-width:40px; }
.mon-table-wrap:hover::-webkit-scrollbar-thumb { background:#cbd5e1; }
.mon-table-wrap:hover::-webkit-scrollbar-thumb:hover { background:#94a3b8; }
.mon-table { font-size:0.78rem; }
.mon-table > :not(caption) > * > * { padding:0.5rem 0.75rem; }
.mon-thead th {
    position:sticky; top:0; z-index:2;
    font-size:0.7rem; text-transform:uppercase; letter-spacing:0.03em;
    color:#475569; font-weight:700;
    background:#f1f5f9 !important;
    border-bottom:1px solid #e2e8f0;
    white-space:nowrap;
}
.mon-table td { vertical-align:middle; }
.mon-sub { font-size:0.68rem; color:#64748b; margin-top:1px; }
.mon-muted { color:#64748b; font-size:0.74rem; }
.mon-code { font-size:0.72rem; color:#334155; background:#f1f5f9; padding:1px 5px; border-radius:4px; }
.mon-rx { font-variant-numeric:tabular-nums; font-weight:600; }
</style>
<script>
(function() {
    const $ = sel => document.querySelector(sel);
    const MAX_POINTS = 60;
    let prevRx = 0, prevTx = 0, prevTime = 0;
    let chart = null;

    const labels = [];
    const rxData = [];
    const txData = [];

    for (let i = MAX_POINTS; i > 0; i--) {
        labels.push('');
        rxData.push(0);
        txData.push(0);
    }

    const ctx = document.getElementById('trafficChart');
    if (!ctx) return;

    const rxGrad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 150);
    rxGrad.addColorStop(0, 'rgba(14,165,233,0.35)');
    rxGrad.addColorStop(1, 'rgba(14,165,233,0.02)');
    const txGrad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 150);
    txGrad.addColorStop(0, 'rgba(244,63,94,0.30)');
    txGrad.addColorStop(1, 'rgba(244,63,94,0.02)');

    function buildChart() {
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Download',
                    data: rxData,
                    borderColor: '#0ea5e9',
                    backgroundColor: rxGrad,
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    pointHitRadius: 8,
                },
                {
                    label: 'Upload',
                    data: txData,
                    borderColor: '#f43f5e',
                    backgroundColor: txGrad,
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    pointHitRadius: 8,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.92)',
                    titleFont: { size: 10 },
                    bodyFont: { size: 11 },
                    padding: 8,
                    cornerRadius: 4,
                    displayColors: true,
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + fmtRate(ctx.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: { display: false },
                    ticks: { display: false },
                    border: { display: false }
                },
                y: {
                    display: true,
                    beginAtZero: true,
                    grid: { color: 'rgba(148,163,184,0.18)', drawTicks: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 9 },
                        color: '#94a3b8',
                        maxTicksLimit: 4,
                        padding: 4,
                        callback: function(v) { return fmtRateShort(v); }
                    }
                }
            }
        }
    });
    }

    function fmtRate(bytesPerSec) {
        if (!bytesPerSec || bytesPerSec <= 0) return '0 bps';
        const bits = bytesPerSec * 8;
        if (bits >= 1000000000) return (bits / 1000000000).toFixed(2) + ' Gbps';
        if (bits >= 1000000) return (bits / 1000000).toFixed(2) + ' Mbps';
        if (bits >= 1000) return (bits / 1000).toFixed(1) + ' Kbps';
        return bits.toFixed(0) + ' bps';
    }

    function fmtRateShort(bytesPerSec) {
        if (!bytesPerSec || bytesPerSec <= 0) return '0';
        const bits = bytesPerSec * 8;
        if (bits >= 1000000000) return (bits / 1000000000).toFixed(1) + 'G';
        if (bits >= 1000000) return (bits / 1000000).toFixed(1) + 'M';
        if (bits >= 1000) return (bits / 1000).toFixed(0) + 'K';
        return bits.toFixed(0);
    }

    function fmt(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const u = ['B','KB','MB','GB','TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
    }

    function esc(s) {
        if (s == null) return '-';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pulse(el) {
        el.classList.add('rate-pulse');
        setTimeout(() => el.classList.remove('rate-pulse'), 500);
    }

    function fetchLive() {
        const rid = $('#router-select')?.value || '';
        fetch('{{ route("monitoring.live") }}' + (rid ? '?router_id=' + rid : ''))
            .then(r => r.json())
            .then(d => {
                if (d.error) return;

                const now = Date.now();
                const dt = prevTime ? (now - prevTime) / 1000 : 0;
                prevTime = now;

                let rxRate = (d.rx_rate ?? 0);
                let txRate = (d.tx_rate ?? 0);
                if ((!rxRate && !txRate) && dt > 0) {
                    rxRate = Math.max(0, (d.total_rx - prevRx) / dt);
                    txRate = Math.max(0, (d.total_tx - prevTx) / dt);
                }
                prevRx = d.total_rx;
                prevTx = d.total_tx;

                // Chart data
                const timeLabel = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                labels.push(timeLabel);
                rxData.push(rxRate);
                txData.push(txRate);
                if (labels.length > MAX_POINTS) { labels.shift(); rxData.shift(); txData.shift(); }
                chart.update('none');

                // WAN cards
                const rxEl = $('#wan-rx');
                const txEl = $('#wan-tx');
                if (rxRate > 0) { rxEl.textContent = fmtRate(rxRate); pulse(rxEl); }
                else { rxEl.textContent = fmt(d.total_rx) + ' total'; }
                if (txRate > 0) { txEl.textContent = fmtRate(txRate); pulse(txEl); }
                else { txEl.textContent = fmt(d.total_tx) + ' total'; }

                // ONU counts
                $('#onu-ppp-count').textContent = d.onu_ppp_count;
                $('#onu-ppp-badge').textContent = d.onu_ppp_count;
                $('#onu-hs-count').textContent = d.onu_hotspot_count;
                $('#onu-hs-badge').textContent = d.onu_hotspot_count;

                // ONU PPP table
                const pppTbody = $('#onu-ppp-tbody');
                if (d.onu_ppp && d.onu_ppp.length) {
                    pppTbody.innerHTML = d.onu_ppp.map(o => {
                        const rxC = o.rx_power ? (o.rx_power > -27 ? '#059669' : '#dc2626') : '';
                        const rxV = o.rx_power != null ? parseFloat(o.rx_power).toFixed(1) : '-';
                        return '<tr>' +
                            '<td><span class="fw-semibold">' + esc(o.customer) + '</span>' +
                            '<div class="mon-sub">' + esc(o.customer_code) + '</div></td>' +
                            '<td><code class="mon-code">' + esc(o.serial) + '</code></td>' +
                            '<td class="mon-muted">' + esc(o.olt) + '</td>' +
                            '<td class="text-center mon-rx" style="color:' + rxC + ';">' + rxV + '</td>' +
                            '<td class="mon-muted">' + esc(o.last_seen) + '</td></tr>';
                    }).join('');
                } else {
                    pppTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada ONU PPP online</td></tr>';
                }

                // ONU Hotspot table
                const hsTbody = $('#onu-hs-tbody');
                if (d.onu_hotspot && d.onu_hotspot.length) {
                    hsTbody.innerHTML = d.onu_hotspot.map(o => {
                        const rxC = o.rx_power ? (o.rx_power > -27 ? '#059669' : '#dc2626') : '';
                        const rxV = o.rx_power != null ? parseFloat(o.rx_power).toFixed(1) : '-';
                        return '<tr>' +
                            '<td><span class="fw-semibold">' + esc(o.customer) + '</span>' +
                            '<div class="mon-sub">' + esc(o.customer_code) + '</div></td>' +
                            '<td><code class="mon-code">' + esc(o.serial) + '</code></td>' +
                            '<td class="mon-muted">' + esc(o.olt) + '</td>' +
                            '<td class="text-center mon-rx" style="color:' + rxC + ';">' + rxV + '</td>' +
                            '<td class="mon-muted">' + esc(o.last_seen) + '</td></tr>';
                    }).join('');
                } else {
                    hsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada ONU Hotspot online</td></tr>';
                }

                // Akun PPP Aktif table
                const pppaTbody = $('#ppp-active-tbody');
                const pppaBadge = $('#ppp-active-badge');
                if (pppaBadge) pppaBadge.textContent = d.ppp_active_count ?? (d.ppp_active ? d.ppp_active.length : 0);
                if (pppaTbody) {
                    const pppa = d.ppp_active || [];
                    if (pppa.length) {
                        pppaTbody.innerHTML = pppa.map(s => {
                            const user = esc(s.name ?? s.user ?? '—');
                            const ip = esc(s.address ?? '—');
                            const mac = esc(s['caller-id'] ?? '—');
                            const up = esc(s.uptime ?? '—');
                            return '<tr>' +
                                '<td class="fw-semibold">' + user + '</td>' +
                                '<td><code class="mon-code">' + ip + '</code></td>' +
                                '<td class="mon-muted">' + mac + '</td>' +
                                '<td class="text-end mon-muted">' + up + '</td></tr>';
                        }).join('');
                    } else {
                        pppaTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada akun PPP aktif</td></tr>';
                    }
                }

                // Voucher Digunakan table
                const vaTbody = $('#voucher-active-tbody');
                const vaBadge = $('#voucher-active-badge');
                if (vaBadge) vaBadge.textContent = d.hotspot_active_count ?? (d.hotspot_active ? d.hotspot_active.length : 0);
                if (vaTbody) {
                    const va = d.hotspot_active || [];
                    if (va.length) {
                        vaTbody.innerHTML = va.map(s => {
                            const user = esc(s.user ?? s.name ?? '—');
                            const ip = esc(s.address ?? '—');
                            const mac = esc(s['mac-address'] ?? '—');
                            const up = esc(s.uptime ?? '—');
                            return '<tr>' +
                                '<td class="fw-semibold">' + user + '</td>' +
                                '<td><code class="mon-code">' + ip + '</code></td>' +
                                '<td class="mon-muted">' + mac + '</td>' +
                                '<td class="text-end mon-muted">' + up + '</td></tr>';
                        }).join('');
                    } else {
                        vaTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada voucher digunakan</td></tr>';
                    }
                }
            })
            .catch(() => {});
    }

    $('#router-select')?.addEventListener('change', function() {
        prevRx = 0; prevTx = 0; prevTime = 0;
        labels.length = 0; rxData.length = 0; txData.length = 0;
        for (let i = MAX_POINTS; i > 0; i--) { labels.push(''); rxData.push(0); txData.push(0); }
        if (chart) chart.update('none');
        fetchLive();
    });

    function start() {
        if (typeof Chart === 'undefined') {
            return setTimeout(start, 100);
        }
        buildChart();
        fetchLive();
        setInterval(fetchLive, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
