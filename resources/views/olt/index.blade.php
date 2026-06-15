@extends('layouts.app')

@section('title', 'OLT')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:var(--primary);"></i>OLT</h2>
        <p class="section-subtitle mb-0 mt-1">Manajemen perangkat OLT dan ONU</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('olt.export') }}" class="btn btn-outline-success px-3 py-2">
            <i class="fa-solid fa-download me-1"></i>Export OLT
        </a>
        <a href="{{ route('onu.export') }}" class="btn btn-outline-success px-3 py-2">
            <i class="fa-solid fa-download me-1"></i>Export ONU
        </a>
        <a href="{{ route('olt.create') }}" class="btn btn-primary px-3 py-2">
            <i class="fa-solid fa-plus me-1"></i>Tambah OLT
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- STATS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-tower-cell"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $olts->count() }}</div>
                <div class="stat-label">Total OLT</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-wifi"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $totalOnus }}</div>
                <div class="stat-label">Total ONU</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#22c55e,#16a34a);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $onlineOnus }}</div>
                <div class="stat-label">ONU Online</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.2s">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#ef4444,#dc2626);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $offlineOnus }}</div>
                <div class="stat-label">ONU Offline</div>
            </div>
        </div>
    </div>
</div>

{{-- OLT LIST --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Brand</th>
                        <th>IP Address</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Last Polled</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($olts as $olt)
                        <tr>
                            <td>
                                <a href="{{ route('olt.show', $olt) }}" class="fw-semibold text-decoration-none">
                                    {{ $olt->name }}
                                </a>
                            </td>
                            <td><span class="badge bg-secondary">{{ ucfirst($olt->brand) }}</span></td>
                            <td><code>{{ $olt->ip_address }}:{{ $olt->ssh_port }}</code></td>
                            <td>{{ $olt->location ?? '-' }}</td>
                            <td>
                                @if($olt->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($olt->status === 'maintenance')
                                    <span class="badge bg-warning">Maintenance</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($olt->last_polled_at)
                                    <span class="badge" style="background:{{ $olt->last_polled_at->diffInMinutes() < 30 ? '#f0fdf4' : '#fef3c7' }};color:{{ $olt->last_polled_at->diffInMinutes() < 30 ? '#059669' : '#d97706' }};">
                                        <i class="fa-solid fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>
                                        {{ $olt->last_polled_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">Belum pernah</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('olt.test', $olt) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Test Connection">
                                        <i class="fa-solid fa-plug"></i>
                                    </button>
                                </form>
                                <a href="{{ route('olt.show', $olt) }}" class="btn btn-sm btn-outline-primary px-2" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('olt.edit', $olt) }}" class="btn btn-sm btn-outline-secondary px-2" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form action="{{ route('olt.destroy', $olt) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus OLT ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger px-2" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada OLT. <a href="{{ route('olt.create') }}">Tambah sekarang</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
