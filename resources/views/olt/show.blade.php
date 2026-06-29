@extends('layouts.app')

@section('title', $olt->name)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map-show { height: 220px; border-radius: 12px; z-index: 0; }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:var(--primary);"></i>{{ $olt->name }}</h2>
        <p class="section-subtitle mb-0 mt-1">
            {{ ucfirst($olt->brand) }} {{ $olt->model }} &mdash; <code>{{ $olt->ip_address }}:{{ $olt->ssh_port }}</code>
            @if($olt->location) &mdash; {{ $olt->location }} @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <span class="badge bg-success me-2" id="live-badge" style="font-size:0.65rem;">
            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
        </span>
        <form action="{{ route('olt.test', $olt) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-success px-3 py-2" title="Test Koneksi">
                <i class="fa-solid fa-plug me-1"></i>Test Koneksi
            </button>
        </form>
        <form action="{{ route('olt.scan', $olt) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary px-3 py-2" title="Scan ONU">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Scan ONU
            </button>
        </form>
        <form action="{{ route('olt.sync-mikrotik', $olt) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-info px-3 py-2" onclick="return confirm('Sync ONU dari PPPoE aktif MikroTik?')">
                <i class="fa-solid fa-rotate me-1"></i>Sync dari MikroTik
            </button>
        </form>
        <button type="button" class="btn btn-outline-info px-3 py-2" data-bs-toggle="modal" data-bs-target="#syncPortsModal">
            <i class="fa-solid fa-sync me-1"></i>Sync Ports
        </button>
        <a href="{{ route('olt.edit', $olt) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-pen me-1"></i>Edit
        </a>
        <a href="{{ route('olt.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0s">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Ping</small>
                <h4 class="fw-bold mb-0" id="olt-ping">-</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-network-wired"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number" id="olt-total-port">{{ $totalPorts }}</div>
                <div class="stat-label">Total Port</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-wifi"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number" id="olt-total-onu">{{ $totalOnus }}</div>
                <div class="stat-label">Total ONU</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#22c55e,#16a34a);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number" id="olt-online-onu">{{ $onlineOnus }}</div>
                <div class="stat-label">ONU Online</div>
            </div>
        </div>
    </div>
</div>

{{-- MINI MAP --}}
@if($olt->latitude && $olt->longitude)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span>Lokasi OLT</span>
        <small class="text-muted">{{ $olt->latitude }}, {{ $olt->longitude }}</small>
        <a href="{{ route('olt.edit', $olt) }}" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="fa-solid fa-pen me-1"></i>Ubah Lokasi
        </a>
    </div>
    <div class="card-body p-0">
        <div id="map-show"></div>
    </div>
</div>
@endif

