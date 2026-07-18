@extends('layouts.app')

@section('title', 'Traffic Classification — Traffic Engineering')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.traffic_eng.dashboard', ['router_id' => request('router_id')]) }}">Traffic Eng & QoS</a></li>
                <li class="breadcrumb-item active">Traffic Classification</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-tags me-2" style="color:#f59e0b;"></i>Traffic Classification</h2>
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

<div class="row g-4 mb-4">
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#f59e0b,#f59e0bdd);border-radius:16px;min-height:100px;">
            <div class="card-body">
                <div class="stat-number">{{ count($classification['mangleItems'] ?? []) }}</div>
                <div class="stat-label">Mangle Rules</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#8b5cf6,#8b5cf6dd);border-radius:16px;min-height:100px;">
            <div class="card-body">
                <div class="stat-number">{{ count($classification['packetMarks'] ?? []) }}</div>
                <div class="stat-label">Packet Marks</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#10b981,#10b981dd);border-radius:16px;min-height:100px;">
            <div class="card-body">
                <div class="stat-number">{{ count($classification['connMarks'] ?? []) }}</div>
                <div class="stat-label">Connection Marks</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#06b6d4,#06b6d4dd);border-radius:16px;min-height:100px;">
            <div class="card-body">
                <div class="stat-number">{{ count($classification['routingMarks'] ?? []) }}</div>
                <div class="stat-label">Routing Marks</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- ═══ PACKET MARKS ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-tag me-2" style="color:#8b5cf6;"></i>Packet Marks</h6></div>
            <div class="card-body p-0">
                @if(count($classification['packetMarks'] ?? []) > 0)
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Mark Name</th><th class="text-end">Rules Count</th></tr></thead>
                        <tbody>
                            @foreach($classification['packetMarks'] as $mark => $count)
                            <tr><td><span class="badge bg-warning text-dark" style="font-size:0.65rem;">{{ $mark }}</span></td><td class="text-end">{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted"><i class="fa-solid fa-tag" style="font-size:1.5rem;"></i><p class="mt-1 mb-0">No packet marks found</p></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ CONNECTION MARKS ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-link me-2" style="color:#10b981;"></i>Connection Marks</h6></div>
            <div class="card-body p-0">
                @if(count($classification['connMarks'] ?? []) > 0)
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Mark Name</th><th class="text-end">Rules Count</th></tr></thead>
                        <tbody>
                            @foreach($classification['connMarks'] as $mark => $count)
                            <tr><td><span class="badge bg-success" style="font-size:0.65rem;">{{ $mark }}</span></td><td class="text-end">{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted"><i class="fa-solid fa-link" style="font-size:1.5rem;"></i><p class="mt-1 mb-0">No connection marks found</p></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ DSCP MARKS ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-sort-amount-down me-2" style="color:#06b6d4;"></i>DSCP Classification</h6></div>
            <div class="card-body p-0">
                @if(count($classification['dscpCounts'] ?? []) > 0)
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>DSCP Value</th><th class="text-end">Rules Count</th></tr></thead>
                        <tbody>
                            @foreach($classification['dscpCounts'] as $dscp => $count)
                            <tr><td><span class="badge bg-info" style="font-size:0.65rem;">DSCP {{ $dscp }}</span></td><td class="text-end">{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted"><i class="fa-solid fa-sort-amount-down" style="font-size:1.5rem;"></i><p class="mt-1 mb-0">No DSCP rules found</p></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ ROUTING MARKS ═══ --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-route me-2" style="color:#ec4899;"></i>Routing Marks</h6></div>
            <div class="card-body p-0">
                @if(count($classification['routingMarks'] ?? []) > 0)
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
<thead class="mon-thead">
    <tr><th>Mark Name</th><th class="text-end">Rules Count</th></tr></thead>
                        <tbody>
                            @foreach($classification['routingMarks'] as $mark => $count)
                            <tr><td><span class="badge bg-danger" style="font-size:0.65rem;">{{ $mark }}</span></td><td class="text-end">{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted"><i class="fa-solid fa-route" style="font-size:1.5rem;"></i><p class="mt-1 mb-0">No routing marks found</p></div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══ MANGLE RULES TABLE ═══ --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="fa-solid fa-tag me-2" style="color:#f59e0b;"></i>Mangle Rules Detail</h6></div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr><th>#</th><th>Chain</th><th>Action</th><th>Packet Mark</th><th>Conn Mark</th><th>Routing Mark</th><th>DSCP</th><th>Src</th><th>Dst</th><th>Hits</th></tr>

                <tbody>
                    @forelse(($classification['mangleItems'] ?? []) as (int) $idx => $item)
                    <tr>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $item['chain'] ?? '' }}</span></td>
                        <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $item['action'] ?? '' }}</span></td>
                        <td><span class="badge bg-warning text-dark" style="font-size:0.62rem;">{{ $item['new-packet-marks'] ?? '—' }}</span></td>
                        <td><span class="badge bg-success" style="font-size:0.62rem;">{{ $item['new-connection-mark'] ?? '—' }}</span></td>
                        <td><span class="badge bg-danger" style="font-size:0.62rem;">{{ $item['new-routing-mark'] ?? '—' }}</span></td>
                        <td style="font-size:0.78rem;">{{ $item['dscp'] ?? '—' }}</td>
                        <td style="font-size:0.75rem;">{{ $item['src-address'] ?? '—' }}</td>
                        <td style="font-size:0.75rem;">{{ $item['dst-address'] ?? '—' }}</td>
                        <td style="font-size:0.75rem;">{{ $item['packets'] ?? '0' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No mangle rules found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

