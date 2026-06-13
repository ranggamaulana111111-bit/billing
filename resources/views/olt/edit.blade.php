@extends('layouts.app')

@section('title', 'Edit OLT')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit OLT</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('olt.show', $olt) }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-eye me-1"></i>Detail
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
        <form action="{{ route('olt.update', $olt) }}" method="POST">
            @csrf @method('PUT')

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Nama OLT <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $olt->name) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                    <select name="brand" class="form-select" required>
                        <option value="huawei" {{ old('brand', $olt->brand) === 'huawei' ? 'selected' : '' }}>Huawei</option>
                        <option value="zte" {{ old('brand', $olt->brand) === 'zte' ? 'selected' : '' }}>ZTE</option>
                        <option value="fiberhome" {{ old('brand', $olt->brand) === 'fiberhome' ? 'selected' : '' }}>FiberHome</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Model</label>
                    <input type="text" name="model" class="form-control" value="{{ old('model', $olt->model) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">IP Address <span class="text-danger">*</span></label>
                    <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address', $olt->ip_address) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">SSH Port</label>
                    <input type="number" name="ssh_port" class="form-control" value="{{ old('ssh_port', $olt->ssh_port) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username SSH</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $olt->username) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password SSH (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">SNMP Community</label>
                    <input type="text" name="snmp_community" class="form-control" value="{{ old('snmp_community', $olt->snmp_community) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">SNMP Version</label>
                    <select name="snmp_version" class="form-select">
                        <option value="">Pilih</option>
                        <option value="v1" {{ old('snmp_version', $olt->snmp_version) === 'v1' ? 'selected' : '' }}>v1</option>
                        <option value="v2c" {{ old('snmp_version', $olt->snmp_version) === 'v2c' ? 'selected' : '' }}>v2c</option>
                        <option value="v3" {{ old('snmp_version', $olt->snmp_version) === 'v3' ? 'selected' : '' }}>v3</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">SNMP Port</label>
                    <input type="number" name="snmp_port" class="form-control" value="{{ old('snmp_port', $olt->snmp_port) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status', $olt->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="maintenance" {{ old('status', $olt->status) === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        <option value="inactive" {{ old('status', $olt->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $olt->latitude) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $olt->longitude) }}">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location', $olt->location) }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $olt->notes) }}</textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="fa-solid fa-save me-1"></i>Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
