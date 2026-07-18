@extends('layouts.app')

@section('title', 'Edit ' . $router->name . ' — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit {{ $router->name }}</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.mikrotik-devices.show', $router) }}" class="btn btn-outline-info px-3 py-2">
            <i class="fa-solid fa-eye me-1"></i>Detail
        </a>
        <a href="{{ route('noc.mikrotik-devices.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-custom alert-danger mb-4">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('noc.mikrotik-devices.update', $router) }}" method="POST">
    @csrf @method('PUT')

    {{-- CONNECTION INFO --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header border-0" style="background:rgba(37,99,235,0.06);">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plug me-2" style="color:var(--primary);"></i>Informasi Koneksi</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nama Device <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $router->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Identity (RouterOS)</label>
                    <input type="text" name="identity" class="form-control" value="{{ old('identity', $router->identity) }}" placeholder="Otomatis terisi saat test koneksi">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipe Router <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        <option value="pppoe" {{ old('type', $router->type) === 'pppoe' ? 'selected' : '' }}>PPPoE (Utama)</option>
                        <option value="bandwidth" {{ old('type', $router->type) === 'bandwidth' ? 'selected' : '' }}>Bandwidth (HTB)</option>
                        <option value="general" {{ old('type', $router->type) === 'general' ? 'selected' : '' }}>General</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Host / IP Address <span class="text-danger">*</span></label>
                    <input type="text" name="host" class="form-control" value="{{ old('host', $router->host) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Port REST <span class="text-danger">*</span></label>
                    <input type="number" name="port" class="form-control" value="{{ old('port', $router->port) }}" min="1" max="65535" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">SSH Port</label>
                    <input type="number" name="ssh_port" class="form-control" value="{{ old('ssh_port', $router->ssh_port) }}" min="1" max="65535">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">API SSL Port</label>
                    <input type="number" name="api_ssl_port" class="form-control" value="{{ old('api_ssl_port', $router->api_ssl_port) }}" min="1" max="65535">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $router->username) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Connection Type <span class="text-danger">*</span></label>
                    <select name="connection_type" class="form-select" required>
                        <option value="rest_api" {{ old('connection_type', $router->connection_type) === 'rest_api' ? 'selected' : '' }}>REST API (HTTP)</option>
                        <option value="api_ssl" {{ old('connection_type', $router->connection_type) === 'api_ssl' ? 'selected' : '' }}>REST API (HTTPS/SSL)</option>
                        <option value="ssh" {{ old('connection_type', $router->connection_type) === 'ssh' ? 'selected' : '' }}>SSH (RouterOS v6)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Timeout (detik)</label>
                    <input type="number" name="timeout" class="form-control" value="{{ old('timeout', $router->timeout) }}" min="1" max="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Hotspot Server</label>
                    <input type="text" name="hotspot_server" class="form-control" value="{{ old('hotspot_server', $router->hotspot_server) }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" {{ old('is_active', $router->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SITE & LOCATION --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header border-0" style="background:rgba(5,150,105,0.06);">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-location-dot me-2" style="color:#059669;"></i>Site & Lokasi</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Site</label>
                    <input type="text" name="site" class="form-control" value="{{ old('site', $router->site) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lokasi / Address</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location', $router->location) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $router->timezone) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Latitude</label>
                    <input type="number" name="latitude" class="form-control" value="{{ old('latitude', $router->latitude) }}" step="0.0000001" min="-90" max="90">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Longitude</label>
                    <input type="number" name="longitude" class="form-control" value="{{ old('longitude', $router->longitude) }}" step="0.0000001" min="-180" max="180">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Management VLAN</label>
                    <input type="number" name="management_vlan" class="form-control" value="{{ old('management_vlan', $router->management_vlan) }}" min="1" max="4094">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Management Interface</label>
                    <input type="text" name="management_interface" class="form-control" value="{{ old('management_interface', $router->management_interface) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- TAGS & NOTES --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header border-0" style="background:rgba(139,92,246,0.06);">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-tags me-2" style="color:#8b5cf6;"></i>Tags & Catatan</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tags <small class="text-muted">(Enter untuk menambah)</small></label>
                    <input type="text" name="tags_input" class="form-control" id="tagsInput" placeholder="core, router, site-a">
                    <input type="hidden" name="tags" id="tagsHidden" value="{{ old('tags') ? implode(',', old('tags', [])) : ($router->tags ? implode(',', $router->tags) : '') }}">
                    <div id="tagsContainer" class="mt-2 d-flex flex-wrap gap-1"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Catatan</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $router->notes) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-save me-1"></i>Simpan Perubahan
        </button>
        <a href="{{ route('noc.mikrotik-devices.show', $router) }}" class="btn btn-outline-secondary px-4 py-2">Batal</a>
    </div>
</form>

@push('scripts')
<script>
(function() {
    const input = document.getElementById('tagsInput');
    const hidden = document.getElementById('tagsHidden');
    const container = document.getElementById('tagsContainer');
    let tags = hidden.value ? hidden.value.split(',').filter(Boolean) : [];

    function render() {
        container.innerHTML = '';
        tags.forEach((tag, i) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary';
            badge.style.cssText = 'font-size:0.75rem;cursor:pointer;';
            badge.innerHTML = tag + ' <i class="fa-solid fa-xmark ms-1"></i>';
            badge.onclick = () => { tags.splice(i, 1); render(); };
            container.appendChild(badge);
        });
        hidden.value = tags.join(',');
    }

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = input.value.trim().replace(',', '');
            if (val && !tags.includes(val)) {
                tags.push(val);
                render();
            }
            input.value = '';
        }
    });

    render();
})();
</script>
@endpush
@endsection
