@extends('layouts.app')

@section('title', 'IP Conflicts — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard', ['router_id' => $routerId ?? '']) }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">IP Conflicts</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-shield-halved me-2" style="color:#f59e0b;"></i>IP Conflicts</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ $total }} konflik terdeteksi</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        </form>
        <a href="{{ route('noc.internet.dashboard', ['router_id' => $routerId ?? '']) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4 py-2" style="font-size:0.85rem;">
        <i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
@if(isset($routers) && $routers->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($routerId ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══ STAT ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,{{ $total > 0 ? '#ef4444,#dc2626' : '#10b981,#059669' }});min-height:110px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <div class="stat-number">{{ $total }}</div>
                        <div class="stat-label">IP Conflicts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CONFLICTS TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>IP Address</th>
                        <th>Entry 1</th>
                        <th>MAC 1</th>
                        <th>Entry 2</th>
                        <th>MAC 2</th>
                        <th class="text-center">Severity</th>
                    </tr>

                <tbody>
                    @forelse($conflicts as $conflict)
                    <tr>
                        <td><code class="fw-bold text-danger">{{ $conflict['ip'] }}</code></td>
                        @if(isset($conflict['entries'][0]))
                        <td>
                            <span class="badge bg-{{ ($conflict['entries'][0]['type'] ?? '') === 'dhcp' ? 'primary' : (($conflict['entries'][0]['type'] ?? '') === 'ppp' ? 'info' : 'secondary') }}" style="font-size:0.65rem;">{{ strtoupper($conflict['entries'][0]['type'] ?? '') }}</span>
                            {{ $conflict['entries'][0]['owner'] ?? '' }}
                        </td>
                        <td><code style="font-size:0.72rem;">{{ $conflict['entries'][0]['mac'] ?? '—' }}</code></td>
                        @else
                        <td>—</td><td>—</td>
                        @endif
                        @if(isset($conflict['entries'][1]))
                        <td>
                            <span class="badge bg-{{ ($conflict['entries'][1]['type'] ?? '') === 'dhcp' ? 'primary' : (($conflict['entries'][1]['type'] ?? '') === 'ppp' ? 'info' : 'secondary') }}" style="font-size:0.65rem;">{{ strtoupper($conflict['entries'][1]['type'] ?? '') }}</span>
                            {{ $conflict['entries'][1]['owner'] ?? '' }}
                        </td>
                        <td><code style="font-size:0.72rem;">{{ $conflict['entries'][1]['mac'] ?? '—' }}</code></td>
                        @else
                        <td>—</td><td>—</td>
                        @endif
                        <td class="text-center">
                            @php
                                $types = array_column($conflict['entries'] ?? [], 'type');
                                $isCross = count(array_unique($types)) > 1;
                            @endphp
                            @if($isCross)
                                <span class="badge bg-danger" style="font-size:0.62rem;">HIGH</span>
                            @else
                                <span class="badge bg-warning text-dark" style="font-size:0.62rem;">MEDIUM</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fa-solid fa-shield-check" style="font-size:2rem;color:#10b981;"></i>
                            <p class="mt-2 mb-0 fw-semibold" style="color:#10b981;">Tidak ada konflik IP terdeteksi</p>
                            <small class="text-muted">Semua IP address unik dan tidak tumpang tindih.</small>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

