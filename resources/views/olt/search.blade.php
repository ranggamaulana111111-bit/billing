@extends('layouts.app')

@section('title', 'Cari ONU')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-search me-2" style="color:var(--primary);"></i>Cari ONU</h2>
        <p class="section-subtitle mb-0 mt-1">Cari perangkat ONU berdasarkan nomor ID, serial, atau pelanggan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('onu.export', request()->only(['olt_id', 'status', 'q'])) }}" class="btn btn-outline-success px-3 py-2">
            <i class="fa-solid fa-download me-1"></i>Export CSV
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- FILTER --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('onu.search') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold text-muted">Kata Kunci</label>
                    <input type="text" name="q" class="form-control" placeholder="ONU ID, Serial, Nama Pelanggan..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                        <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">OLT</label>
                    <select name="olt_id" class="form-select">
                        <option value="">Semua OLT</option>
                        @foreach($olts as $id => $name)
                            <option value="{{ $id }}" {{ request('olt_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search me-1"></i>Cari</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- RESULTS --}}
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-1"></i>Hasil Pencarian</span>
        <span class="badge bg-secondary">{{ $onus->total() }} ONU</span>
    </div>
    <div class="card-body p-0">
        @if($onus->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>OLT</th>
                        <th>Port</th>
                        <th>ONU ID</th>
                        <th>Serial</th>
                        <th>Caller ID</th>
                        <th>Status</th>
                        <th>Rx Power</th>
                        <th>Pelanggan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($onus as $onu)
                    <tr>
                        <td>{{ $onu->oltPort?->olt?->name ?? '-' }}</td>
                        <td>{{ $onu->oltPort?->port_name ?? '-' }}</td>
                        <td><code>{{ $onu->onu_id }}</code></td>
                        <td><code class="small">{{ $onu->serial_number ?? '-' }}</code></td>
                        <td><code class="small">{{ $onu->caller_id ?? '-' }}</code></td>
                        <td>
                            <span class="badge bg-{{ $onu->status === 'online' ? 'success' : 'danger' }}">
                                {{ ucfirst($onu->status) }}
                            </span>
                        </td>
                        <td>
                            @if($onu->rx_power !== null)
                                <span class="badge bg-{{ $onu->rx_power < -27 ? 'danger' : 'success' }}">
                                    {{ $onu->rx_power }} dBm
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($onu->customer)
                                {{ $onu->customer->name }}
                            @else
                                <span class="text-muted">Belum ditautkan</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($onu->oltPort?->olt)
                                <form action="{{ route('olt.onu.reboot', [$onu->oltPort->olt, $onu]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-warning" title="Reboot" onclick="return confirm('Reboot ONU {{ $onu->onu_id }}?')">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </form>
                                <form action="{{ route('olt.onu.remove', [$onu->oltPort->olt, $onu]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus ONU {{ $onu->onu_id }} dari OLT?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2">
            {{ $onus->links() }}
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="fa-solid fa-wifi" style="font-size:2rem;opacity:0.3;margin-bottom:8px;display:block;"></i>
            @if(request()->anyFilled(['q', 'status', 'olt_id']))
                Tidak ada ONU yang cocok dengan pencarian.
            @else
                Gunakan filter di atas untuk mencari ONU.
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
