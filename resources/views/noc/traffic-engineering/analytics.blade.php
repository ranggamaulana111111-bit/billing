@extends('layouts.app')

@section('title', 'Traffic Analytics — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">Traffic Analytics</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-chart-bar me-2" style="color:#ec4899;"></i>Traffic Analytics</h2>
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

<div class="row g-4">
    {{-- ═══ TOP INTERFACES ═══ --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-network-wired me-2" style="color:#10b981;"></i>Top Interfaces</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>#</th><th>Interface</th><th>Rate</th><th>Type</th></tr></thead>
                        <tbody>
                            @forelse($analytics['topInterfaces'] ?? [] as (int) $idx => $if)
                            <tr>
                                <td class="text-muted">{{ $idx + 1 }}</td>
                                <td class="fw-semibold">{{ $if['name'] ?? '' }}</td>
                                <td><span class="badge bg-{{ $idx === 0 ? 'danger' : ($idx < 3 ? 'warning text-dark' : 'secondary') }}" style="font-size:0.65rem;">{{ $if['rate'] ?? '0' }}</span></td>
                                <td style="font-size:0.75rem;">{{ $if['type'] ?? '' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No interfaces</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TOP QUEUES ═══ --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-layer-group me-2" style="color:#6366f1;"></i>Top Queues</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>#</th><th>Queue</th><th>Rate</th><th>Target</th></tr></thead>
                        <tbody>
                            @forelse($analytics['topQueues'] ?? [] as (int) $idx => $q)
                            <tr>
                                <td class="text-muted">{{ $idx + 1 }}</td>
                                <td class="fw-semibold">{{ $q['name'] ?? '' }}</td>
                                <td><span class="badge bg-{{ $idx === 0 ? 'danger' : ($idx < 3 ? 'warning text-dark' : 'secondary') }}" style="font-size:0.65rem;">{{ $q['rate'] ?? '0' }}</span></td>
                                <td style="font-size:0.75rem;">{{ $q['target'] ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No queues</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TOP QUEUE TREES ═══ --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-sitemap me-2" style="color:#8b5cf6;"></i>Top Queue Trees</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>#</th><th>Queue Tree</th><th>Rate</th><th>Parent</th></tr></thead>
                        <tbody>
                            @forelse($analytics['topTrees'] ?? [] as (int) $idx => $t)
                            <tr>
                                <td class="text-muted">{{ $idx + 1 }}</td>
                                <td class="fw-semibold">{{ $t['name'] ?? '' }}</td>
                                <td><span class="badge bg-{{ $idx === 0 ? 'danger' : ($idx < 3 ? 'warning text-dark' : 'secondary') }}" style="font-size:0.65rem;">{{ $t['rate'] ?? '0' }}</span></td>
                                <td style="font-size:0.75rem;">{{ $t['parent'] ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No queue trees</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ PACKET MARK USAGE ═══ --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-tags me-2" style="color:#f59e0b;"></i>Packet Mark Usage Analytics</h6></div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr><th>#</th><th>Packet Mark</th><th>Rules</th><th>Total Packets</th><th>Total Bytes</th><th>Formatted</th></tr>

                <tbody>
                    @forelse($analytics['packetMarkUsage'] ?? [] as $mark => $usage)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td><span class="badge bg-warning text-dark" style="font-size:0.65rem;">{{ $mark }}</span></td>
                        <td>{{ $usage['count'] }}</td>
                        <td style="font-size:0.78rem;">{{ number_format($usage['packets']) }}</td>
                        <td style="font-size:0.78rem;">{{ number_format($usage['bytes']) }}</td>
                        <td style="font-size:0.78rem;">{{ \App\Services\Mikrotik\TrafficEngineering\TrafficEngineeringManager::formatBytes($usage['bytes']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="fa-solid fa-tags" style="font-size:1.5rem;"></i>
                        <p class="mt-1 mb-0">No packet mark usage data</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

