@extends('layouts.app')

@section('title', 'Paket Internet')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-wifi me-2" style="color:var(--primary);"></i>Paket Internet</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola paket internet — tambah, edit, hapus</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('customers.sync-pppoe') }}" class="d-inline" onsubmit="return confirm('Sync PPPoE semua pelanggan ke MikroTik?')">
            @csrf
            <button type="submit" class="btn btn-outline-info px-3 py-2">
                <i class="fa-solid fa-network-wired me-1"></i>Sync PPPoE
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari Paket</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama, speed, profil MikroTik..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('packages.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-rotate me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    {{-- FORM TAMBAH --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span>Tambah Paket</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('packages.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Paket</label>
                        <input type="text" name="name" class="form-control" placeholder="contoh: 10 Mbps" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kecepatan (Mbps)</label>
                        <input type="number" name="speed" class="form-control" placeholder="10" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="contoh: Unlimited FUP wajar"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" placeholder="150000" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Siklus Billing</label>
                        <select name="billing_cycle" class="form-select" required>
                            <option value="monthly">Bulanan</option>
                            <option value="weekly">Mingguan</option>
                            <option value="daily">Harian</option>
                            <option value="yearly">Tahunan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profil MikroTik</label>
                        <input type="text" name="mikrotik_profile" class="form-control" placeholder="contoh: 10M">
                    </div>
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="packageActive" checked>
                        <label class="form-check-label fw-semibold" for="packageActive">Paket aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah Paket
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- DAFTAR --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Daftar Paket</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $packages->total() }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th>Paket</th><th>Speed</th><th>Billing</th><th class="text-center">Pelanggan</th><th class="text-end">Harga</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $p)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $p->name }}</div>
                                    @if($p->description)
                                        <div class="text-muted" style="font-size:0.78rem;">{{ $p->description }}</div>
                                    @endif
                                    @if($p->mikrotik_profile)
                                        <div class="text-muted" style="font-size:0.78rem;">Profil MikroTik: {{ $p->mikrotik_profile }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-premium" style="background:#eef2ff;color:var(--primary);"><i class="fa-solid fa-wifi me-1"></i>{{ $p->speed }} Mbps</span></td>
                                <td>{{ ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'][$p->billing_cycle] ?? $p->billing_cycle }}</td>
                                <td class="text-center">{{ $p->customers_count }}</td>
                                <td class="fw-bold text-end">Rp{{ number_format($p->price, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    @if($p->is_active)
                                        <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">Aktif</span>
                                    @else
                                        <span class="badge badge-premium" style="background:#f3f4f6;color:#6b7280;">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal{{ $p->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('packages.destroy', $p->id) }}" class="d-inline" onsubmit="return confirm('Hapus paket {{ $p->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada paket</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @if($packages->hasPages())
                <div class="card-footer bg-white d-flex justify-content-center">
                    {{ $packages->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- EDIT MODALS --}}
@foreach($packages as $p)
<div class="modal fade" id="editModal{{ $p->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('packages.update', $p->id) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Paket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Paket</label>
                        <input type="text" name="name" class="form-control" value="{{ $p->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kecepatan (Mbps)</label>
                        <input type="number" name="speed" class="form-control" value="{{ $p->speed }}" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2">{{ $p->description }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" value="{{ $p->price }}" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Siklus Billing</label>
                        <select name="billing_cycle" class="form-select" required>
                            <option value="monthly" {{ $p->billing_cycle === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                            <option value="weekly" {{ $p->billing_cycle === 'weekly' ? 'selected' : '' }}>Mingguan</option>
                            <option value="daily" {{ $p->billing_cycle === 'daily' ? 'selected' : '' }}>Harian</option>
                            <option value="yearly" {{ $p->billing_cycle === 'yearly' ? 'selected' : '' }}>Tahunan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profil MikroTik</label>
                        <input type="text" name="mikrotik_profile" class="form-control" value="{{ $p->mikrotik_profile }}">
                    </div>
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="packageActive{{ $p->id }}" {{ $p->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="packageActive{{ $p->id }}">Paket aktif</label>
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
