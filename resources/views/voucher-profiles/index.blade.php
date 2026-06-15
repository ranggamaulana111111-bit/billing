@extends('layouts.app')

@section('title', 'Profile Voucher')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tags me-2" style="color:var(--primary);"></i>Profile Voucher</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah Profile
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
                        <th>Speed</th>
                        <th>Harga</th>
                        <th>Time Limit</th>
                        <th>Kuota</th>
                        <th>Masa Berlaku</th>
                        <th>Shared</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td class="fw-semibold">{{ $profile->name }}</td>
                            <td>{{ $profile->speed ?? '-' }}</td>
                            <td>Rp {{ number_format($profile->price, 0, ',', '.') }}</td>
                            <td>{{ $profile->time_limit ? $profile->time_limit.' Jam' : 'Unlimited' }}</td>
                            <td>{{ $profile->quota_limit ? number_format($profile->quota_limit).' MB' : 'Unlimited' }}</td>
                            <td>{{ $profile->validity_days ? $profile->validity_days.' Hari' : '-' }}</td>
                            <td class="text-center">{{ $profile->shared_users }}</td>
                            <td>
                                <span class="badge" style="background:{{ $profile->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $profile->is_active ? '#059669' : '#64748b' }};">
                                    {{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editModal{{ $profile->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('voucher-profiles.destroy', $profile) }}" class="d-inline" onsubmit="return confirm('Hapus profile {{ $profile->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada profile voucher</td></tr>
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
            <form method="POST" action="{{ route('voucher-profiles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Profile Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Paket 10GB" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Speed</label>
                            <input type="text" name="speed" class="form-control" placeholder="10Mbps">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Harga (Rp)</label>
                            <input type="number" name="price" class="form-control" value="0" min="0" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Limit (Jam)</label>
                            <input type="number" name="time_limit" class="form-control" placeholder="Kosongkan jika unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kuota (MB)</label>
                            <input type="number" name="quota_limit" class="form-control" placeholder="Kosongkan jika unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Masa Berlaku (Hari)</label>
                            <input type="number" name="validity_days" class="form-control" placeholder="Kosongkan jika 1 hari">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="number" name="shared_users" class="form-control" value="1" min="1" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsional"></textarea>
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
@foreach($profiles as $profile)
<div class="modal fade" id="editModal{{ $profile->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-profiles.update', $profile) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" class="form-control" value="{{ $profile->name }}" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Speed</label>
                            <input type="text" name="speed" class="form-control" value="{{ $profile->speed }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Harga (Rp)</label>
                            <input type="number" name="price" class="form-control" value="{{ $profile->price }}" min="0" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Limit (Jam)</label>
                            <input type="number" name="time_limit" class="form-control" value="{{ $profile->time_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kuota (MB)</label>
                            <input type="number" name="quota_limit" class="form-control" value="{{ $profile->quota_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Masa Berlaku (Hari)</label>
                            <input type="number" name="validity_days" class="form-control" value="{{ $profile->validity_days }}">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="number" name="shared_users" class="form-control" value="{{ $profile->shared_users }}" min="1" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2">{{ $profile->description }}</textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive{{ $profile->id }}" {{ $profile->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editIsActive{{ $profile->id }}">Aktif</label>
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
