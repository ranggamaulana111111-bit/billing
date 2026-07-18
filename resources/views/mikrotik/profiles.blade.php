@extends('layouts.app')
@section('title', 'Hotspot Profiles')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>Hotspot User Profiles</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola profile user hotspot MikroTik — rate limit, shared users</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <form method="POST" action="{{ route('mikrotik.profiles.sync') }}" class="d-inline">
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
                    <span>Tambah Profile</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mikrotik.profiles.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" class="form-control" placeholder="contoh: 10Mbps" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rate Limit</label>
                        <input type="text" name="rate_limit" class="form-control" placeholder="10M/10M">
                        <small class="text-muted">Format: tx-rate/rx-rate (contoh: 10M/10M)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="text" name="shared_users" class="form-control" placeholder="1">
                        <small class="text-muted">Jumlah user yang bisa login bersamaan</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- DAFTAR PROFILES --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Daftar Profiles</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($profiles) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                        <tr>
                            <th>Nama</th>
                            <th>Rate Limit</th>
                            <th>Shared Users</th>
                            <th class="text-center">Aksi</th>
                        </tr>

                    <tbody>
                        @forelse($profiles as $p)
                            <tr>
                                <td class="fw-medium">{{ $p['name'] ?? '-' }}</td>
                                <td><code>{{ $p['rate-limit'] ?? '-' }}</code></td>
                                <td>{{ $p['shared-users'] ?? '1' }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary px-2" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#editProfileModal"
                                            data-id="{{ $p['.id'] ?? '' }}"
                                            data-name="{{ $p['name'] ?? '' }}"
                                            data-rate-limit="{{ $p['rate-limit'] ?? '' }}"
                                            data-shared-users="{{ $p['shared-users'] ?? '1' }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProfileModal"
                                                    data-id="{{ $p['.id'] ?? '' }}"
                                                    data-name="{{ $p['name'] ?? '' }}"
                                                    data-rate-limit="{{ $p['rate-limit'] ?? '' }}"
                                                    data-shared-users="{{ $p['shared-users'] ?? '1' }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('mikrotik.profiles.destroy', $p['.id'] ?? '') }}" onsubmit="return confirm('Hapus profile {{ $p['name'] }}?')">
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
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada profile</td></tr>
                        @endforelse
                    </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>
    </div>
</div>
{{-- MODAL EDIT --}}
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="editProfileForm">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-pen me-2"></i>Edit Hotspot Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rate Limit</label>
                        <input type="text" name="rate_limit" id="edit_rate_limit" class="form-control" placeholder="10M/10M">
                        <small class="text-muted">Format: tx-rate/rx-rate (contoh: 10M/10M)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="text" name="shared_users" id="edit_shared_users" class="form-control" placeholder="1">
                        <small class="text-muted">Jumlah user yang bisa login bersamaan</small>
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
    const modal = document.getElementById('editProfileModal');
    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        const rateLimit = btn.getAttribute('data-rate-limit');
        const sharedUsers = btn.getAttribute('data-shared-users');
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_rate_limit').value = rateLimit;
        document.getElementById('edit_shared_users').value = sharedUsers;
        const action = '{{ route("mikrotik.profiles.update", "__ID__") }}'.replace('__ID__', id);
        document.getElementById('editProfileForm').action = action;
    });
});
</script>
@endpush
@endsection
