@extends('layouts.app')

@section('title', 'Active Sessions — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="#">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Active Sessions</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-signal fa-lg me-2" style="color:var(--primary);"></i>Active Sessions
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            PPP + Hotspot real-time monitoring
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-outline-primary px-3 py-2" id="btnRefresh">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
    </div>
</div>

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $error }}
</div>
@endif

@if(session('success'))
<div class="alert alert-success d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center mb-4 py-2" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
</div>
@endif

{{-- ═══ ROUTER SELECTOR ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $router->id == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search sessions..." onkeyup="filterAllTables()">
            </div>
        </form>
    </div>
</div>

{{-- ═══ STAT CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4 col-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;min-width:40px;background:rgba(37,99,235,0.1);">
                        <i class="fa-solid fa-link" style="color:#2563eb;font-size:0.9rem;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.7rem;">PPP Online</div>
                        <h5 class="mb-0 fw-bold">{{ count($pppActive ?? []) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;min-width:40px;background:rgba(139,92,246,0.1);">
                        <i class="fa-solid fa-wifi" style="color:#8b5cf6;font-size:0.9rem;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.7rem;">Hotspot Online</div>
                        <h5 class="mb-0 fw-bold">{{ count($hotspotActive ?? []) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;min-width:40px;background:rgba(25,135,84,0.1);">
                        <i class="fa-solid fa-users" style="color:#198754;font-size:0.9rem;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.7rem;">Total</div>
                        <h5 class="mb-0 fw-bold">{{ count($pppActive ?? []) + count($hotspotActive ?? []) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ PPPoE ACTIVE SESSIONS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fw-bold" style="font-size:0.85rem;"><i class="fa-solid fa-link me-2" style="color:#2563eb;"></i>PPPoE Active</h6>
        <span class="badge bg-primary" style="font-size:0.65rem;">{{ count($pppActive ?? []) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="white-space:nowrap;">Username</th>
                        <th class="d-none d-md-table-cell">Service</th>
                        <th class="d-none d-lg-table-cell">Caller ID</th>
                        <th>IP Address</th>
                        <th>Uptime</th>
                        <th class="d-none d-md-table-cell">Rate</th>
                        <th style="width:60px;"></th>
                    </tr>

                <tbody>
                    @forelse($pppActive ?? [] as $session)
                    <tr>
                        <td class="fw-semibold" style="white-space:nowrap;">{{ $session['name'] ?? '—' }}</td>
                        <td class="d-none d-md-table-cell"><span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $session['service'] ?? 'pppoe' }}</span></td>
                        <td class="d-none d-lg-table-cell"><code style="font-size:0.72rem;">{{ $session['caller-id'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.72rem;">{{ $session['address'] ?? '—' }}</code></td>
                        <td><span class="badge bg-primary" style="font-size:0.65rem;">{{ $session['uptime'] ?? '—' }}</span></td>
                        <td class="d-none d-md-table-cell" style="font-size:0.72rem;">
                            @if(isset($session['rate']))
                            <span class="text-success"><i class="fa-solid fa-arrow-down"></i> {{ $session['rate'] }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('noc.internet.disconnect-session', ['type' => 'ppp', 'sessionId' => $session['.id']]) }}" class="d-inline" onsubmit="return confirm('Disconnect {{ addslashes($session['name'] ?? '') }}?')">
                                @csrf
                                <input type="hidden" name="router_id" value="{{ $router->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Disconnect">
                                    <i class="fa-solid fa-link-slash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="fa-solid fa-link-slash" style="font-size:1.5rem;"></i>
                        <p class="mt-2 mb-0">Tidak ada sesi PPPoE aktif</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ HOTSPOT ACTIVE SESSIONS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fw-bold" style="font-size:0.85rem;"><i class="fa-solid fa-wifi me-2" style="color:#8b5cf6;"></i>Hotspot Active</h6>
        <span class="badge" style="background:#8b5cf6;font-size:0.65rem;">{{ count($hotspotActive ?? []) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="white-space:nowrap;">User</th>
                        <th>IP Address</th>
                        <th class="d-none d-md-table-cell">MAC Address</th>
                        <th>Uptime</th>
                        <th class="d-none d-lg-table-cell">Server</th>
                        <th style="width:60px;"></th>
                    </tr>

                <tbody>
                    @forelse($hotspotActive ?? [] as $session)
                    <tr>
                        <td class="fw-semibold" style="white-space:nowrap;">{{ $session['user'] ?? '—' }}</td>
                        <td><code style="font-size:0.72rem;">{{ $session['address'] ?? '—' }}</code></td>
                        <td class="d-none d-md-table-cell"><code style="font-size:0.7rem;">{{ $session['mac-address'] ?? '—' }}</code></td>
                        <td><span class="badge" style="background:#8b5cf6;font-size:0.65rem;">{{ $session['uptime'] ?? '—' }}</span></td>
                        <td class="d-none d-lg-table-cell">{{ $session['server'] ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('noc.internet.disconnect-session', ['type' => 'hotspot', 'sessionId' => $session['.id']]) }}" class="d-inline" onsubmit="return confirm('Disconnect {{ addslashes($session['user'] ?? '') }}?')">
                                @csrf
                                <input type="hidden" name="router_id" value="{{ $router->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Disconnect">
                                    <i class="fa-solid fa-link-slash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="fa-solid fa-wifi-slash" style="font-size:1.5rem;"></i>
                        <p class="mt-2 mb-0">Tidak ada sesi hotspot aktif</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function filterAllTables() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#pppTable tbody tr, #hotspotTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

document.getElementById('btnRefresh')?.addEventListener('click', function() {
    location.reload();
});
</script>

