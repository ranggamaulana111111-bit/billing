@extends('layouts.app')

@section('title', 'Detail ODC - '.$odc->nama_odc)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-server me-2" style="color:var(--primary);"></i>Detail ODC</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $odc->nama_odc }}</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="{{ route('distribution.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                <i class="fa-solid fa-plug"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-available">{{ $odc->ports->where('status', 'available')->count() }}</div>
                <div class="stat-label">Port Tersedia</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">
                <i class="fa-solid fa-plug"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-used">{{ $odc->ports->where('status', 'used')->count() }}</div>
                <div class="stat-label">Port Terpakai</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(250,204,21,0.15);color:#eab308;">
                <i class="fa-solid fa-diagram-project"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="stat-odp-count">{{ $odc->odps->count() }}</div>
                <div class="stat-label">ODP Tersambung</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span>Peta Lokasi ODC & ODP</span>
    </div>
    <div class="card-body p-0">
        <div id="map-odc" style="height:350px;width:100%;border-radius:0 0 16px 16px;"></div>
    </div>
</div>

@if($odc->odps->count() > 0)
<div class="card mb-4">
    <div class="card-header bg-white">
        <i class="fa-solid fa-tower-cell me-1 text-muted"></i> Daftar ODP
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nama ODP</th>
                        <th>Tube</th>
                        <th>Core</th>
                        <th>Port</th>
                        <th>Kondisi Jalur</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($odc->odps as $odp)
                    <tr>
                        <td class="fw-medium">{{ $odp->nama_odp }}</td>
                        <td><span class="badge bg-secondary">{{ $odp->kabel_tube_color }}</span></td>
                        <td><span class="badge bg-dark">{{ $odp->kabel_core_number }}</span></td>
                        <td>
                            <span class="badge bg-success me-1">{{ $odp->availablePortsCount() }} tersedia</span>
                            <span class="badge bg-danger">{{ $odp->usedPortsCount() }} terpakai</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $odp->kondisi_jalur === 'UP' ? 'success' : 'danger' }}">
                                {{ $odp->kondisi_jalur === 'UP' ? 'NORMAL' : 'PUTUS' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('odp.show', $odp) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-table-cells me-1 text-muted"></i> Port ODC ({{ $odc->ports->count() }})</span>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small" id="live-indicator"><i class="fa-solid fa-circle text-success" style="font-size:8px;"></i> Live</span>
            <span class="text-muted small">Sisa <strong id="sisa-port">{{ $odc->ports->where('status', 'available')->count() }}</strong> port terbuka</span>
        </div>
    </div>
    <div class="card-body">
        <div class="port-grid" id="port-grid">
            @forelse($odc->ports as $port)
                @php
                    $portClass = match($port->status) {
                        'available' => 'port-available',
                        'used' => 'port-used',
                        'broken' => 'port-broken',
                        default => 'port-available',
                    };
                    $typeIcon = $port->port_type === 'inlet' ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket';
                    $customerCount = $port->connectedOdp?->ports->filter(fn($p) => $p->status === 'used' && $p->customer)->count() ?? 0;
                @endphp
                <div class="port-item {{ $portClass }}" data-port-id="{{ $port->id }}" data-status="{{ $port->status }}">
                    <span class="port-number">{{ $port->port_number }}</span>
                    <span class="port-type-badge"><i class="fa-solid {{ $typeIcon }}"></i> {{ $port->port_type }}</span>
                    @if($port->status === 'used' && $port->connectedOdp)
                        <span class="port-odp-name">{{ $port->connectedOdp->nama_odp }}</span>
                        <span class="port-customer-count">{{ $customerCount }} pelanggan</span>
                    @endif
                    @if($port->status === 'broken')
                        <span class="port-broken-label">RUSAK</span>
                    @endif
                </div>
            @empty
                <div class="col-12 text-center text-muted py-4">Belum ada port ODC.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var odcLat = {{ $odc->latitude ?? 'null' }};
    var odcLng = {{ $odc->longitude ?? 'null' }};
    var hasOdcCoord = odcLat !== null && odcLng !== null;

    var hasOdps = @json($odc->odps->filter(fn($o) => $o->latitude && $o->longitude)->count()) > 0;

    if (hasOdcCoord || hasOdps) {
        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        });
        var sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '&copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        });
        var map = L.map('map-odc', { layers: [sat] });
        L.control.layers({ 'Satelit': sat, 'Street': osm }).addTo(map);

        var bounds = [];
        var odcIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="width:28px;height:28px;background:#0f172a;border:3px solid #fff;border-radius:6px;box-shadow:0 2px 10px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;"><i class="fa-solid fa-server"></i></div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });

        var odpIconGreen = L.divIcon({
            className: 'custom-marker',
            html: '<div style="width:14px;height:14px;background:#059669;border:2px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        var odpIconRed = L.divIcon({
            className: 'custom-marker',
            html: '<div style="width:14px;height:14px;background:#dc2626;border:2px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        if (hasOdcCoord) {
            var marker = L.marker([odcLat, odcLng], { icon: odcIcon }).addTo(map);
            marker.bindPopup(`
                <div style="font-family:'Inter',sans-serif;min-width:160px;">
                    <h6 style="margin:0;font-weight:800;color:#0f172a;"><i class="fa-solid fa-server"></i> {{ $odc->nama_odc }}</h6>
                    <small style="color:#64748b;">Kapasitas: {{ $odc->kapasitas_port }} port</small>
                </div>
            `, { className: 'custom-popup' });
            bounds.push([odcLat, odcLng]);
        }

        @foreach($odc->odps as $odp)
            @if($odp->latitude && $odp->longitude)
                (function() {
                    var lat = {{ $odp->latitude }};
                    var lng = {{ $odp->longitude }};
                    var kondisi = '{{ $odp->kondisi_jalur }}';
                    var icon = kondisi === 'UP' ? odpIconGreen : odpIconRed;
                    var m = L.marker([lat, lng], { icon: icon }).addTo(map);
                    m.bindPopup(`
                        <div style="font-family:'Inter',sans-serif;min-width:160px;">
                            <h6 style="margin:0;font-weight:700;font-size:13px;color:#0f172a;">{{ $odp->nama_odp }}</h6>
                            <small style="color:#64748b;">Tube: {{ $odp->kabel_tube_color }} | Core: {{ $odp->kabel_core_number }}</small>
                            <div style="margin-top:6px;font-size:11px;color:#475569;">
                                Port: {{ $odp->usedPortsCount() }}/{{ $odp->kapasitas_port }} terpakai<br>
                                Jalur: <span style="color:{{ $odp->kondisi_jalur === 'UP' ? '#059669' : '#dc2626' }};font-weight:600;">{{ $odp->kondisi_jalur === 'UP' ? 'NORMAL' : 'PUTUS' }}</span>
                            </div>
                            <div style="margin-top:6px;"><a href="/odp/{{ $odp->id }}" style="font-size:11px;">&rarr; Detail ODP</a></div>
                        </div>
                    `, { className: 'custom-popup' });
                    bounds.push([lat, lng]);

                    if (hasOdcCoord) {
                        L.polyline([[odcLat, odcLng], [lat, lng]], {
                            color: '#94a3b8', weight: 1.5, opacity: 0.4, dashArray: '4, 4'
                        }).addTo(map);
                    }
                })();
            @endif
        @endforeach

        if (bounds.length > 0) {
            map.fitBounds(L.latLngBounds(bounds), { padding: [30, 30] });
        } else {
            map.setView([-6.476, 106.014], 14);
        }
    } else {
        document.getElementById('map-odc').innerHTML = '<div class="text-center py-5 text-muted"><i class="fa-solid fa-map me-2"></i>Koordinat belum diatur. Edit ODC untuk menambahkan lokasi.</div>';
    }

    // ── Realtime polling ──
    function refreshPorts() {
        fetch('/api/v1/odc/{{ $odc->id }}/ports')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('stat-available').textContent = data.ports.filter(function(p) { return p.status === 'available'; }).length;
                document.getElementById('stat-used').textContent = data.ports.filter(function(p) { return p.status === 'used'; }).length;
                document.getElementById('stat-odp-count').textContent = data.odc.odp_count;
                document.getElementById('sisa-port').textContent = data.ports.filter(function(p) { return p.status === 'available'; }).length;

                data.ports.forEach(function(p) {
                    var el = document.querySelector('.port-item[data-port-id="' + p.id + '"]');
                    if (!el) return;
                    var oldStatus = el.dataset.status;
                    if (oldStatus !== p.status) {
                        el.className = 'port-item port-' + p.status;
                        el.dataset.status = p.status;
                        if (p.status === 'used' && p.connected_odp) {
                            var nameEl = el.querySelector('.port-odp-name');
                            var countEl = el.querySelector('.port-customer-count');
                            if (nameEl) nameEl.textContent = p.connected_odp.nama_odp;
                            if (countEl) countEl.textContent = p.connected_odp.customer_count + ' pelanggan';
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
    min-height: 95px;
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
.port-type-badge { font-size: 0.6rem; color: #64748b; margin-top: 2px; }
.port-odp-name { font-size: 0.65rem; color: #1e293b; font-weight: 600; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.port-customer-count { font-size: 0.55rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
</style>
@endpush
