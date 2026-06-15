@extends('layouts.app')

@section('title', 'Monitoring Bandwidth')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-line me-2" style="color:var(--primary);"></i>Monitoring Bandwidth</h2>
        <p class="section-subtitle mb-0 mt-1">
            Pemakaian bandwidth real-time dari MikroTik
            <span class="badge bg-success ms-2" id="live-badge" style="font-size:0.65rem;">
                <span id="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
</div>

<div class="row g-3 mb-4" id="stats-cards">
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Ping</small>
                <h4 class="fw-bold mb-0" id="stat-ping">- ms</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Total Download</small>
                <h4 class="fw-bold mb-0" id="stat-rx">0 MB</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Total Upload</small>
                <h4 class="fw-bold mb-0" id="stat-tx">0 MB</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Sesi Aktif</small>
                <h4 class="fw-bold mb-0" id="stat-sessions">0</h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- HOTSPOT SESSIONS --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                <span>Hotspot Aktif (<span id="hotspot-count">0</span>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Uptime</th>
                            </tr>
                        </thead>
                        <tbody id="hotspot-tbody">
                            <tr><td colspan="5" class="text-center text-muted py-3">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- PPP ACTIVE --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;#8b5cf6;"></div>
                <span>PPPoE Aktif (<span id="ppp-count">0</span>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Uptime</th>
                            </tr>
                        </thead>
                        <tbody id="ppp-tbody">
                            <tr><td colspan="5" class="text-center text-muted py-3">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- INTERFACES --}}
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;#059669;"></div>
                <span>Interface</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Rx Rate</th>
                                <th>Tx Rate</th>
                                <th>Rx Total</th>
                                <th>Tx Total</th>
                            </tr>
                        </thead>
                        <tbody id="interface-tbody">
                            <tr><td colspan="7" class="text-center text-muted py-3">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- No js fallback --}}
<noscript>
    <div class="alert alert-warning mt-3">JavaScript diperlukan untuk live update. <a href="{{ url()->current() }}">Refresh manual</a></div>
</noscript>
@endsection

@push('scripts')
<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
.update-flash { animation: flash-highlight 0.6s ease; }
@keyframes flash-highlight { 0%{background:rgba(34,197,94,0.2)} 100%{background:transparent} }
</style>
<script>
(function() {
    const $ = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => (ctx || document).querySelectorAll(sel);

    let prevRx = {};
    let prevTx = {};

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function formatRate(bytesPerSec) {
        if (bytesPerSec <= 0) return '0 bps';
        const bits = bytesPerSec * 8;
        if (bits > 1000000000) return (bits / 1000000000).toFixed(2) + ' Gbps';
        if (bits > 1000000) return (bits / 1000000).toFixed(2) + ' Mbps';
        if (bits > 1000) return (bits / 1000).toFixed(1) + ' Kbps';
        return bits.toFixed(0) + ' bps';
    }

    function fetchLive() {
        fetch('{{ route("mikrotik.live") }}')
            .then(r => r.json())
            .then(d => {
                $('#stat-ping').textContent = d.ping !== null ? d.ping + ' ms' : '-';
                $('#stat-rx').textContent = formatBytes(d.total_rx);
                $('#stat-tx').textContent = formatBytes(d.total_tx);
                $('#stat-sessions').textContent = d.hotspot_count + d.ppp_count;
                $('#hotspot-count').textContent = d.hotspot_count;
                $('#ppp-count').textContent = d.ppp_count;

                // Hotspot sessions
                var htbody = $('#hotspot-tbody');
                if (d.sessions.length) {
                    htbody.innerHTML = d.sessions.map(s => '<tr>' +
                        '<td class="fw-medium">' + esc(s.user) + '</td>' +
                        '<td>' + esc(s.address) + '</td>' +
                        '<td>' + formatBytes(s.bytes_in) + '</td>' +
                        '<td>' + formatBytes(s.bytes_out) + '</td>' +
                        '<td>' + esc(s.uptime) + '</td>' +
                    '</tr>').join('');
                } else {
                    htbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi aktif</td></tr>';
                }

                // PPP
                var ptbody = $('#ppp-tbody');
                if (d.ppp.length) {
                    ptbody.innerHTML = d.ppp.map(s => '<tr>' +
                        '<td class="fw-medium">' + esc(s.user) + '</td>' +
                        '<td>' + esc(s.address) + '</td>' +
                        '<td>' + formatBytes(s.bytes_in) + '</td>' +
                        '<td>' + formatBytes(s.bytes_out) + '</td>' +
                        '<td>' + esc(s.uptime) + '</td>' +
                    '</tr>').join('');
                } else {
                    ptbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi aktif</td></tr>';
                }

                // Interfaces with rate
                var itbody = $('#interface-tbody');
                var now = Date.now();
                if (d.interfaces.length) {
                    itbody.innerHTML = d.interfaces.map(i => {
                        var rxRate = 0, txRate = 0;
                        if (prevRx[i.name] !== undefined && prevTx[i.name] !== undefined) {
                            var dt = (now - prevRx[i.name].time) / 1000;
                            if (dt > 0) {
                                rxRate = (i.rx_byte - prevRx[i.name].val) / dt;
                                txRate = (i.tx_byte - prevTx[i.name].val) / dt;
                            }
                        }
                        prevRx[i.name] = { val: i.rx_byte, time: now };
                        prevTx[i.name] = { val: i.tx_byte, time: now };

                        return '<tr>' +
                            '<td class="fw-medium">' + esc(i.name) + '</td>' +
                            '<td>' + esc(i.type) + '</td>' +
                            '<td>' + (i.running
                                ? '<span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">UP</span>'
                                : '<span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">DOWN</span>') +
                            '</td>' +
                            '<td style="color:#059669;font-weight:600;">' + formatRate(rxRate) + '</td>' +
                            '<td style="color:#2563eb;font-weight:600;">' + formatRate(txRate) + '</td>' +
                            '<td class="text-muted" style="font-size:0.8rem;">' + formatBytes(i.rx_byte) + '</td>' +
                            '<td class="text-muted" style="font-size:0.8rem;">' + formatBytes(i.tx_byte) + '</td>' +
                        '</tr>';
                    }).join('');
                } else {
                    itbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada interface</td></tr>';
                }
            })
            .catch(function() {
                // silently retry
            });
    }

    function esc(s) {
        if (s == null) return '-';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Initial load
    fetchLive();

    // Poll every 3 seconds
    setInterval(fetchLive, 3000);
})();
</script>
@endpush
