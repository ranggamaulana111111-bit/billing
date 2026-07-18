@extends('layouts.app')

@section('title', 'Monitoring Center — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard', ['router_id' => $routerId ?? '']) }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Monitoring Center</li>
            </ol>
        </nav>
        <h2 class="mb-0"><i class="fa-solid fa-heart-pulse me-2" style="color:#ef4444;"></i>Monitoring Center</h2>
        <p class="section-subtitle mb-0 mt-1">Status realtime & sinkronisasi layanan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <span class="badge bg-success d-flex align-items-center" style="font-size:0.72rem;">
            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
        </span>
        <button type="button" class="btn btn-outline-primary px-3 py-2" onclick="location.reload()">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
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

@if($router ?? null)
{{-- ═══ ROUTER STATUS ═══ --}}
<div class="bento-grid mb-4">
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,{{ $router_status === 'online' ? '#10b981,#059669' : '#ef4444,#dc2626' }});min-height:120px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-server"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-server"></i></div>
                    <div>
                        <div class="stat-number" style="font-size:1rem;">{{ strtoupper($router_status) }}</div>
                        <div class="stat-label">Router Status</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);min-height:120px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-microchip"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-microchip"></i></div>
                    <div>
                        <div class="stat-number" style="font-size:1.1rem;" id="router-cpu">{{ $router_cpu }}%</div>
                        <div class="stat-label">CPU Load</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);min-height:120px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-memory"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-memory"></i></div>
                    <div>
                        <div class="stat-number" style="font-size:1rem;" id="router-uptime">{{ $router_uptime }}</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="span-1">
        <div class="card stat-card text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);min-height:120px;border-radius:16px;overflow:hidden;position:relative;">
            <div class="stat-bg"><i class="fa-solid fa-code-branch"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-code-branch"></i></div>
                    <div>
                        <div class="stat-number" style="font-size:0.95rem;" id="router-version">{{ $router_version }}</div>
                        <div class="stat-label">RouterOS Version</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODULE STATUS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-puzzle-piece me-2" style="color:var(--primary);"></i>Module Status</h6>
        <span class="text-muted" style="font-size:0.72rem;">{{ count($module_stats) }} modules</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
    
                
    
                    <tr>
                        <th>Module</th>
                        <th>Path</th>
                        <th class="text-center">Total Items</th>
                        <th class="text-center">Active</th>
                        <th class="text-center">Status</th>
                    </tr>

                <tbody>
                    @php
                    $moduleLabels = [
                        'ip_pool' => ['IP Pool', 'fa-layer-group', '#2563eb'],
                        'dhcp_server' => ['DHCP Server', 'fa-server', '#10b981'],
                        'dhcp_lease' => ['DHCP Lease', 'fa-address-card', '#8b5cf6'],
                        'ppp_profile' => ['PPP Profile', 'fa-sliders', '#f59e0b'],
                        'ppp_secret' => ['PPP Secret', 'fa-key', '#ec4899'],
                        'hotspot_server' => ['Hotspot Server', 'fa-wifi', '#06b6d4'],
                        'hotspot_user' => ['Hotspot User', 'fa-users', '#6366f1'],
                        'hotspot_profile' => ['Hotspot Profile', 'fa-id-badge', '#14b8a6'],
                    ];
                    @endphp
                    @foreach($module_stats as $key => $stat)
                    @php $label = $moduleLabels[$key] ?? [$key, 'fa-cube', '#64748b']; @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid {{ $label[1] }}" style="color:{{ $label[2] }};"></i>
                                <span class="fw-semibold">{{ $label[0] }}</span>
                            </div>
                        </td>
                        <td><code style="font-size:0.72rem;">{{ $stat['path'] }}</code></td>
                        <td class="text-center fw-bold">{{ $stat['total'] }}</td>
                        <td class="text-center">{{ $stat['active'] ?? $stat['online'] ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge bg-success" style="font-size:0.6rem;">
                                <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>OK
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ INTERFACES ═══ --}}
@if(!empty($interfaces))
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>Interfaces ({{ count($interfaces) }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
    
                
    
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">TX Rate</th>
                        <th class="text-end">RX Rate</th>
                    </tr>

                <tbody>
                    @foreach($interfaces as $iface)
                    <tr>
                        <td class="fw-semibold">{{ $iface['name'] ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.68rem;">{{ $iface['type'] ?? '—' }}</span></td>
                        <td class="text-center">
                            @if(($iface['disabled'] ?? 'false') === 'true')
                                <span class="badge bg-secondary" style="font-size:0.6rem;">DISABLED</span>
                            @elseif(($iface['running'] ?? 'false') === 'true')
                                <span class="badge bg-success" style="font-size:0.6rem;">
                                    <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>UP
                                </span>
                            @else
                                <span class="badge bg-danger" style="font-size:0.6rem;">DOWN</span>
                            @endif
                        </td>
                        <td class="text-end" style="font-size:0.78rem;" data-txrate data-iface="{{ $iface['name'] ?? '' }}">{{ $iface['tx-rate'] ?? '—' }}</td>
                        <td class="text-end" style="font-size:0.78rem;" data-rxrate data-iface="{{ $iface['name'] ?? '' }}">{{ $iface['rx-rate'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ═══ ACTIVE PPP SESSIONS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-key me-2" style="color:var(--primary);"></i>Active PPP Sessions</h6>
        <span class="badge bg-light text-dark" style="font-size:0.68rem;" id="ppp-active-count">{{ count($ppp_active ?? []) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap" style="max-height:340px;overflow-y:auto;">
<table class="table table-hover align-middle mb-0 mon-table">
            
    
                
    
                    <tr>
                        <th>User</th>
                        <th>Service</th>
                        <th>IP Address</th>
                        <th>MAC / Caller-ID</th>
                        <th class="text-end">Uptime</th>
                    </tr>

                <tbody id="ppp-active-tbody">
                    @forelse($ppp_active ?? [] as $s)
                    <tr>
                        <td class="fw-semibold">{{ $s['name'] ?? $s['user'] ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark" style="font-size:0.68rem;">{{ $s['service'] ?? 'pppoe' }}</span></td>
                        <td><code style="font-size:0.72rem;">{{ $s['address'] ?? '—' }}</code></td>
                        <td style="font-size:0.74rem;">{{ $s['caller-id'] ?? '—' }}</td>
                        <td class="text-end" style="font-size:0.76rem;">{{ $s['uptime'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi PPP aktif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ ACTIVE HOTSPOT SESSIONS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-wifi me-2" style="color:var(--primary);"></i>Active Hotspot Sessions</h6>
        <span class="badge bg-light text-dark" style="font-size:0.68rem;" id="hotspot-active-count">{{ count($hotspot_active ?? []) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap" style="max-height:340px;overflow-y:auto;">
<table class="table table-hover align-middle mb-0 mon-table">
            
    
                
    
                    <tr>
                        <th>User</th>
                        <th>Server</th>
                        <th>IP Address</th>
                        <th>MAC</th>
                        <th class="text-end">Uptime</th>
                    </tr>

                <tbody id="hotspot-active-tbody">
                    @forelse($hotspot_active ?? [] as $s)
                    <tr>
                        <td class="fw-semibold">{{ $s['user'] ?? $s['name'] ?? '—' }}</td>
                        <td style="font-size:0.74rem;">{{ $s['server'] ?? '—' }}</td>
                        <td><code style="font-size:0.72rem;">{{ $s['address'] ?? '—' }}</code></td>
                        <td style="font-size:0.74rem;">{{ $s['mac-address'] ?? '—' }}</td>
                        <td class="text-end" style="font-size:0.76rem;">{{ $s['uptime'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi Hotspot aktif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ SYNC STATUS ═══ --}}
@if($last_sync)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-rotate me-2" style="color:var(--primary);"></i>Last Sync Status</h6>
    </div>
    <div class="card-body" style="font-size:0.82rem;">
        <div class="row g-3">
            <div class="col-md-3">
                <small class="text-muted d-block">Module</small>
                <span class="fw-semibold">{{ $last_sync->module ?? '—' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Status</small>
                <span class="badge bg-{{ ($last_sync->status ?? '') === 'success' ? 'success' : 'danger' }}" style="font-size:0.68rem;">{{ ucfirst($last_sync->status ?? 'unknown') }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Duration</small>
                <span class="fw-semibold">{{ number_format($last_sync->duration_ms ?? 0, 1) }}ms</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Last Synced</small>
                <span class="fw-semibold">{{ $last_sync->created_at?->diffForHumans() ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ═══ RECENT CHANGES ═══ --}}
@if($recent_changes->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>Recent Changes</h6>
        <a href="{{ route('noc.internet.audit', ['router_id' => $routerId]) }}" style="font-size:0.78rem;">Lihat Semua &rarr;</a>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
    
                
    
                    <tr>
                        <th>Time</th>
                        <th>Resource</th>
                        <th>Item</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>User</th>
                    </tr>

                <tbody>
                    @foreach($recent_changes as $log)
                    <tr>
                        <td style="font-size:0.78rem;">{{ $log->created_at->diffForHumans() }}</td>
                        <td><span class="badge bg-info" style="font-size:0.65rem;">{{ str_replace('internet_service.', '', $log->resource_type) }}</span></td>
                        <td>{{ $log->item_name ?: $log->item_id ?: '—' }}</td>
                        <td><span class="badge bg-{{ $log->action_badge }}" style="font-size:0.65rem;">{{ ucfirst($log->action) }}</span></td>
                        <td><span class="badge bg-{{ $log->status_badge }}" style="font-size:0.65rem;">{{ ucfirst($log->status) }}</span></td>
                        <td>{{ $log->user->name ?? 'System' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@else
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div><strong>No active router found.</strong> Please add and configure a MikroTik router first.</div>
</div>
@endif
@endsection

@push('scripts')
<script>
function ifFmtRate(bps) {
    if (!bps || bps <= 0) return '0 bps';
    if (bps >= 1000000000) return (bps / 1000000000).toFixed(2) + ' Gbps';
    if (bps >= 1000000) return (bps / 1000000).toFixed(2) + ' Mbps';
    if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps';
    return bps.toFixed(0) + ' bps';
}

function applyRateColor(el, name, bps) {
    if (!name || name.toLowerCase().indexOf('pppoe') === -1) return;
    const mbps = bps / 1000000;
    el.style.setProperty('color', '', 'important');
    el.style.fontWeight = '';
    if (mbps > 15) {
        el.style.setProperty('color', '#dc2626', 'important');
        el.style.fontWeight = '700';
    } else if (mbps > 9) {
        el.style.setProperty('color', '#d97706', 'important');
        el.style.fontWeight = '700';
    } else {
        el.style.setProperty('color', '#059669', 'important');
    }
}

function fetchInterfaceRates() {
    const rid = @json($routerId ?? null);
    fetch('{{ route("noc.internet.monitoring.interface-rates") }}' + (rid ? '?router_id=' + rid : ''))
        .then(r => r.json())
        .then(d => {
            const rates = d.rates || {};
            document.querySelectorAll('[data-txrate]').forEach(el => {
                const name = el.getAttribute('data-iface');
                if (!rates[name]) return;
                el.textContent = ifFmtRate(rates[name].tx_rate);
                applyRateColor(el, name, rates[name].tx_rate);
            });
            document.querySelectorAll('[data-rxrate]').forEach(el => {
                const name = el.getAttribute('data-iface');
                if (!rates[name]) return;
                el.textContent = ifFmtRate(rates[name].rx_rate);
                applyRateColor(el, name, rates[name].rx_rate);
            });
        })
        .catch(() => {});
}

function md5(string) {
    function rotateLeft(lValue, iShiftBits) {
        return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
    }
    function addUnsigned(lX, lY) {
        const lX8 = (lX & 0x80000000), lY8 = (lY & 0x80000000);
        const lX4 = (lX & 0x40000000), lY4 = (lY & 0x40000000);
        const lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        if (lX4 | lY4) return (lResult & 0x40000000 ? (lResult ^ 0x80000000 ^ lX8 ^ lY8) : (lResult ^ lX8 ^ lY8));
        return (lResult ^ lX8 ^ lY8);
    }
    function f(x, y, z) { return (x & y) | ((~x) & z); }
    function g(x, y, z) { return (x & z) | (y & (~z)); }
    function h(x, y, z) { return (x ^ y ^ z); }
    function i(x, y, z) { return (y ^ (x | (~z))); }
    function ff(a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(f(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); }
    function gg(a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(g(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); }
    function hh(a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(h(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); }
    function ii(a, b, c, d, x, s, ac) { a = addUnsigned(a, addUnsigned(addUnsigned(i(b, c, d), x), ac)); return addUnsigned(rotateLeft(a, s), b); }
    function convertToWordArray(str) {
        const lWordCount = ((str.length + 8) >> 6) + 1;
        const lWordArray = Array(lWordCount * 16).fill(0);
        for (let i = 0; i < str.length; i++) {
            lWordArray[i >> 2] |= (str.charCodeAt(i) & 0xff) << ((i % 4) * 8);
        }
        lWordArray[str.length >> 2] |= 0x80 << ((str.length % 4) * 8);
        lWordArray[lWordCount * 16 - 2] = str.length << 3;
        return lWordArray;
    }
    function wordToHex(lValue) {
        let word = '', nMask = 0xF;
        for (let i = 0; i <= 3; i++) {
            const n = (lValue >>> (i * 8)) & nMask;
            word += '0123456789abcdef'.charAt(n);
        }
        return word;
    }
    let x = convertToWordArray(string), a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
    for (let k = 0; k < x.length; k += 16) {
        const AA = a, BB = b, CC = c, DD = d;
        a = ff(a, b, c, d, x[k], 7, 0xD76AA478); d = ff(d, a, b, c, x[k+1], 12, 0xE8C7B756);
        c = ff(c, d, a, b, x[k+2], 17, 0x242070DB); b = ff(b, c, d, a, x[k+3], 22, 0xC1BDCEEE);
        a = ff(a, b, c, d, x[k+4], 7, 0xF57C0FAF); d = ff(d, a, b, c, x[k+5], 12, 0x4787C62A);
        c = ff(c, d, a, b, x[k+6], 17, 0xA8304613); b = ff(b, c, d, a, x[k+7], 22, 0xFD469501);
        a = ff(a, b, c, d, x[k+8], 7, 0x698098D8); d = ff(d, a, b, c, x[k+9], 12, 0x8B44F7AF);
        c = ff(c, d, a, b, x[k+10], 17, 0xFFFF5BB1); b = ff(b, c, d, a, x[k+11], 22, 0x895CD7BE);
        a = ff(a, b, c, d, x[k+12], 7, 0x6B901122); d = ff(d, a, b, c, x[k+13], 12, 0xFD987193);
        c = ff(c, d, a, b, x[k+14], 17, 0xA679438E); b = ff(b, c, d, a, x[k+15], 22, 0x49B40821);
        a = gg(a, b, c, d, x[k+1], 5, 0xF61E2562); d = gg(d, a, b, c, x[k+6], 9, 0xC040B340);
        c = gg(c, d, a, b, x[k+11], 14, 0x265E5A51); b = gg(b, c, d, a, x[k], 16, 0xE9B6C7AA);
        a = gg(a, b, c, d, x[k+5], 5, 0xD62F105D); d = gg(d, a, b, c, x[k+10], 9, 0x02441453);
        c = gg(c, d, a, b, x[k+15], 14, 0xD8A1E681); b = gg(b, c, d, a, x[k+4], 20, 0xE7D3FBC8);
        a = gg(a, b, c, d, x[k+9], 5, 0x21E1CDE6); d = gg(d, a, b, c, x[k+14], 9, 0xC33707D6);
        c = gg(c, d, a, b, x[k+3], 14, 0xF4D50D87); b = gg(b, c, d, a, x[k+8], 20, 0x455A14ED);
        a = hh(a, b, c, d, x[k], 4, 0xA9E3E905); d = hh(d, a, b, c, x[k+7], 11, 0xFCEFA3F8);
        c = hh(c, d, a, b, x[k+14], 16, 0x676F02D9); b = hh(b, c, d, a, x[k+5], 23, 0x8D2A4C8A);
        a = hh(a, b, c, d, x[k+12], 4, 0xFFFA3942); d = hh(d, a, b, c, x[k+3], 11, 0x8771F681);
        c = hh(c, d, a, b, x[k+10], 16, 0x6D9D6122); b = hh(b, c, d, a, x[k+1], 23, 0xFDE5380C);
        a = hh(a, b, c, d, x[k+8], 4, 0xA4BEEA44); d = hh(d, a, b, c, x[k+15], 11, 0x4BDECFA9);
        c = hh(c, d, a, b, x[k+6], 16, 0xF6BB4B60); b = hh(b, c, d, a, x[k+13], 23, 0xBEBFBC70);
        a = ii(a, b, c, d, x[k], 6, 0xF4292244); d = ii(d, a, b, c, x[k+7], 10, 0x432AFF97);
        c = ii(c, d, a, b, x[k+14], 15, 0xAB9423A7); b = ii(b, c, d, a, x[k+5], 21, 0xFC93A039);
        a = ii(a, b, c, d, x[k+12], 6, 0x655B59C3); d = ii(d, a, b, c, x[k+3], 10, 0x8F0CCC92);
        c = ii(c, d, a, b, x[k+10], 15, 0xFFEFF47D); b = ii(b, c, d, a, x[k+1], 21, 0x85845DD1);
        a = ii(a, b, c, d, x[k+8], 6, 0x6FA87E4F); d = ii(d, a, b, c, x[k+15], 10, 0xFE2CE6E0);
        c = ii(c, d, a, b, x[k+6], 15, 0xA3014314); b = ii(b, c, d, a, x[k+13], 21, 0x4E0811A1);
        a = addUnsigned(a, AA); b = addUnsigned(b, BB); c = addUnsigned(c, CC); d = addUnsigned(d, DD);
    }
    return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d));
}

fetchInterfaceRates();
setInterval(fetchInterfaceRates, 3000);

function fetchRouterStatus() {
    const rid = @json($routerId ?? null);
    fetch('{{ route("noc.internet.monitoring.router-status") }}' + (rid ? '?router_id=' + rid : ''))
        .then(r => r.json())
        .then(d => {
            if (d.cpu_load !== undefined && d.cpu_load !== null) {
                const cpuEl = document.getElementById('router-cpu');
                if (cpuEl) cpuEl.textContent = d.cpu_load + '%';
            }
            if (d.uptime !== undefined && d.uptime !== null) {
                const upEl = document.getElementById('router-uptime');
                if (upEl) upEl.textContent = d.uptime;
            }
            if (d.version !== undefined && d.version !== null) {
                const verEl = document.getElementById('router-version');
                if (verEl) verEl.textContent = d.version;
            }
        })
        .catch(() => {});
}

fetchRouterStatus();
setInterval(fetchRouterStatus, 3000);

function escHtml(s) {
    if (s == null) return '—';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function fetchActiveSessions() {
    const rid = @json($routerId ?? null);
    fetch('{{ route("noc.internet.monitoring.active-sessions") }}' + (rid ? '?router_id=' + rid : ''))
        .then(r => r.json())
        .then(d => {
            const ppp = d.ppp || [];
            const hs = d.hotspot || [];
            const pppCount = document.getElementById('ppp-active-count');
            const hsCount = document.getElementById('hotspot-active-count');
            if (pppCount) pppCount.textContent = d.ppp_count ?? ppp.length;
            if (hsCount) hsCount.textContent = d.hotspot_count ?? hs.length;

            const pppTbody = document.getElementById('ppp-active-tbody');
            if (pppTbody) {
                pppTbody.innerHTML = ppp.length
                    ? ppp.map(s => '<tr>' +
                        '<td class="fw-semibold">' + escHtml(s.user) + '</td>' +
                        '<td><span class="badge bg-light text-dark" style="font-size:0.68rem;">' + escHtml(s.service) + '</span></td>' +
                        '<td><code style="font-size:0.72rem;">' + escHtml(s.address) + '</code></td>' +
                        '<td style="font-size:0.74rem;">' + escHtml(s.caller) + '</td>' +
                        '<td class="text-end" style="font-size:0.76rem;">' + escHtml(s.uptime) + '</td>' +
                        '</tr>').join('')
                    : '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi PPP aktif</td></tr>';
            }

            const hsTbody = document.getElementById('hotspot-active-tbody');
            if (hsTbody) {
                hsTbody.innerHTML = hs.length
                    ? hs.map(s => '<tr>' +
                        '<td class="fw-semibold">' + escHtml(s.user) + '</td>' +
                        '<td style="font-size:0.74rem;">' + escHtml(s.server) + '</td>' +
                        '<td><code style="font-size:0.72rem;">' + escHtml(s.address) + '</code></td>' +
                        '<td style="font-size:0.74rem;">' + escHtml(s.mac) + '</td>' +
                        '<td class="text-end" style="font-size:0.76rem;">' + escHtml(s.uptime) + '</td>' +
                        '</tr>').join('')
                    : '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada sesi Hotspot aktif</td></tr>';
            }
        })
        .catch(() => {});
}

fetchActiveSessions();
setInterval(fetchActiveSessions, 3000);
</script>
@endpush

