@extends('layouts.app')

@section('title', 'Bufferbloat Analyzer — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">Bufferbloat Analyzer</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-chart-line me-2" style="color:#ef4444;"></i>Bufferbloat Analyzer</h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <form method="GET" class="d-inline">
            <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($routers as $r)
                <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

{{-- ═══ STATUS CARDS ═══ --}}
<div class="row g-4 mb-4">
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ ($analysis['has_cake'] ?? false) ? '#10b981' : '#ef4444' }};">
            <div class="card-body py-3">
                <h6 class="fw-bold" style="font-size:0.85rem;">CAKE Queue</h6>
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-{{ ($analysis['has_cake'] ?? false) ? 'circle-check text-success' : 'circle-xmark text-danger' }} me-2"></i>
                    <span style="font-size:0.82rem;">{{ ($analysis['has_cake'] ?? false) ? 'Active' : 'Not Configured' }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ ($analysis['has_fq_codel'] ?? false) ? '#10b981' : '#f59e0b' }};">
            <div class="card-body py-3">
                <h6 class="fw-bold" style="font-size:0.85rem;">FQ-CoDel Queue</h6>
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-{{ ($analysis['has_fq_codel'] ?? false) ? 'circle-check text-success' : 'circle-exclamation text-warning' }} me-2"></i>
                    <span style="font-size:0.82rem;">{{ ($analysis['has_fq_codel'] ?? false) ? 'Active' : 'Not Configured' }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #8b5cf6;">
            <div class="card-body py-3">
                <h6 class="fw-bold" style="font-size:0.85rem;">Total Queues</h6>
                <div class="fw-bold" style="font-size:1.3rem;color:#8b5cf6;">{{ count($analysis['queues'] ?? []) }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #06b6d4;">
            <div class="card-body py-3">
                <h6 class="fw-bold" style="font-size:0.85rem;">Queue Types</h6>
                <div class="fw-bold" style="font-size:1.3rem;color:#06b6d4;">{{ count($analysis['queue_types'] ?? []) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ANALYSIS ═══ --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-magnifying-glass-chart me-2" style="color:#ef4444;"></i>Bufferbloat Analysis</h6></div>
            <div class="card-body" style="font-size:0.85rem;">
                <div class="mb-4">
                    <h6 class="fw-bold" style="color:#6366f1;">Queue Delay Assessment</h6>
                    @php $queuesWithLimit = collect($analysis['queues'] ?? [])->filter(fn ($q) => ! empty($q['max-limit'])); @endphp
                    @if($queuesWithLimit->count() > 0)
                    <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        
<thead class="mon-thead">
    <tr><th>Queue</th><th>Max Limit</th><th>Current Rate</th><th>Load</th><th>Risk</th></tr></thead>
                            <tbody>
                                @foreach($queuesWithLimit as $q)
                                @php
                                    $maxBps = \App\Services\Mikrotik\TrafficEngineering\TrafficEngineeringManager::formatRate((float)$q['max-limit']);
                                    $rate = $q['rate'] ?? '0';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $q['name'] ?? '' }}</td>
                                    <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $q['max-limit'] ?? '—' }}</span></td>
                                    <td style="font-size:0.78rem;">{{ $rate }}</td>
                                    <td>
                                        <div class="progress" style="height:6px;width:80px;">
                                            <div class="progress-bar bg-{{ ($rate === '0' || $rate === '0bps') ? 'secondary' : 'success' }}" style="width:50%"></div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-{{ ($rate !== '0' && $rate !== '0bps') ? 'warning text-dark' : 'secondary' }}" style="font-size:0.62rem;">{{ ($rate !== '0' && $rate !== '0bps') ? 'MONITOR' : 'OK' }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted mb-0">No queues with max-limit configured.</p>
                    @endif
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold" style="color:#f59e0b;">Interface Utilization</h6>
                    @foreach($analysis['interfaces'] ?? [] as $if)
                        @if(($if['type'] ?? '') !== 'loopback')
                        <div class="d-flex align-items-center mb-2">
                            <span style="width:120px;font-size:0.8rem;" class="fw-semibold">{{ $if['name'] ?? '' }}</span>
                            <div class="progress flex-grow-1" style="height:12px;">
                                <div class="progress-bar bg-{{ ($if['disabled'] ?? 'false') === 'true' ? 'secondary' : 'info' }}" style="width:{{ ($if['disabled'] ?? 'false') === 'true' ? '0' : '30' }}%"></div>
                            </div>
                            <span class="ms-2" style="font-size:0.75rem;">{{ $if['rate'] ?? '0' }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-lightbulb me-2" style="color:#f59e0b;"></i>QoS Recommendations</h6></div>
            <div class="card-body" style="font-size:0.82rem;">
                @if(!($analysis['has_cake'] ?? false) && !($analysis['has_fq_codel'] ?? false))
                <div class="alert alert-warning py-2 mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i>Neither CAKE nor FQ-CoDel is configured. This may cause bufferbloat under load.</div>
                @endif

                <h6 class="fw-bold" style="font-size:0.82rem;">Recommended Tuning:</h6>
                <ul class="list-unstyled" style="font-size:0.8rem;">
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Enable <strong>CAKE</strong> on WAN interfaces for best bufferbloat protection.</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Use <strong>FQ-CoDel</strong> as fallback if CAKE is unavailable.</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Set <strong>diffserv4</strong> or <strong>diffserv8</strong> for DSCP-aware QoS.</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Configure <strong>per-host</strong> or <strong>perip</strong> flow isolation.</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Set <strong>memlimit</strong> to prevent buffer bloat in queue.</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check me-1 text-success"></i>Enable <strong>NAT</strong> option in CAKE for NATted connections.</li>
                </ul>

                <h6 class="fw-bold mt-3" style="font-size:0.82rem;">Queue Types on Router:</h6>
                @forelse($analysis['queue_types'] ?? [] as $qt)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span><span class="badge bg-light text-dark" style="font-size:0.62rem;">{{ $qt['name'] ?? '' }}</span></span>
                    <span class="badge bg-info" style="font-size:0.6rem;">{{ $qt['kind'] ?? 'unknown' }}</span>
                </div>
                @empty
                <p class="text-muted" style="font-size:0.8rem;">No custom queue types found.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

