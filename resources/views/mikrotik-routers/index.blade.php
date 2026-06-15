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
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Hotspot Server</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routers as $router)
                        <tr>
                            <td class="fw-semibold">{{ $router->name }}</td>
                            <td><code>{{ $router->host }}:{{ $router->port }}</code></td>
                            <td>{{ $router->port }}</td>
                            <td>{{ $router->hotspot_server ?: 'default' }}</td>
                            <td>
                                <span class="badge" style="background:{{ $router->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $router->is_active ? '#059669' : '#64748b' }};">
                                    {{ $router->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ $router->vouchers()->count() }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('mikrotik-routers.test', $router) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Test Koneksi">
                                        <i class="fa-solid fa-plug"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editModal{{ $router->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('mikrotik-routers.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada router</td></tr>
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
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" placeholder="Kosongkan untuk default">
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
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" value="{{ $router->hotspot_server }}">
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
