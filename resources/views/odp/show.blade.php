@extends('layouts.app')

@section('title', 'Detail ODP - '.$odp->nama_odp)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:var(--primary);"></i>Detail ODP</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $odp->nama_odp }}</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="{{ route('distribution.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                <i class="fa-solid fa-plug"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-available">{{ $odp->availablePortsCount() }}</div>
                <div class="stat-label">Port Tersedia</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">
                <i class="fa-solid fa-plug"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-used">{{ $odp->usedPortsCount() }}</div>
                <div class="stat-label">Port Terpakai</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(250,204,21,0.15);color:#eab308;">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-broken">{{ $odp->brokenPortsCount() }}</div>
                <div class="stat-label">Port Rusak</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:{{ $odp->kondisi_jalur === 'UP' ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' }};color:{{ $odp->kondisi_jalur === 'UP' ? '#22c55e' : '#ef4444' }};">
                <i class="fa-solid fa-road"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:0.9rem">{{ $odp->kondisi_jalur === 'UP' ? 'NORMAL' : 'PUTUS' }}</div>
                <div class="stat-label">Kondisi Jalur</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <i class="fa-solid fa-info-circle me-1 text-muted"></i> Jalur Distribusi
    </div>
    <div class="card-body">
        <p class="mb-0 fs-5 fw-medium">
            {{ $odp->odc?->nama_odc ?? 'Tanpa ODC' }}
            @if($odp->connectedOdcPort)
                <span class="badge bg-dark ms-1">Port ODC #{{ $odp->connectedOdcPort->port_number }}</span>
            @endif
            <i class="fa-solid fa-arrow-right mx-2 text-muted"></i>
            Tube: <span class="badge bg-secondary">{{ $odp->kabel_tube_color }}</span>
            <i class="fa-solid fa-arrow-right mx-2 text-muted"></i>
            Core: <span class="badge bg-dark">{{ $odp->kabel_core_number }}</span>
        </p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span>Peta Lokasi ODP</span>
    </div>
    <div class="card-body p-0">
        <div id="map-odp" style="height:300px;width:100%;border-radius:0 0 16px 16px;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-table-cells me-1 text-muted"></i> Port ODP ({{ $odp->kapasitas_port }})</span>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small" id="live-indicator"><i class="fa-solid fa-circle text-success" style="font-size:8px;"></i> Live</span>
            <span class="text-muted small">Sisa <strong id="sisa-port">{{ $odp->availablePortsCount() }}</strong> port terbuka</span>
        </div>
    </div>
    <div class="card-body">
        <div class="port-grid" id="port-grid">
            @foreach($odp->ports as $port)
                @php
                    $portClass = match($port->status) {
                        'available' => 'port-available',
                        'used' => 'port-used',
                        'broken' => 'port-broken',
                        default => 'port-available',
                    };
                @endphp
                <div class="port-item {{ $portClass }}" data-port="{{ $port->id }}" data-status="{{ $port->status }}" data-customer="{{ $port->customer?->name ?? '' }}">
                    <span class="port-number">{{ $port->port_number }}</span>
                    @if($port->status === 'used' && $port->customer)
                        <span class="port-customer-name">{{ $port->customer->name }}</span>
                        <span class="port-customer-info">{{ $port->customer->package?->name ?? 'No Pkg' }}</span>
                    @endif
                    @if($port->status === 'broken')
                        <span class="port-broken-label">RUSAK</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var odpLat = {{ $odp->latitude ?? 'null' }};
    var odpLng = {{ $odp->longitude ?? 'null' }};

    if (odpLat !== null && odpLng !== null) {
        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        });
        var sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '&copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        });
        var map = L.map('map-odp', { layers: [sat] });
        L.control.layers({ 'Satelit': sat, 'Street': osm }).addTo(map);

        var marker = L.marker([odpLat, odpLng]).addTo(map);
        marker.bindPopup(`
            <div style="font-family:'Inter',sans-serif;min-width:160px;">
                <h6 style="margin:0;font-weight:700;color:#0f172a;">{{ $odp->nama_odp }}</h6>
                <small style="color:#64748b;">{{ $odp->odc?->nama_odc ?? 'Tanpa ODC' }}</small>
                <div style="margin-top:6px;font-size:11px;color:#475569;">
                    Port: {{ $odp->usedPortsCount() }}/{{ $odp->kapasitas_port }} terpakai<br>
                    Tube: {{ $odp->kabel_tube_color }} | Core: {{ $odp->kabel_core_number }}<br>
                    Jalur: <span style="color:{{ $odp->kondisi_jalur === 'UP' ? '#059669' : '#dc2626' }};font-weight:600;">{{ $odp->kondisi_jalur === 'UP' ? 'NORMAL' : 'PUTUS' }}</span>
                </div>
                @if($odp->odc?->latitude && $odp->odc?->longitude)
                    <div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;color:#64748b;">
                        ODC: {{ $odp->odc->nama_odc }}
                    </div>
                @endif
            </div>
        `, { className: 'custom-popup' });

        @if($odp->odc?->latitude && $odp->odc?->longitude)
            var odcLat = {{ $odp->odc->latitude }};
            var odcLng = {{ $odp->odc->longitude }};
            var odcIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:22px;height:22px;background:#0f172a;border:3px solid #fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;"><i class="fa-solid fa-server"></i></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });
            L.marker([odcLat, odcLng], { icon: odcIcon }).addTo(map)
                .bindPopup('<div style="font-family:\'Inter\',sans-serif;"><h6 style="margin:0;font-weight:700;font-size:13px;">{{ $odp->odc->nama_odc }}</h6><small style="color:#64748b;">ODC Induk</small></div>', { className: 'custom-popup' });

            L.polyline([[odcLat, odcLng], [odpLat, odpLng]], {
                color: '#94a3b8', weight: 2, opacity: 0.5, dashArray: '5, 5'
            }).addTo(map);

            map.fitBounds(L.latLngBounds([[odcLat, odcLng], [odpLat, odpLng]]), { padding: [30, 30] });
        @else
            map.setView([odpLat, odpLng], 16);
        @endif
    } else {
        document.getElementById('map-odp').innerHTML = '<div class="text-center py-4 text-muted"><i class="fa-solid fa-map me-2"></i>Koordinat belum diatur.</div>';
    }

    // ── Realtime polling ──
    function refreshPorts() {
        fetch('/api/v1/odp/{{ $odp->id }}/ports')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('stat-available').textContent = data.odp.available;
                document.getElementById('stat-used').textContent = data.odp.used;
                document.getElementById('stat-broken').textContent = data.odp.broken;
                document.getElementById('sisa-port').textContent = data.odp.available;

                data.ports.forEach(function(p) {
                    var el = document.querySelector('.port-item[data-port="' + p.id + '"]');
                    if (!el) return;
                    var oldStatus = el.dataset.status;
                    if (oldStatus !== p.status) {
                        el.className = 'port-item port-' + p.status;
                        el.dataset.status = p.status;
                        var oldContent = '';
                        if (p.status === 'used' && p.customer) {
                            el.innerHTML = '<span class="port-number">' + p.port_number + '</span><span class="port-customer-name">' + p.customer.name + '</span><span class="port-customer-info">' + (p.customer.package || 'No Pkg') + '</span>';
                        } else if (p.status === 'broken') {
                            el.innerHTML = '<span class="port-number">' + p.port_number + '</span><span class="port-broken-label">RUSAK</span>';
                        } else {
                            el.innerHTML = '<span class="port-number">' + p.port_number + '</span>';
                        }
                    }
                });

                var indicator = document.getElementById('live-indicator');
                indicator.innerHTML = '<i class="fa-solid fa-circle text-success" style="font-size:8px;"></i> Live';
            })
            .catch(function() {
                var indicator = document.getElementById('live-indicator');
                indicator.innerHTML = '<i class="fa-solid fa-circle text-muted" style="font-size:8px;"></i> Offline';
            });
    }

    setInterval(refreshPorts, 15000);
});
</script>
<style>
.port-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
}
.port-item {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    transition: all 0.2s;
    cursor: default;
    position: relative;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.port-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.port-available {
    background: #f0fdf4;
    border-color: #86efac;
}
.port-available .port-number { color: #16a34a; }
.port-used {
    background: #fef2f2;
    border-color: #fca5a5;
}
.port-used .port-number { color: #dc2626; }
.port-broken {
    background: #1e293b;
    border-color: #64748b;
    animation: blink-broken 1.2s ease-in-out infinite;
}
.port-broken .port-number { color: #94a3b8; }
.port-broken .port-broken-label { color: #ef4444; font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; display: block; }
@keyframes blink-broken {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.port-number { font-weight: 800; font-size: 1.1rem; display: block; }
.port-customer-name { font-size: 0.65rem; color: #1e293b; font-weight: 600; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.port-customer-info { font-size: 0.55rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
</style>
@endpush
