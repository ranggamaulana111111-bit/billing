@extends('layouts.app')

@php
$isAdmin = auth()->user()->role === 'admin';
@endphp

@section('title', 'Monitor Gangguan OLT')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-broadcast me-2" style="color:var(--primary);"></i>Monitor Gangguan</h2>
        <p class="section-subtitle mb-0 mt-1">Pemantauan ONU offline & sinyal lemah</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="{{ route('onu.export', ['status' => 'offline']) }}" class="btn btn-outline-danger px-3 py-2">
            <i class="fa-solid fa-download me-1"></i>Export ONU Offline
        </a>
        <a href="{{ route('onu.search') }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-search me-1"></i>Cari ONU
        </a>
    </div>
</div>

{{-- STATS CARDS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                <i class="fa-solid fa-wifi"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalOnline }}</div>
                <div class="stat-label">ONU Online</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">
                <i class="fa-solid fa-wifi"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalOffline }}</div>
                <div class="stat-label">ONU Offline</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(250,204,21,0.15);color:#eab308;">
                <i class="fa-solid fa-signal"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalWeak }}</div>
                <div class="stat-label">Sinyal Lemah</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(96,165,250,0.15);color:#60a5fa;">
                <i class="fa-solid fa-tower-cell"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $olts->count() }}</div>
                <div class="stat-label">Total OLT</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- OFFLINE ONU --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-exclamation me-1 text-danger"></i> ONU Offline</span>
                <span class="badge bg-danger">{{ $offlineOnus->count() }}</span>
            </div>
            <div class="card-body p-0">
                @if($offlineOnus->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>OLT</th>
                                <th>Port</th>
                                <th>ONU ID</th>
                                <th>Pelanggan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($offlineOnus as $onu)
                            <tr>
                                <td>{{ $onu->oltPort->olt->name ?? '-' }}</td>
                                <td>{{ $onu->oltPort->port_name ?? '-' }}</td>
                                <td><code>{{ $onu->onu_id }}</code></td>
                                <td>{{ $onu->customer->name ?? '-' }}</td>
                                <td><span class="badge bg-danger">{{ $onu->status }}</span></td>
                                <td>
                                    @if($onu->oltPort?->olt)
                                    <form action="{{ route('olt.onu.reboot', [$onu->oltPort->olt, $onu]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" title="Reboot" onclick="return confirm('Reboot ONU {{ $onu->onu_id }}?')">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">Semua ONU dalam keadaan online.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- SINYAL LEMAH --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-signal me-1 text-warning"></i> Sinyal Lemah (Rx < -27 dBm)</span>
                <span class="badge bg-warning text-dark">{{ $weakSignalOnus->count() }}</span>
            </div>
            <div class="card-body p-0">
                @if($weakSignalOnus->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>OLT</th>
                                <th>Port</th>
                                <th>ONU ID</th>
                                <th>Pelanggan</th>
                                <th>Rx Power</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weakSignalOnus as $onu)
                            <tr>
                                <td>{{ $onu->oltPort->olt->name ?? '-' }}</td>
                                <td>{{ $onu->oltPort->port_name ?? '-' }}</td>
                                <td><code>{{ $onu->onu_id }}</code></td>
                                <td>{{ $onu->customer->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $onu->rx_power < -27 ? 'danger' : 'success' }}">
                                        {{ $onu->rx_power }} dBm
                                    </span>
                                </td>
                                <td><span class="badge bg-{{ $onu->status === 'online' ? 'success' : 'danger' }}">{{ $onu->status }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">Tidak ada ONU dengan sinyal lemah.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
