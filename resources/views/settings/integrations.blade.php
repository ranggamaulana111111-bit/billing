@extends('layouts.app')
@section('title', 'Integrasi MikroTik & OLT')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plug-circle-plus me-2" style="color:var(--primary);"></i>Integrasi MikroTik & OLT</h2>
        <p class="text-muted mb-0 mt-1 small">Kelola koneksi perangkat MikroTik dan OLT untuk integrasi billing.</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <button type="button" class="btn btn-primary px-3 py-2 me-2" data-bs-toggle="modal" data-bs-target="#createMikrotikModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah MikroTik
        </button>
        <button type="button" class="btn btn-outline-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createOltModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah OLT
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2 fs-5"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i> {{ session('error') }}
    </div>
@endif

{{-- ══════════ MIKROTIK ══════════ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>MikroTik Router</h5>
        <span class="badge" style="background:#eff6ff;color:#2563eb;">{{ $routers->count() }} perangkat</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <tr>
                    <th>Nama</th>
                    <th>Host</th>
                    <th>Port</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Terakhir Koneksi</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tbody>
                    @forelse($routers as $router)
                        <tr>
                            <td class="fw-semibold">{{ $router->name }}</td>
                            <td><code>{{ $router->host }}</code></td>
                            <td>{{ $router->port }}</td>
                            <td>
                                <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                    {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $router->status_badge_color }}">{{ $router->status_label }}</span>
                                @if($router->is_active)
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Aktif</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                @if($router->last_connected)
                                    {{ $router->last_connected->diffForHumans() }}
                                @else
                                    <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.test', $router) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Koneksi / Test">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editMikrotikModal{{ $router->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.integrations.mikrotik.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada router MikroTik. Klik "Tambah MikroTik" untuk mengkoneksikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ OLT ══════════ --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:var(--primary);"></i>OLT</h5>
        <span class="badge" style="background:#eff6ff;color:#2563eb;">{{ $olts->count() }} perangkat</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <tr>
                    <th>Nama</th>
                    <th>Brand / Model</th>
                    <th>IP</th>
                    <th>SSH Port</th>
                    <th>Status</th>
                    <th>Terakhir Polling</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tbody>
                    @forelse($olts as $olt)
                        <tr>
                            <td class="fw-semibold">{{ $olt->name }}</td>
                            <td>
                                <span class="badge" style="background:#f1f5f9;color:#334155;text-transform:capitalize;">{{ $olt->brand }}</span>
                                @if($olt->model)<small class="text-muted">{{ $olt->model }}</small>@endif
                            </td>
                            <td><code>{{ $olt->ip_address }}</code></td>
                            <td>{{ $olt->ssh_port }}</td>
                            <td>
                                <span class="badge bg-{{ match($olt->status) { 'active' => 'success', 'maintenance' => 'warning', default => 'secondary' } }}">
                                    {{ match($olt->status) { 'active' => 'Active', 'maintenance' => 'Maintenance', default => 'Inactive' } }}
                                </span>
                            </td>
                            <td>
                                @if($olt->last_polled_at)
                                    {{ $olt->last_polled_at->diffForHumans() }}
                                @else
                                    <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('settings.integrations.olt.test', $olt) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Koneksi / Test">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editOltModal{{ $olt->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.integrations.olt.destroy', $olt) }}" class="d-inline" onsubmit="return confirm('Hapus OLT {{ $olt->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada OLT. Klik "Tambah OLT" untuk mengkoneksikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ CREATE MIKROTIK MODAL ══════════ --}}
<div class="modal fade" id="createMikrotikModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.mikrotik.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Router MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: RB-Main" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host/IP</label>
                            <input type="text" name="host" class="form-control" placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="8728" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Tipe Router</label>
                        <select name="type" class="form-select" required>
                            <option value="pppoe">PPPoE (Utama)</option>
                            <option value="bandwidth">Bandwidth (HTB)</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" placeholder="Kosongkan untuk default">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SSH Port <small class="text-muted">(isi jika RouterOS v6 / REST tidak support)</small></label>
                        <input type="number" name="ssh_port" class="form-control" placeholder="Kosongkan untuk REST API" min="1" max="65535">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createMikrotikIsActive" checked>
                        <label class="form-check-label" for="createMikrotikIsActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ CREATE OLT MODAL ══════════ --}}
<div class="modal fade" id="createOltModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.olt.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: OLT-1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select" required>
                                <option value="">Pilih</option>
                                <option value="huawei">Huawei</option>
                                <option value="zte">ZTE</option>
                                <option value="fiberhome">FiberHome</option>
                                <option value="cdata">C-Data</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Model</label>
                            <input type="text" name="model" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip_address" class="form-control" placeholder="10.10.10.1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SSH Port</label>
                            <input type="number" name="ssh_port" class="form-control" value="22" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username SSH</label>
                            <input type="text" name="username" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password SSH</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Tunnel via Jump Host <small class="text-muted fw-normal">(opsional)</small></h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jump Host IP</label>
                            <input type="text" name="jump_host" class="form-control" placeholder="IP perantara">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="jump_port" class="form-control" value="22" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="jump_username" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="jump_password" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Version</label>
                            <select name="snmp_version" class="form-select">
                                <option value="">Pilih</option>
                                <option value="v1">v1</option>
                                <option value="v2c">v2c</option>
                                <option value="v3">v3</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control" value="161" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Lokasi</label>
                            <input type="text" name="location" class="form-control" placeholder="Alamat / nama lokasi">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════ EDIT MIKROTIK MODALS ══════════ --}}
