@extends('layouts.app')

@section('title', 'PPP Secrets')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>PPP Secrets</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola user PPPoE/PPTP/L2TP/OVPN di MikroTik</p>
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
                    <span>Tambah PPP Secret</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mikrotik.ppp.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="pppoe_user" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="text" name="password" class="form-control" placeholder="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Service</label>
                        <select name="service" class="form-select" required>
                            <option value="pppoe">PPPoE</option>
                            <option value="pptp">PPTP</option>
                            <option value="l2tp">L2TP</option>
                            <option value="ovpn">OVPN</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile</label>
                        <select name="profile" class="form-select">
                            <option value="">— Default —</option>
                            @foreach($profiles as $pr)
                                <option value="{{ $pr['name'] ?? '' }}">{{ $pr['name'] ?? '-' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah Secret
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
                    <span>Daftar PPP Secrets</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($secrets) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Service</th>
                            <th>Profile</th>
                            <th>Local Address</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($secrets as $s)
                            <tr>
                                <td class="fw-medium">{{ $s['name'] ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">
                                        {{ $s['service'] ?? 'pppoe' }}
                                    </span>
                                </td>
                                <td>{{ $s['profile'] ?? '-' }}</td>
                                <td><code style="font-size:0.75rem;">{{ $s['local-address'] ?? '-' }}</code></td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('mikrotik.ppp.destroy', $s['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Hapus secret {{ $s['name'] ?? '' }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada PPP secrets</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