{{-- PORTS --}}
@forelse($olt->ports as $port)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="fa-solid fa-plug me-1"></i>Slot {{ $port->slot_number }} / Port {{ $port->port_number }}
                <span class="badge bg-info ms-2">{{ strtoupper($port->port_type) }}</span>
                @if($port->status === 'active')
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-danger">Inactive</span>
                @endif
            </span>
            <span class="text-muted small" id="onu-count-{{ $port->id }}">{{ $port->onus->count() }} ONU</span>
        </div>
        @if($port->onus->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ONU ID</th>
                            <th>Serial Number</th>
                            <th>Caller ID</th>
                            <th>Status</th>
                            <th>Rx Power</th>
                            <th>Tx Power</th>
                            <th>Pelanggan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="onu-tbody-{{ $port->id }}">
                        @foreach($port->onus as $onu)
                            <tr>
                                <td><code>{{ $onu->onu_id }}</code></td>
                                <td><code>{{ $onu->serial_number ?? '-' }}</code></td>
                                <td><code>{{ $onu->caller_id ?? '-' }}</code></td>
                                <td>
                                    @if($onu->status === 'online')
                                        <span class="badge bg-success">Online</span>
                                    @else
                                        <span class="badge bg-secondary">Offline</span>
                                    @endif
                                </td>
                                <td class="{{ $onu->rx_power !== null && $onu->rx_power < -27 ? 'text-danger' : '' }}">
                                    {{ $onu->rx_power !== null ? $onu->rx_power.' dBm' : '-' }}
                                </td>
                                <td>{{ $onu->tx_power !== null ? $onu->tx_power.' dBm' : '-' }}</td>
                                <td>
                                    @if($onu->customer)
                                        <a href="{{ route('customers.index') }}?search={{ $onu->customer->name }}" class="text-decoration-none">
                                            {{ $onu->customer->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">Belum ditautkan</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('olt.onu.reboot', [$olt, $onu]) }}" method="POST" class="d-inline" onsubmit="return confirm('Reboot ONU {{ $onu->onu_id }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" title="Reboot"><i class="fa-solid fa-rotate"></i></button>
                                    </form>
                                    <form action="{{ route('olt.onu.remove', [$olt, $onu]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus ONU {{ $onu->onu_id }} dari OLT?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body text-muted text-center py-3">Tidak ada ONU di port ini.</div>
            <span id="onu-count-{{ $port->id }}" style="display:none;">0 ONU</span>
        @endif
    </div>
@empty
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa-solid fa-plug fa-3x mb-3" style="opacity:0.3;"></i>
            <p>Belum ada port. Tambah port melalui edit atau scan ONU.</p>
        </div>
    </div>
@endforelse
{{-- SYNC PORTS MODAL --}}
<div class="modal fade" id="syncPortsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('olt.ports.sync', $olt) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Sync Ports OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Tambah satu atau beberapa port sekaligus.</p>
                    <div id="port-rows">
                        <div class="row g-2 mb-2 port-row">
                            <div class="col-4">
                                <input type="number" name="ports[0][slot]" class="form-control" placeholder="Slot" value="0" required>
                            </div>
                            <div class="col-4">
                                <input type="number" name="ports[0][port]" class="form-control" placeholder="Port" value="0" required>
                            </div>
                            <div class="col-4">
                                <select name="ports[0][type]" class="form-select">
                                    <option value="gpon">GPON</option>
                                    <option value="xgspon">XGS-PON</option>
                                    <option value="epon">EPON</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPortRow()">
                        <i class="fa-solid fa-plus"></i> Tambah Baris
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Sync</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let portIndex = 1;
function addPortRow() {
    const html = `<div class="row g-2 mb-2 port-row">
        <div class="col-4">
            <input type="number" name="ports[${portIndex}][slot]" class="form-control" placeholder="Slot" value="0" required>
        </div>
        <div class="col-4">
            <input type="number" name="ports[${portIndex}][port]" class="form-control" placeholder="Port" value="${portIndex}" required>
        </div>
        <div class="col-4">
            <select name="ports[${portIndex}][type]" class="form-select">
                <option value="gpon">GPON</option>
                <option value="xgspon">XGS-PON</option>
                <option value="epon">EPON</option>
            </select>
        </div>
    </div>`;
    document.getElementById('port-rows').insertAdjacentHTML('beforeend', html);
    portIndex++;
}
</script>

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
</style>
<script>
(function() {
    const $ = (sel, ctx) => (ctx || document).querySelector(sel);

    var csrfToken = '{{ csrf_token() }}';
    var oltId = {{ $olt->id }};
    var consecutiveErrors = 0;
    var polling = false;
    var errorBadge = null;

    function esc(s) {
        if (s == null || s === '') return '-';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function showError(show) {
        if (!errorBadge) {
            var p = $('#live-badge');
            if (!p) return;
            errorBadge = document.createElement('span');
            errorBadge.className = 'badge bg-danger ms-1';
            errorBadge.style.cssText = 'font-size:0.65rem;';
            errorBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Gagal ambil data';
            p.parentNode.insertBefore(errorBadge, p.nextSibling);
        }
        errorBadge.classList.toggle('d-none', !show);
    }

    function fetchLive() {
        if (polling) return;
        polling = true;

        fetch('{{ route("olt.live", $olt) }}')
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(d => {
                consecutiveErrors = 0;
                showError(false);

                var pingEl = $('#olt-ping');
                if (pingEl) pingEl.textContent = d.ping !== null ? d.ping + ' ms' : '-';
                var totalOnuEl = $('#olt-total-onu');
                if (totalOnuEl) totalOnuEl.textContent = d.total_onus;
                var onlineOnuEl = $('#olt-online-onu');
                if (onlineOnuEl) onlineOnuEl.textContent = d.online_onus;

                d.ports.forEach(function(p) {
                    var tbody = $('#onu-tbody-' + p.id);
                    if (!tbody) return;

                    if (p.onus.length) {
                        tbody.innerHTML = p.onus.map(function(o) {
                            var statusBadge = o.status === 'online'
                                ? '<span class="badge bg-success">Online</span>'
                                : '<span class="badge bg-secondary">Offline</span>';
                            var rxClass = (o.rx_power !== null && o.rx_power < -27) ? 'text-danger' : '';
                            var rxVal = o.rx_power !== null ? o.rx_power + ' dBm' : '-';
                            var txVal = o.tx_power !== null ? o.tx_power + ' dBm' : '-';
                            var customerHtml = o.customer_name
                                ? '<a href="{{ route("customers.index") }}?search=' + encodeURIComponent(o.customer_name) + '" class="text-decoration-none">' + esc(o.customer_name) + '</a>'
                                : '<span class="text-muted">Belum ditautkan</span>';
                            var csrfInput = '<input type="hidden" name="_token" value="' + csrfToken + '">';

                            return '<tr>' +
                                '<td><code>' + esc(o.onu_id) + '</code></td>' +
                                '<td><code>' + esc(o.serial_number || '-') + '</code></td>' +
                                '<td><code>' + esc(o.caller_id || '-') + '</code></td>' +
                                '<td>' + statusBadge + '</td>' +
                                '<td class="' + rxClass + '">' + rxVal + '</td>' +
                                '<td>' + txVal + '</td>' +
                                '<td>' + customerHtml + '</td>' +
                                '<td class="text-end">' +
                                '  <form action="/olts/' + oltId + '/onu/' + o.id + '/reboot" method="POST" class="d-inline" onsubmit="return confirm(\'Reboot ONU ' + esc(o.onu_id) + '?\')">' + csrfInput + '<button class="btn btn-sm btn-outline-warning" title="Reboot"><i class="fa-solid fa-rotate"></i></button></form>' +
                                '  <form action="/olts/' + oltId + '/onu/' + o.id + '" method="POST" class="d-inline" onsubmit="return confirm(\'Hapus ONU ' + esc(o.onu_id) + '?\')">' + csrfInput + '<input type="hidden" name="_method" value="DELETE"><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button></form>' +
                                '</td>' +
                            '</tr>';
                        }).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Tidak ada ONU di port ini.</td></tr>';
                    }

                    var countEl = $('#onu-count-' + p.id);
                    if (countEl) countEl.textContent = p.onus.length + ' ONU';
                });

                polling = false;
                scheduleNext();
            })
            .catch(function() {
                consecutiveErrors++;
                showError(consecutiveErrors >= 2);
                polling = false;
                scheduleNext();
            });
    }

    function scheduleNext() {
        var delay = consecutiveErrors > 0
            ? Math.min(5000 * Math.pow(2, consecutiveErrors), 30000)
            : 5000;
        setTimeout(fetchLive, delay);
    }

    // Initial load after 1s
    setTimeout(fetchLive, 1000);
})();
</script>
@if($olt->latitude && $olt->longitude)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var lat = {{ $olt->latitude }};
    var lng = {{ $olt->longitude }};

    var map = L.map('map-show').setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'custom-marker',
            html: '<div style="width:24px;height:24px;background:var(--primary);border:3px solid #fff;border-radius:6px;box-shadow:0 2px 10px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;"><i class="fa-solid fa-tower-cell"></i></div>',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        })
    }).addTo(map);
});
</script>
@endif
@endpush
