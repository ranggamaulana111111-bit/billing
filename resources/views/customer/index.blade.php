@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-users me-2" style="color:var(--primary);"></i>Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar semua pelanggan — kelola data, status, dan tagihan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('customer.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-user-plus me-2"></i>Pasang Baru
        </a>
        <form action="{{ route('customers.sync-pppoe') }}" method="POST" class="d-inline" onsubmit="return confirm('Sync PPPoE semua pelanggan aktif ke MikroTik?')">
            @csrf
            <button type="submit" class="btn btn-outline-info px-4 py-2">
                <i class="fa-solid fa-network-wired me-2"></i>Sync PPPoE
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

{{-- STATS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:var(--primary);">{{ $stats['total'] }}</div>
            <small class="text-muted">Total Pelanggan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#059669;">{{ $stats['active'] }}</div>
            <small class="text-muted">Aktif</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#d97706;">{{ $stats['suspended'] }}</div>
            <small class="text-muted">Ditangguhkan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#dc2626;">{{ $stats['inactive'] }}</div>
            <small class="text-muted">Nonaktif</small>
        </div>
    </div>
</div>

{{-- TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Daftar Pelanggan</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $customers->total() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Telepon</th>
                        <th>Email</th>
                        <th>Paket</th>
                        <th>ODP</th>
                        <th>PPPoE</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--accent));color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;">
                                        {{ strtoupper(substr($c->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $c->name }}</div>
                                        <small class="text-muted">{{ $c->location ?? '-' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $c->phone ?? '-' }}</td>
                            <td>{{ $c->email ?? '-' }}</td>
                            <td><span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">{{ $c->odp->name ?? '-' }}</span></td>
                            <td><code style="font-size:0.75rem;">{{ $c->pppoe_username ?? '-' }}</code></td>
                            <td class="text-center">
                                @if($c->status === 'active')
                                    <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">
                                        <i class="fa-regular fa-circle-check me-1"></i>Aktif
                                    </span>
                                @elseif($c->status === 'suspended')
                                    <span class="badge badge-premium" style="background:#fef3c7;color:#d97706;">
                                        <i class="fa-solid fa-pause me-1"></i>Ditangguhkan
                                    </span>
                                @else
                                    <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">
                                        <i class="fa-solid fa-ban me-1"></i>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('customer.edit', $c->id) }}" class="btn btn-sm btn-outline-primary px-2" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    @if($c->status === 'active')
                                        <form method="POST" action="{{ route('customer.suspend', $c->id) }}" class="d-inline" onsubmit="return confirm('Tangguhkan {{ $c->name }}?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning px-2" title="Tangguhkan">
                                                <i class="fa-solid fa-pause"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('customer.activate', $c->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Aktifkan">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('customer.destroy', $c->id) }}" class="d-inline" onsubmit="return confirm('Hapus {{ $c->name }}? Semua data tagihan ikut terhapus!')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-users" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada pelanggan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($customers->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $customers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
