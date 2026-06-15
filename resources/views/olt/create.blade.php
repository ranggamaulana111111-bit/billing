@extends('layouts.app')

@section('title', 'Tambah OLT')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map-create { height: 320px; border-radius: 12px; z-index: 0; }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plus me-2" style="color:var(--primary);"></i>Tambah OLT</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('olt.map') }}" class="btn btn-outline-primary px-3 py-2 me-2">
            <i class="fa-solid fa-map-location-dot me-1"></i>Map OLT
        </a>
        <a href="{{ route('olt.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-custom alert-danger mb-4">{{ $errors->first() }}</div>
@endif

<div class="card">
    <div class="card-body">
        <form action="{{ route('olt.store') }}" method="POST">
            @csrf

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Nama OLT <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                    <select name="brand" class="form-select" required>
                        <option value="">Pilih Brand</option>
                        <option value="huawei" {{ old('brand') === 'huawei' ? 'selected' : '' }}>Huawei</option>
                        <option value="zte" {{ old('brand') === 'zte' ? 'selected' : '' }}>ZTE</option>
                        <option value="fiberhome" {{ old('brand') === 'fiberhome' ? 'selected' : '' }}>FiberHome</option>
                        <option value="cdata" {{ old('brand') === 'cdata' ? 'selected' : '' }}>C-Data</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Model</label>
                    <input type="text" name="model" class="form-control" value="{{ old('model') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">IP Address <span class="text-danger">*</span></label>
                    <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">SSH Port</label>
                    <input type="number" name="ssh_port" class="form-control" value="{{ old('ssh_port', 22) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username SSH</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password SSH</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">SNMP Community</label>
                    <input type="text" name="snmp_community" class="form-control" value="{{ old('snmp_community') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">SNMP Version</label>
                    <select name="snmp_version" class="form-select">
                        <option value="">Pilih</option>
                        <option value="v1" {{ old('snmp_version') === 'v1' ? 'selected' : '' }}>v1</option>
                        <option value="v2c" {{ old('snmp_version') === 'v2c' ? 'selected' : '' }}>v2c</option>
                        <option value="v3" {{ old('snmp_version') === 'v3' ? 'selected' : '' }}>v3</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">SNMP Port</label>
                    <input type="number" name="snmp_port" class="form-control" value="{{ old('snmp_port', 161) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Lokasi <span class="text-muted small">(klik peta untuk set koordinat)</span></label>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="number" step="any" name="latitude" id="lat" class="form-control" placeholder="Latitude" value="{{ old('latitude') }}">
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="any" name="longitude" id="lng" class="form-control" placeholder="Longitude" value="{{ old('longitude') }}">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="location" class="form-control" placeholder="Nama lokasi (alamat)" value="{{ old('location') }}">
                        </div>
                    </div>
                    <div id="map-create"></div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="fa-solid fa-save me-1"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var defaultLat = parseFloat(latInput.value) || -6.476;
    var defaultLng = parseFloat(lngInput.value) || 106.014;

    var map = L.map('map-create').setView([defaultLat, defaultLng], defaultLat !== -6.476 ? 15 : 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var marker;

    function placeMarker(lat, lng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);

        marker.on('dragend', function() {
            var pos = marker.getLatLng();
            latInput.value = pos.lat.toFixed(6);
            lngInput.value = pos.lng.toFixed(6);
        });
    }

    if (latInput.value && lngInput.value && defaultLat !== -6.476) {
        placeMarker(defaultLat, defaultLng);
    }

    map.on('click', function(e) {
        placeMarker(e.latlng.lat, e.latlng.lng);
    });

    latInput.addEventListener('change', function() {
        var lat = parseFloat(this.value);
        var lng = parseFloat(lngInput.value);
        if (!isNaN(lat) && !isNaN(lng)) {
            map.setView([lat, lng], 15);
            placeMarker(lat, lng);
        }
    });

    lngInput.addEventListener('change', function() {
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(this.value);
        if (!isNaN(lat) && !isNaN(lng)) {
            map.setView([lat, lng], 15);
            placeMarker(lat, lng);
        }
    });
});
</script>
@endpush
