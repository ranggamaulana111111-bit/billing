@extends('layouts.app')

@section('title', 'Map OLT')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
    #map { height: 520px; width: 100%; border-radius: 0 0 16px 16px; }
    .olt-marker {
        display: flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 8px;
        border: 3px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        font-size: 14px; color: #fff;
    }
    .custom-popup .leaflet-popup-content-wrapper {
        border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    .custom-popup .leaflet-popup-content { margin: 12px 16px; font-family: 'Inter', sans-serif; }
    .search-box {
        position: relative;
    }
    .search-box input {
        padding-left: 36px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        font-size: 13px;
        height: 38px;
        width: 240px;
    }
    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
    }
    .leaflet-marker-icon.marker-cluster {
        background: rgba(37,99,235,0.2);
        border: 2px solid var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        color: var(--primary);
    }
    .legend {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #475569;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 4px;
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-map-location-dot me-2" style="color:var(--primary);"></i>Map OLT</h2>
        <p class="section-subtitle mb-0 mt-1">Visualisasi sebaran perangkat OLT — klik peta saat tambah/edit OLT untuk set lokasi</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('olt.create') }}" class="btn btn-primary px-3 py-2">
            <i class="fa-solid fa-plus me-1"></i>Tambah OLT
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif

{{-- STATS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-tower-cell"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $oltData->count() }}</div>
                <div class="stat-label">Total OLT</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-check-circle"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $oltData->where('status', 'active')->count() }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-wrench"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $oltData->where('status', 'maintenance')->count() }}</div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-red text-white">
            <div class="stat-bg"><i class="fa-solid fa-ban"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $oltData->where('status', 'inactive')->count() }}</div>
                <div class="stat-label">Nonaktif</div>
            </div>
        </div>
    </div>
</div>

{{-- MAP --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Peta Sebaran OLT</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $oltData->where('latitude')->count() }} titik</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="legend">
                <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span>Aktif</span>
                <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span>Maintenance</span>
                <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span>Nonaktif</span>
            </div>
            <div class="search-box">
                <i class="fa-solid fa-search search-icon"></i>
                <input type="text" id="searchOlt" placeholder="Cari OLT..." autocomplete="off">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="map"></div>
    </div>
</div>

{{-- OLT LIST --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="oltTable">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Brand</th>
                        <th>IP</th>
                        <th>Lokasi</th>
                        <th class="text-center">Port</th>
                        <th class="text-center">ONU</th>
                        <th>Status</th>
                        <th>Last Polled</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($oltData as $olt)
                        <tr>
                            <td class="fw-semibold">{{ $olt['name'] }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($olt['brand']) }}</span></td>
                            <td><code>{{ $olt['ip_address'] }}</code></td>
                            <td>{{ $olt['location'] ?? '-' }}</td>
                            <td class="text-center">{{ $olt['ports_count'] }}</td>
                            <td class="text-center">{{ $olt['total_onus'] }} <small class="text-success">({{ $olt['online_onus'] }} online)</small></td>
                            <td>
                                @php $s = $olt['status']; @endphp
                                <span class="badge bg-{{ $s === 'active' ? 'success' : ($s === 'maintenance' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($s) }}
                                </span>
                            </td>
                            <td>{{ $olt['last_polled_at'] ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('olt.show', $olt['id']) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada OLT.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('map').setView([-6.476, 106.014], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            className: 'map-tiles'
        }).addTo(map);

        var olts = @json($oltData);
        var markers = [];
        var markerBounds = [];

        var statusColors = {
            active: '#22c55e',
            maintenance: '#f59e0b',
            inactive: '#ef4444'
        };

        var statusIcons = {
            active: 'fa-check-circle',
            maintenance: 'fa-wrench',
            inactive: 'fa-ban'
        };

        var mcg = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            iconCreateFunction: function(cluster) {
                var count = cluster.getChildCount();
                var size = count < 10 ? '32px' : (count < 50 ? '38px' : '46px');
                return L.divIcon({
                    html: '<div class="marker-cluster" style="width:'+size+';height:'+size+';">' + count + '</div>',
                    className: 'custom-marker',
                    iconSize: L.point(parseInt(size), parseInt(size))
                });
            }
        });

        olts.forEach(function(olt) {
            if (olt.latitude && olt.longitude) {
                var color = statusColors[olt.status] || '#64748b';

                var icon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div class="olt-marker" style="background:${color}"><i class="fa-solid ${statusIcons[olt.status] || 'fa-tower-cell'}"></i></div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                var marker = L.marker([olt.latitude, olt.longitude], { icon: icon });

                var statusLabel = olt.status === 'active' ? 'Aktif' : (olt.status === 'maintenance' ? 'Maintenance' : 'Nonaktif');

                marker.bindPopup(`
                    <div style="min-width:200px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:${color};"></div>
                            <h6 style="margin:0;font-weight:700;font-size:14px;">${olt.name}</h6>
                        </div>
                        <small style="color:#64748b;"><i class="fa-solid fa-server"></i> ${olt.brand} &mdash; ${olt.ip_address}</small>
                        ${olt.location ? `<br><small style="color:#64748b;"><i class="fa-solid fa-location-dot"></i> ${olt.location}</small>` : ''}
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;font-size:12px;color:#475569;">
                            <span class="badge bg-${olt.status === 'active' ? 'success' : (olt.status === 'maintenance' ? 'warning' : 'danger')}">${statusLabel}</span>
                            <br>
                            Port: <strong>${olt.ports_count ?? 0}</strong> &bull; ONU: <strong>${olt.total_onus ?? 0}</strong> <small class="text-success">(${olt.online_onus ?? 0} online)</small>
                            ${olt.last_polled_at ? `<br>Last Polled: ${olt.last_polled_at}` : ''}
                        </div>
                        <div style="margin-top:8px;">
                            <a href="${'/olts/' + olt.id}" style="font-size:12px;">&rarr; Detail OLT</a>
                        </div>
                    </div>
                `, { className: 'custom-popup' });

                mcg.addLayer(marker);
                markers.push({ marker: marker, data: olt });
                markerBounds.push([olt.latitude, olt.longitude]);
            }
        });

        map.addLayer(mcg);

        if (markerBounds.length > 0) {
            var bounds = L.latLngBounds(markerBounds);
            map.fitBounds(bounds, { padding: [40, 40] });
        }

        {{-- SEARCH FILTER --}}
        document.getElementById('searchOlt').addEventListener('input', function(e) {
            var query = e.target.value.toLowerCase().trim();
            var visibleCount = 0;

            mcg.clearLayers();

            markers.forEach(function(item) {
                var olt = item.data;
                var match = olt.name.toLowerCase().includes(query)
                    || olt.brand.toLowerCase().includes(query)
                    || olt.ip_address.toLowerCase().includes(query)
                    || (olt.location && olt.location.toLowerCase().includes(query));

                if (match) {
                    mcg.addLayer(item.marker);
                    visibleCount++;
                }
            });

            {{-- Show count in header --}}
            var badge = document.querySelector('.badge-premium');
            if (badge) {
                badge.textContent = visibleCount + ' titik' + (query ? ' (filter)' : '');
            }
        });
    });
</script>
@endpush
