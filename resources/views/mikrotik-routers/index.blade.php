@extends('layouts.app')
@section('title', 'Router MikroTik')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-server me-2" style="color:var(--primary);"></i>Router MikroTik</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah Router
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
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Nama</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Tipe</th>
                        <th>Hotspot Server</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($routers as $router)
                        <tr>
                            <td class="fw-semibold">{{ $router->name }}</td>
                            <td><code>{{ $router->host }}:{{ $router->port }}</code></td>
                            <td>{{ $router->port }}</td>
                            <td>
                                <span class="badge" style="background:{{ match($router->type) { 'pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', default => '#f8fafc' } }};color:{{ match($router->type) { 'pppoe' => '#2563eb', 'bandwidth' => '#dc2626', default => '#475569' } }};">
                                    {{ match($router->type) { 'pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', default => 'General' } }}
                                </span>
                            </td>
                            <td>{{ $router->hotspot_server ?: 'default' }}</td>
                            <td>
                                <span class="badge" style="background:{{ $router->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $router->is_active ? '#059669' : '#64748b' }};">
                                    {{ $router->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ $router->vouchers()->count() }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <form method="POST" action="{{ route('mikrotik-routers.test', $router) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Test Koneksi">
                                            <i class="fa-solid fa-plug"></i>
                                        </button>
                                    </form>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" title="Lainnya" style="font-size:0.7rem;">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal{{ $router->id }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                            <li>
                                                <form method="POST" action="{{ route('mikrotik-routers.test', $router) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="fa-solid fa-plug me-2 text-success"></i>Test Koneksi</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('mikrotik-routers.destroy', $router) }}" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada router</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{-- CREATE MODAL --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mikrotik-routers.store') }}">
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
                        <input type="checkbox" name="is_active" class="form-check-input" id="createIsActive" checked>
                        <label class="form-check-label" for="createIsActive">Aktif</label>
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
{{-- EDIT MODALS --}}
@foreach($routers as $router)
<div class="modal fade" id="editModal{{ $router->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mikrotik-routers.update', $router) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Router</h5>
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
                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive{{ $router->id }}" {{ $router->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editIsActive{{ $router->id }}">Aktif</label>
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
