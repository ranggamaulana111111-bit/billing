@extends('layouts.app')

@section('title', 'Profile MikroTik')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tags me-2" style="color:var(--primary);"></i>Profile MikroTik</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if($routers->isNotEmpty())
        <form method="POST" action="{{ route('voucher-profiles.sync-mikrotik') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-info px-3 py-2">
                <i class="fa-solid fa-rotate me-1"></i>Sync
            </button>
        </form>
        @endif
        <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fa-solid fa-plus me-1"></i>Buat Profile
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($error)
    <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $error }}</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address Pool</th>
                        <th>Shared Users</th>
                        <th>Rate Limit</th>
                        <th>Price</th>
                        <th>Selling Price</th>
                        <th>Lock User</th>
                        <th>Parent Queue</th>
                        <th>Router</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mikrotikProfiles as $profile)
                        <tr>
                            <td class="fw-semibold">{{ $profile['name'] }}</td>
                            <td>{{ $profile['address_pool'] ?? '-' }}</td>
                            <td class="text-center">{{ $profile['shared_users'] }}</td>
                            <td><code>{{ $profile['speed'] ?? '-' }}</code></td>
                            <td>Rp {{ number_format($profile['price'] ?? 0, 0, ',', '.') }}</td>
                            <td>{{ $profile['selling_price'] ? 'Rp '.number_format($profile['selling_price'], 0, ',', '.') : '-' }}</td>
                            <td class="text-center">
                                @if($profile['lock_user'])
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Enable</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Disable</span>
                                @endif
                            </td>
                            <td>{{ $profile['parent_queue'] ?? '-' }}</td>
                            <td><span class="badge" style="background:#eef2ff;color:#4f46e5;">{{ $profile['router'] }}</span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2 edit-profile-btn"
                                    data-id="{{ $profile['id'] }}"
                                    data-name="{{ $profile['name'] }}"
                                    data-speed="{{ $profile['speed'] ?? '' }}"
                                    data-shared="{{ $profile['shared_users'] }}"
                                    data-address-pool="{{ $profile['address_pool'] ?? '' }}"
                                    data-lock-user="{{ $profile['lock_user'] ? '1' : '0' }}"
                                    data-price="{{ $profile['price'] ?? 0 }}"
                                    data-selling-price="{{ $profile['selling_price'] ?? '' }}"
                                    data-parent-queue="{{ $profile['parent_queue'] ?? '' }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('voucher-profiles.destroy-mikrotik', $profile['id']) }}" class="d-inline" onsubmit="return confirm('Hapus profile &quot;{{ $profile['name'] }}&quot; dari MikroTik?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        @if(!$error)
                        <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada profile MikroTik</td></tr>
                        @endif
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- CREATE MODAL --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-profiles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Buat Profile di MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Profile name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address Pool</label>
                            <input type="text" name="address_pool" class="form-control" placeholder="none">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shared Users</label>
                            <input type="number" name="shared_users" class="form-control" value="1" min="1" max="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rate limit [up/down]</label>
                            <input type="text" name="speed" class="form-control" placeholder="Example : 512k/1M">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price Rp</label>
                            <input type="number" name="price" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Selling Price Rp</label>
                            <input type="number" name="selling_price" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                Lock User
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" name="lock_user" class="form-check-input" id="createLockUser" value="1">
                                    <label class="form-check-label" for="createLockUser">Disable</label>
                                </div>
                            </label>
                            <small class="text-muted d-block">Username can only be used on 1 device only.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Queue</label>
                            <input type="text" name="parent_queue" class="form-control" placeholder="none">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Buat di MikroTik</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile di MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address Pool</label>
                            <input type="text" name="address_pool" class="form-control" placeholder="none">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shared Users</label>
                            <input type="number" name="shared_users" class="form-control" min="1" max="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rate limit [up/down]</label>
                            <input type="text" name="speed" class="form-control" placeholder="Example : 512k/1M">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price Rp</label>
                            <input type="number" name="price" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Selling Price Rp</label>
                            <input type="number" name="selling_price" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                Lock User
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" name="lock_user" class="form-check-input" id="editLockUser" value="1">
                                    <label class="form-check-label" for="editLockUser">Disable</label>
                                </div>
                            </label>
                            <small class="text-muted d-block">Username can only be used on 1 device only.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Queue</label>
                            <input type="text" name="parent_queue" class="form-control" placeholder="none">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-profile-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const speed = this.dataset.speed;
            const shared = this.dataset.shared;
            const addressPool = this.dataset.addressPool;
            const lockUser = this.dataset.lockUser;
            const price = this.dataset.price;
            const sellingPrice = this.dataset.sellingPrice;
            const parentQueue = this.dataset.parentQueue;

            const modal = document.getElementById('editModal');
            const form = modal.querySelector('form');
            form.action = '{{ route("voucher-profiles.update-mikrotik", "_id_") }}'.replace('_id_', id);
            form.querySelector('[name="name"]').value = name;
            form.querySelector('[name="speed"]').value = speed;
            form.querySelector('[name="shared_users"]').value = shared;
            form.querySelector('[name="address_pool"]').value = addressPool;
            form.querySelector('[name="price"]').value = price;
            form.querySelector('[name="selling_price"]').value = sellingPrice;
            form.querySelector('[name="parent_queue"]').value = parentQueue;
            form.querySelector('[name="lock_user"]').checked = lockUser === '1';

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });
});
</script>
@endpush

@endsection
