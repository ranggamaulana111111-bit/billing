@extends('layouts.app')

@section('title', 'Hotspot Profiles')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>Hotspot User Profiles</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola profile user hotspot MikroTik — rate limit, shared users</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
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
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Rate Limit</th>
                            <th>Shared Users</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profiles as $p)
                            <tr>
                                <td class="fw-medium">{{ $p['name'] ?? '-' }}</td>
                                <td><code>{{ $p['rate-limit'] ?? '-' }}</code></td>
                                <td>{{ $p['shared-users'] ?? '1' }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('mikrotik.profiles.destroy', $p['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Hapus profile {{ $p['name'] }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada profile</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
