@extends('layouts.app')

@section('title', 'Hotspot Users')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-wifi me-2" style="color:var(--primary);"></i>Hotspot Users</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar user hotspot dari MikroTik — kelola, update, sync</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <form method="POST" action="{{ route('mikrotik.hotspot-users.sync') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary px-3 me-2" title="Sync dari MikroTik">
                <i class="fa-solid fa-rotate me-1"></i>Sync
            </button>
        </form>
        <a href="{{ route('mikrotik.dashboard', ['router' => request('router')]) }}" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Monitor
        </a>
    </div>
</div>

@include('mikrotik._router_switcher')

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4">
    {{-- FORM TAMBAH --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span>Tambah User</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mikrotik.hotspot-users.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="name" class="form-control" placeholder="voucher001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="text" name="password" class="form-control" placeholder="123456" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile</label>
                        <select name="profile" class="form-select">
                            <option value="">— Default —</option>
                            @foreach($profiles as $p)
                                <option value="{{ $p['name'] ?? '' }}">{{ $p['name'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Limit Uptime</label>
                        <input type="text" name="limit_uptime" class="form-control" placeholder="2h">
                        <small class="text-muted">Contoh: 2h, 24h, 7d</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah User
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- DAFTAR USERS --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Daftar Hotspot User</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($users) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Profile</th>
                            <th>Limit Uptime</th>
                            <th>Server</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            <tr>
                                <td class="fw-medium">{{ $u['name'] ?? '-' }}</td>
                                <td><code>{{ $u['profile'] ?? '-' }}</code></td>
                                <td>{{ $u['limit-uptime'] ?? '-' }}</td>
                                <td>{{ $u['server'] ?? '-' }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-warning px-2 me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="{{ $u['.id'] ?? '' }}"
                                        data-name="{{ $u['name'] ?? '' }}"
                                        data-password=""
                                        data-profile="{{ $u['profile'] ?? '' }}"
                                        data-limit-uptime="{{ $u['limit-uptime'] ?? '' }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('mikrotik.hotspot-users.destroy', $u['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Hapus user {{ $u['name'] }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada hotspot user</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL EDIT --}}
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="editUserForm">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen me-2"></i>Edit Hotspot User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password (kosongkan jika tidak diubah)</label>
                        <input type="text" name="password" id="edit_password" class="form-control" placeholder="Biarkan kosong">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile</label>
                        <select name="profile" id="edit_profile" class="form-select">
                            <option value="">— Default —</option>
                            @foreach($profiles as $p)
                                <option value="{{ $p['name'] ?? '' }}">{{ $p['name'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Limit Uptime</label>
                        <input type="text" name="limit_uptime" id="edit_limit_uptime" class="form-control" placeholder="2h">
                        <small class="text-muted">Contoh: 2h, 24h, 7d</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fa-solid fa-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editUserModal');
    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        const password = btn.getAttribute('data-password');
        const profile = btn.getAttribute('data-profile');
        const limitUptime = btn.getAttribute('data-limit-uptime');

        document.getElementById('edit_name').value = name;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_profile').value = profile;
        document.getElementById('edit_limit_uptime').value = limitUptime;

        const action = '{{ route("mikrotik.hotspot-users.update", "__ID__") }}'.replace('__ID__', id);
        document.getElementById('editUserForm').action = action;
    });
});
</script>
@endpush

@endsection
