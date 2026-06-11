@extends('layouts.app')

@section('title', 'Queue Bandwidth')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gauge-high me-2" style="color:var(--primary);"></i>Queue Bandwidth</h2>
        <p class="section-subtitle mb-0 mt-1">Manajemen bandwidth — Simple Queue MikroTik</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('mikrotik.dashboard') }}" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Monitor
        </a>
    </div>
</div>

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
                    <span>Tambah Queue</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mikrotik.queues.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Queue</label>
                        <input type="text" name="name" class="form-control" placeholder="Pelanggan1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target IP</label>
                        <input type="text" name="target" class="form-control" placeholder="192.168.1.100/32" required>
                        <small class="text-muted">IP address target (bisa pakai /32)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Max Limit (tx/rx)</label>
                        <input type="text" name="max_limit" class="form-control" placeholder="10M/10M" required>
                        <small class="text-muted">Format: upload/download (contoh: 10M/10M)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah Queue
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
                    <span>Daftar Simple Queue</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($queues) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Target</th>
                            <th>Max Limit</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queues as $q)
                            <tr>
                                <td class="fw-medium">{{ $q['name'] ?? '-' }}</td>
                                <td><code style="font-size:0.75rem;">{{ $q['target'] ?? '-' }}</code></td>
                                <td><code>{{ $q['max-limit'] ?? '-' }}</code></td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('mikrotik.queues.destroy', $q['.id'] ?? '') }}" class="d-inline" onsubmit="return confirm('Hapus queue {{ $q['name'] ?? '' }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada queue</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
