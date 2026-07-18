@extends('layouts.app')

@section('title', 'Hotspot Cookies — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard', ['router_id' => $router->id ?? '']) }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Hotspot Cookies</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-cookie me-2" style="color:var(--primary);"></i>Hotspot Cookies</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $router->display_identity ?? 'No router' }} · {{ count($items) }} cookies</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="GET" class="d-inline">
            <input type="hidden" name="router_id" value="{{ $router->id ?? '' }}">
            <button type="submit" class="btn btn-outline-primary px-3 py-2"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        </form>
        <a href="{{ route('noc.internet.dashboard', ['router_id' => $router->id ?? '']) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
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

{{-- ═══ STAT ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--primary);color:#fff;">
                    <i class="fa-solid fa-cookie"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.75rem;">Total Cookies</div>
                    <div class="fw-bold" style="font-size:1.3rem;">{{ count($items) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ROUTER SELECTOR ═══ --}}
@if($routers && $routers->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($router->id ?? '') == $r->id ? 'selected' : '' }}>{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search cookies..." onkeyup="filterTable()">
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══ DATA TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>User</th>
                        <th>Cookie</th>
                        <th>IP Address</th>
                        <th>MAC Address</th>
                        <th>Server</th>
                        <th>Expires</th>
                    </tr>

                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item['user'] ?? '—' }}</td>
                        <td><code style="font-size:0.72rem;">{{ substr($item['cookie'] ?? '', 0, 20) }}{{ strlen($item['cookie'] ?? '') > 20 ? '...' : '' }}</code></td>
                        <td><code>{{ $item['address'] ?? '—' }}</code></td>
                        <td><code style="font-size:0.75rem;">{{ $item['mac-address'] ?? '—' }}</code></td>
                        <td>{{ $item['server'] ?? '—' }}</td>
                        <td style="font-size:0.78rem;">{{ $item['expires'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-cookie" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0">No hotspot cookies found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
@endpush

