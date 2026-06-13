@extends('layouts.app')

@section('title', $olt->name)

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:var(--primary);"></i>{{ $olt->name }}</h2>
        <p class="section-subtitle mb-0 mt-1">
            {{ ucfirst($olt->brand) }} {{ $olt->model }} &mdash; <code>{{ $olt->ip_address }}:{{ $olt->ssh_port }}</code>
            @if($olt->location) &mdash; {{ $olt->location }} @endif
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form action="{{ route('olt.test', $olt) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-success px-3 py-2" title="Test Koneksi">
                <i class="fa-solid fa-plug me-1"></i>Test Koneksi
            </button>
        </form>
        <form action="{{ route('olt.scan', $olt) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary px-3 py-2" title="Scan ONU">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Scan ONU
            </button>
        </form>
        <a href="{{ route('olt.edit', $olt) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-pen me-1"></i>Edit
        </a>
        <a href="{{ route('olt.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-network-wired"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $totalPorts }}</div>
                <div class="stat-label">Total Port</div>
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
            <div class="stat-bg"><i class="fa-solid fa-clock"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $olt->last_polled_at ? $olt->last_polled_at->diffForHumans() : 'Tidak pernah' }}</div>
                <div class="stat-label">Last Polled</div>
            </div>
        </div>
    </div>
</div>

{{-- PORTS --}}
@forelse($olt->ports as $port)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="fa-solid fa-plug me-1"></i>Slot {{ $port->slot_number }} / Port {{ $port->port_number }}
                <span class="badge bg-info ms-2">{{ strtoupper($port->port_type) }}</span>
                @if($port->status === 'active')
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-danger">Inactive</span>
                @endif
            </span>
            <span class="text-muted small">{{ $port->onus->count() }} ONU</span>
        </div>
        @if($port->onus->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ONU ID</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Rx Power</th>
                            <th>Tx Power</th>
                            <th>Pelanggan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($port->onus as $onu)
                            <tr>
                                <td><code>{{ $onu->onu_id }}</code></td>
                                <td><code>{{ $onu->serial_number ?? '-' }}</code></td>
                                <td>
                                    @if($onu->status === 'online')
                                        <span class="badge bg-success">Online</span>
                                    @else
                                        <span class="badge bg-secondary">Offline</span>
                                    @endif
                                </td>
                                <td class="{{ $onu->rx_power !== null && $onu->rx_power < -27 ? 'text-danger' : '' }}">
                                    {{ $onu->rx_power !== null ? $onu->rx_power.' dBm' : '-' }}
                                </td>
                                <td>{{ $onu->tx_power !== null ? $onu->tx_power.' dBm' : '-' }}</td>
                                <td>
                                    @if($onu->customer)
                                        <a href="{{ route('customers.index') }}?search={{ $onu->customer->name }}" class="text-decoration-none">
                                            {{ $onu->customer->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">Belum ditautkan</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('olt.onu.reboot', [$olt, $onu]) }}" method="POST" class="d-inline" onsubmit="return confirm('Reboot ONU {{ $onu->onu_id }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" title="Reboot"><i class="fa-solid fa-rotate"></i></button>
                                    </form>
                                    <form action="{{ route('olt.onu.remove', [$olt, $onu]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus ONU {{ $onu->onu_id }} dari OLT?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body text-muted text-center py-3">Tidak ada ONU di port ini.</div>
        @endif
    </div>
@empty
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa-solid fa-plug fa-3x mb-3" style="opacity:0.3;"></i>
            <p>Belum ada port. Tambah port melalui edit atau scan ONU.</p>
        </div>
    </div>
@endforelse
@endsection
