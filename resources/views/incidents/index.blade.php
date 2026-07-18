@extends('layouts.app')
@section('title', 'Incident / Gangguan')
@section('content')
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Incident / Gangguan</h4>
            <small class="text-muted">Kelola dan pantau semua gangguan jaringan</small>
        </div>
        <a href="{{ route('incidents.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Buat Incident
        </a>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card alarm-stat alarm-stat--total">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
                    <small>Total</small>
                </div>
                <i class="fa-solid fa-layer-group alarm-stat__icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card alarm-stat alarm-stat--open">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ $stats['open'] }}</div>
                    <small>Open</small>
                </div>
                <i class="fa-solid fa-folder-open alarm-stat__icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card alarm-stat alarm-stat--invest">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ $stats['investigating'] }}</div>
                    <small>Investigating</small>
                </div>
                <i class="fa-solid fa-magnifying-glass alarm-stat__icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card alarm-stat alarm-stat--breach">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ $stats['breached'] }}</div>
                    <small>SLA Breached</small>
                </div>
                <i class="fa-solid fa-triangle-exclamation alarm-stat__icon"></i>
            </div>
        </div>
    </div>
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <div class="card-header alarm-head border-0 d-flex justify-content-between align-items-center py-3" style="border-radius:12px 12px 0 0;">
            <span class="fw-semibold"><i class="fa-solid fa-bell me-2"></i>Daftar Gangguan</span>
            <form class="d-flex gap-2" method="GET">
                <select name="status" class="form-select form-select-sm" style="width:150px;border-radius:8px;" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="investigating" {{ request('status') === 'investigating' ? 'selected' : '' }}>Investigating</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
                <select name="severity" class="form-select form-select-sm" style="width:150px;border-radius:8px;" onchange="this.form.submit()">
                    <option value="">Semua Severity</option>
                    <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="high" {{ request('severity') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ request('severity') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ request('severity') === 'low' ? 'selected' : '' }}>Low</option>
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th class="border-0 px-3" style="font-size:0.8rem;">ID</th>
                            <th class="border-0" style="font-size:0.8rem;">Judul</th>
                            <th class="border-0" style="font-size:0.8rem;">Severity</th>
                            <th class="border-0" style="font-size:0.8rem;">Status</th>
                            <th class="border-0" style="font-size:0.8rem;">SLA</th>
                            <th class="border-0" style="font-size:0.8rem;">ODP</th>
                            <th class="border-0" style="font-size:0.8rem;">Waktu</th>
                            <th class="border-0 px-3" style="font-size:0.8rem;">Aksi</th>
                        </tr>

                    <tbody>
                        @forelse($incidents as $inc)
                        <tr>
                            <td class="px-3 fw-semibold">#{{ $inc->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $inc->title }}</div>
                                <small class="text-muted">{{ $inc->type === 'auto' ? 'Auto-detect' : 'Manual' }} &middot; {{ $inc->creator?->name }}</small>
                            </td>
                            <td>
                                @php
                                    $sevClass = match($inc->severity) {
                                        'critical' => 'bg-danger',
                                        'high' => 'bg-warning text-dark',
                                        'medium' => 'bg-info',
                                        'low' => 'bg-secondary',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $sevClass }}" style="border-radius:6px;">{{ ucfirst($inc->severity) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusClass = match($inc->status) {
                                        'open' => 'bg-primary',
                                        'investigating' => 'bg-info',
                                        'resolved' => 'bg-success',
                                        'closed' => 'bg-secondary',
                                        default => 'bg-secondary',
                                    };
                                    $statusIcon = match($inc->status) {
                                        'open' => 'fa-flag',
                                        'investigating' => 'fa-magnifying-glass',
                                        'resolved' => 'fa-check-circle',
                                        'closed' => 'fa-lock',
                                        default => 'fa-circle',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}" style="border-radius:6px;">
                                    <i class="fa-solid {{ $statusIcon }} me-1" style="font-size:0.65rem;"></i>{{ ucfirst($inc->status) }}
                                </span>
                            </td>
                            <td>
                                @if(in_array($inc->status, ['open', 'investigating']) && $inc->sla_deadline)
                                    @if($inc->sla_status === 'breached')
                                        <span class="badge bg-danger" style="border-radius:6px;">BREACHED</span>
                                    @else
                                        <small class="text-muted">{{ $inc->sla_remaining }}</small>
                                    @endif
                                @elseif($inc->sla_status === 'met')
                                    <span class="badge bg-success" style="border-radius:6px;">Met</span>
                                @elseif($inc->sla_status === 'breached')
                                    <span class="badge bg-danger" style="border-radius:6px;">Breached</span>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td><small>{{ $inc->odp?->nama_odp ?? '-' }}</small></td>
                            <td><small class="text-muted">{{ $inc->detected_at?->diffForHumans() ?? '-' }}</small></td>
                            <td class="px-3">
                                <a href="{{ route('incidents.show', $inc) }}" class="btn btn-sm btn-outline-primary" style="border-radius:8px;">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-shield-halved" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
                                Belum ada incident
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($incidents->hasPages())
        <div class="card-footer bg-white border-0 py-3" style="border-radius:0 0 12px 12px;">
            {{ $incidents->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
