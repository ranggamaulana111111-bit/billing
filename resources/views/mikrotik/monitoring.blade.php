@extends('layouts.app')

@section('title', 'Monitoring Bandwidth')

@section('content')
<div class="page-header">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-line me-2" style="color:var(--primary);"></i>Monitoring Bandwidth</h2>
        <p class="section-subtitle mb-0 mt-1">Pemakaian bandwidth real-time dari MikroTik</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Total Download</small>
                <h4 class="fw-bold mb-0">{{ number_format($totalBandwidthRx / 1024 / 1024, 1) }} MB</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Total Upload</small>
                <h4 class="fw-bold mb-0">{{ number_format($totalBandwidthTx / 1024 / 1024, 1) }} MB</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Sesi Hotspot Aktif</small>
                <h4 class="fw-bold mb-0">{{ count($sessions) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">Sesi PPP Aktif</small>
                <h4 class="fw-bold mb-0">{{ count($pppActive) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- HOTSPOT SESSIONS --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                <span>Hotspot Aktif</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Uptime</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $s)
                                <tr>
                                    <td class="fw-medium">{{ $s['user'] ?? '-' }}</td>
                                    <td>{{ $s['address'] ?? '-' }}</td>
                                    <td>{{ number_format(($s['bytes-in'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                    <td>{{ number_format(($s['bytes-out'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                    <td>{{ $s['uptime'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- PPP ACTIVE --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;#8b5cf6;"></div>
                <span>PPPoE Aktif</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Uptime</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pppActive as $p)
                                <tr>
                                    <td class="fw-medium">{{ $p['name'] ?? '-' }}</td>
                                    <td>{{ $p['address'] ?? '-' }}</td>
                                    <td>{{ number_format(($p['bytes-in'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                    <td>{{ number_format(($p['bytes-out'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                    <td>{{ $p['uptime'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- SIMPLE QUEUES --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;#d97706;"></div>
                <span>Queue Simple</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Target</th>
                                <th>Max Limit</th>
                                <th>Bytes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($queues as $q)
                                <tr>
                                    <td class="fw-medium">{{ $q['name'] ?? '-' }}</td>
                                    <td>{{ $q['target'] ?? '-' }}</td>
                                    <td>{{ $q['max-limit'] ?? '-' }}</td>
                                    <td>{{ number_format(($q['bytes'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada queue</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- INTERFACES --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;#059669;"></div>
                <span>Interface</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Tx</th>
                                <th>Rx</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($interfaces as $iface)
                                <tr>
                                    <td class="fw-medium">{{ $iface['name'] ?? '-' }}</td>
                                    <td>{{ $iface['type'] ?? '-' }}</td>
                                    <td>
                                        @if(($iface['running'] ?? '') === 'true')
                                            <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">UP</span>
                                        @else
                                            <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">DOWN</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format(($iface['tx-byte'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                    <td>{{ number_format(($iface['rx-byte'] ?? 0) / 1024 / 1024, 1) }} MB</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada interface</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