@foreach($routers as $router)
<div class="modal fade" id="editMikrotikModal{{ $router->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.mikrotik.update', $router) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Router MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" value="{{ $router->name }}" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host/IP</label>
                            <input type="text" name="host" class="form-control" value="{{ $router->host }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="{{ $router->port }}" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" value="{{ $router->username }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Tipe Router</label>
                        <select name="type" class="form-select" required>
                            <option value="pppoe" {{ $router->type === 'pppoe' ? 'selected' : '' }}>PPPoE (Utama)</option>
                            <option value="bandwidth" {{ $router->type === 'bandwidth' ? 'selected' : '' }}>Bandwidth (HTB)</option>
                            <option value="general" {{ $router->type === 'general' ? 'selected' : '' }}>General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" value="{{ $router->hotspot_server }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SSH Port <small class="text-muted">(isi jika RouterOS v6 / REST tidak support)</small></label>
                        <input type="number" name="ssh_port" class="form-control" value="{{ $router->ssh_port }}" placeholder="Kosongkan untuk REST API" min="1" max="65535">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editMikrotikIsActive{{ $router->id }}" {{ $router->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editMikrotikIsActive{{ $router->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- ══════════ EDIT OLT MODALS ══════════ --}}
@foreach($olts as $olt)
<div class="modal fade" id="editOltModal{{ $olt->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.integrations.olt.update', $olt) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $olt->name }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select" required>
                                <option value="huawei" {{ $olt->brand === 'huawei' ? 'selected' : '' }}>Huawei</option>
                                <option value="zte" {{ $olt->brand === 'zte' ? 'selected' : '' }}>ZTE</option>
                                <option value="fiberhome" {{ $olt->brand === 'fiberhome' ? 'selected' : '' }}>FiberHome</option>
                                <option value="cdata" {{ $olt->brand === 'cdata' ? 'selected' : '' }}>C-Data</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Model</label>
                            <input type="text" name="model" class="form-control" value="{{ $olt->model }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip_address" class="form-control" value="{{ $olt->ip_address }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SSH Port</label>
                            <input type="number" name="ssh_port" class="form-control" value="{{ $olt->ssh_port }}" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username SSH</label>
                            <input type="text" name="username" class="form-control" value="{{ $olt->username }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password SSH</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Tunnel via Jump Host <small class="text-muted fw-normal">(opsional)</small></h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jump Host IP</label>
                            <input type="text" name="jump_host" class="form-control" value="{{ $olt->jump_host }}" placeholder="IP perantara">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="jump_port" class="form-control" value="{{ $olt->jump_port ?? 22 }}" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="jump_username" class="form-control" value="{{ $olt->jump_username }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="jump_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control" value="{{ $olt->snmp_community }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Version</label>
                            <select name="snmp_version" class="form-select">
                                <option value="">Pilih</option>
                                <option value="v1" {{ $olt->snmp_version === 'v1' ? 'selected' : '' }}>v1</option>
                                <option value="v2c" {{ $olt->snmp_version === 'v2c' ? 'selected' : '' }}>v2c</option>
                                <option value="v3" {{ $olt->snmp_version === 'v3' ? 'selected' : '' }}>v3</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control" value="{{ $olt->snmp_port }}" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ $olt->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="maintenance" {{ $olt->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                <option value="inactive" {{ $olt->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Lokasi</label>
                            <input type="text" name="location" class="form-control" value="{{ $olt->location }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2">{{ $olt->notes }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
