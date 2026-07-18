@extends('layouts.app')
@section('title', 'Smart QoS — Network Intelligence')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-shield-halved me-2" style="color:var(--primary);"></i>Smart QoS Anti-Bufferbloat</h2>
        <p class="section-subtitle mb-0 mt-1">CAKE algorithm — auto-provisioning per pelanggan — network intelligence dashboard</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <span id="qosLiveDot" class="d-none" style="width:8px;height:8px;border-radius:50%;background:#198754;display:inline-block;animation:qosPulse 1.5s infinite;"></span>
        <span id="qosPollLabel" class="text-muted" style="font-size:0.7rem;"></span>
        <form method="POST" action="{{ route('qos.sync-all') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-sync me-1"></i>Sync All</button>
        </form>
        <form method="POST" action="{{ route('qos.optimize-now') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-bolt me-1"></i>Optimize Now</button>
        </form>
    </div>
</div>
<style>
@keyframes qosPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.4)} }
.qos-flash { transition: background-color .4s ease, box-shadow .4s ease; }
.qos-flash-active { background-color:rgba(13,110,253,.08) !important; box-shadow:0 0 0 2px rgba(13,110,253,.12) !important; }
.qos-grade-badge { transition: background-color .4s ease; }
</style>
@if(!empty($optimizeResult))
<div class="alert alert-info alert-dismissible fade show" style="font-size:0.85rem;" role="alert">
    <i class="fa-solid fa-bolt me-1"></i>{{ $optimizeResult }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" style="font-size:0.85rem;" role="alert">
    <i class="fa-solid fa-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@php
    $gradeColorMap = ['A+'=>'#198754','A'=>'#198754','B'=>'#0d6efd','C'=>'#ffc107','D'=>'#dc3545','N/A'=>'#6c757d'];
@endphp
{{-- ═══ GLOBAL OVERVIEW ═══ --}}
<div class="row g-3 mb-5" id="qosGlobal">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3 qos-flash" id="qos-g-total">
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary);" data-qos="totalQueues">{{ $totalQueues }}</div>
            <div class="text-muted" style="font-size:0.78rem;">Total Simple Queues</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3 qos-flash" id="qos-g-smartqos">
            <div style="font-size:1.8rem;font-weight:700;color:#198754;" data-qos="totalSmartQos">{{ $totalSmartQos }}</div>
            <div class="text-muted" style="font-size:0.78rem;">SmartQos Managed</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3 qos-flash" id="qos-g-latency">
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary);" data-qos="avgLatency">{{ $avgLatency }}ms</div>
            <div class="text-muted" style="font-size:0.78rem;">Avg Latency</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3 qos-flash" id="qos-g-grade">
            <div style="font-size:1.8rem;font-weight:700;color:{{ $gradeColorMap[$overallGrade] ?? '#6c757d' }};" data-qos="overallGrade">{{ $overallGrade }}</div>
            <div class="text-muted" style="font-size:0.78rem;">Bufferbloat Grade</div>
        </div></div>
    </div>
</div>
{{-- ═══ ROUTER SELECTOR ═══ --}}
@if($routers->count() > 1)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Filter Router</label>
                <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Router</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ ($selectedRouterId ?? null) == $r->id ? 'selected' : '' }}>{{ $r->display_identity ?? $r->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif
{{-- ═══ PER-ROUTER ═══ --}}
<div id="qosRouters">
@foreach($routerStats as $stats)
@php $s = $stats['summary']; @endphp
<div class="card border-0 shadow-sm mb-4 qos-card" data-router-id="{{ $stats['router_id'] }}">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h5 class="mb-0"><i class="fa-solid fa-server me-2" style="color:var(--primary);"></i>{{ $stats['router_name'] }}</h5>
                <span class="badge qos-cake-badge {{ $stats['cake_active'] ? 'bg-success' : 'bg-danger' }}">
                    <i class="fa-solid fa-{{ $stats['cake_active'] ? 'check' : 'xmark' }} me-1"></i>{{ $stats['cake_active'] ? 'CAKE Active' : 'CAKE Missing' }}
                </span>
            </div>
            <span class="badge qos-grade-badge" style="background:{{ $gradeColorMap[$stats['grade']] ?? '#6c757d' }};color:#fff;font-size:0.85rem;padding:0.4em 0.8em;">
                <span data-qos="routerGrade">{{ $stats['grade'] }}</span> — <span data-qos="routerLatency">{{ $stats['latency_ms'] }}ms</span>
            </span>
        </div>
    </div>
    <div class="card-body">
        @if(isset($stats['error']))
        <div class="alert alert-danger py-2 mb-3 qos-error-msg" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $stats['error'] }}
        </div>
        @endif
        {{-- ═══ SUMMARY ═══ --}}
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3 qos-summary-item">
                <div class="p-2 rounded qos-flash" style="background:rgba(13,110,253,0.05);">
                    <div class="lbl">Simple Queues</div>
                    <div class="val" data-qos="rTotalQueues">{{ $s['total_simple_queues'] }}</div>
                    <div class="sub">SmartQos <span data-qos="rSmartQos">{{ $s['smartqos_queues'] }}</span> · Existing <span data-qos="rExisting">{{ $s['existing_queues'] }}</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3 qos-summary-item">
                <div class="p-2 rounded qos-flash" style="background:rgba(25,135,84,0.05);">
                    <div class="lbl">Queue Types</div>
                    <div class="val" data-qos="rQueueTypes">{{ count($stats['queue_types']) }}</div>
                    <div class="sub">CAKE <span data-qos="rCakeQueues">{{ $s['cake_queues'] }}</span> · PFIFO <span data-qos="rPfifoQueues">{{ $s['pfifo_queues'] }}</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3 qos-summary-item">
                <div class="p-2 rounded qos-flash" style="background:rgba(255,193,7,0.05);">
                    <div class="lbl">Queue Trees</div>
                    <div class="val" data-qos="rTotalTrees">{{ $s['total_trees'] }}</div>
                    <div class="sub">Cake <span data-qos="rCakeTrees">{{ $s['cake_trees'] }}</span> · PFIFO <span data-qos="rPfifoTrees">{{ $s['pfifo_trees'] }}</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3 qos-summary-item">
                <div class="p-2 rounded qos-flash" style="background:rgba(108,117,125,0.05);">
                    <div class="lbl">PPPoE Sessions</div>
                    <div class="val"><span data-qos="rPppActive">{{ $s['ppp_active'] }}</span> / <span data-qos="rPppTotal">{{ $s['ppp_total'] }}</span></div>
                    <div class="sub">online / total</div>
                </div>
            </div>
        </div>
        {{-- ═══ CAKE CONFIG ═══ --}}
        @if($stats['cake_type'])
        <div class="qos-section">
            <h6 class="fw-bold"><i class="fa-solid fa-gear me-2 text-success"></i>CAKE Queue Type Configuration</h6>
            <div class="qos-cake-grid">
                @foreach($stats['cake_type'] as $key => $val)
                <div class="item">
                    <div class="key">{{ $key }}</div>
                    <div class="val">{{ $val }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        {{-- ═══ ALL QUEUE TYPES ═══ --}}
        @if(count($stats['queue_types']) > 0)
        <div class="qos-section mt-4">
            <h6 class="fw-bold"><i class="fa-solid fa-layer-group me-2 text-primary"></i>All Queue Types on Router</h6>
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
<thead class="mon-thead">
    <tr><th>Name</th><th>Kind</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($stats['queue_types'] as $qt)
                        <tr>
                            <td class="qos-td-name"><code>{{ $qt['name'] }}</code></td>
                            <td class="qos-td-nowrap">{{ $qt['kind'] }}</td>
                            <td class="qos-td-nowrap">
                                @if($qt['name'] === 'cake-smartqos')
                                    <span class="badge bg-success">SmartQos</span>
                                @elseif($qt['kind'] === 'cake')
                                    <span class="badge bg-info">CAKE</span>
                                @elseif($qt['kind'] === 'pfifo')
                                    <span class="badge bg-secondary">PFIFO</span>
                                @else
                                    <span class="badge bg-light text-dark">{{ $qt['kind'] }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        {{-- ═══ SMARTQOS QUEUES ═══ --}}
        <div class="qos-section mt-4">
            <h6 class="fw-bold"><i class="fa-solid fa-robot me-2 text-success"></i>SmartQos Managed Queues <span class="badge bg-success ms-1" data-qos="rSmartqosBadge">{{ $s['smartqos_queues'] }}</span></h6>
            <div data-qos="smartqosQueuesTable">
            @if(!empty($stats['smartqos_queues']))
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th>Queue Name</th>
                            <th>Target</th>
                            <th>Max Limit</th>
                            <th>Type</th>
                            <th>Rate</th>
                            <th>Traffic</th>
                            <th class="d-none d-md-table-cell">Comment</th>
                            <th>Status</th>
                        </tr>

                    <tbody>
                        @foreach($stats['smartqos_queues'] as $q)
                        <tr>
                            <td class="qos-td-name"><code>{{ $q['name'] }}</code></td>
                            <td class="qos-td-nowrap">{{ $q['target'] }}</td>
                            <td class="qos-td-nowrap">{{ $q['max_limit'] }}</td>
                            <td><span class="badge bg-success-subtle text-success" style="font-size:0.68rem;">{{ $q['queue_type'] }}</span></td>
                            <td class="qos-td-nowrap">{{ $q['rate'] }}</td>
                            <td class="qos-td-nowrap">{{ number_format((int) ($q['bytes'] ?? 0)) }}</td>
                            <td class="d-none d-md-table-cell wrap">{{ $q['comment'] }}</td>
                            <td class="qos-td-nowrap">{!! $q['disabled'] ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">On</span>' !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-muted py-2" style="font-size:0.82rem;">
                <i class="fa-solid fa-info-circle me-1"></i>Belum ada SmartQos queue. Klik <strong>Sync All</strong> untuk provisioning otomatis.
            </div>
            @endif
            </div>
        </div>
        {{-- ═══ EXISTING QUEUES ═══ --}}
        <div class="qos-section mt-4" data-qos="existingSection">
        @if(!empty($stats['existing_queues']))
            <h6 class="fw-bold"><i class="fa-solid fa-list me-2 text-secondary"></i>Existing QoS Queues (non-SmartQos) <span class="badge bg-secondary ms-1">{{ $s['existing_queues'] }}</span></h6>
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th>Queue Name</th>
                            <th>Target</th>
                            <th>Max Limit</th>
                            <th class="d-none d-lg-table-cell">Min Limit</th>
                            <th>Type</th>
                            <th>Rate</th>
                            <th class="d-none d-md-table-cell">Bytes</th>
                            <th class="d-none d-md-table-cell">Comment</th>
                            <th>Status</th>
                        </tr>

                    <tbody>
                        @foreach($stats['existing_queues'] as $q)
                        <tr>
                            <td class="qos-td-name"><code>{{ $q['name'] }}</code></td>
                            <td class="qos-td-nowrap">{{ $q['target'] }}</td>
                            <td class="qos-td-nowrap">{{ $q['max_limit'] }}</td>
                            <td class="d-none d-lg-table-cell qos-td-nowrap">{{ $q['min_limit'] ?: '—' }}</td>
                            <td><span class="badge bg-light text-dark" style="font-size:0.68rem;">{{ $q['queue_type'] }}</span></td>
                            <td class="qos-td-nowrap">{{ $q['rate'] }}</td>
                            <td class="d-none d-md-table-cell qos-td-nowrap">{{ number_format((int) ($q['bytes'] ?? 0)) }}</td>
                            <td class="d-none d-md-table-cell wrap">{{ $q['comment'] }}</td>
                            <td class="qos-td-nowrap">{!! $q['disabled'] ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">On</span>' !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
        {{-- ═══ QUEUE TREES ═══ --}}
        <div class="qos-section mt-4" data-qos="treesSection">
        @if(!empty($stats['queue_trees']))
            <h6 class="fw-bold"><i class="fa-solid fa-sitemap me-2 text-warning"></i>Queue Trees <span class="badge bg-warning-subtle text-warning ms-1">{{ count($stats['queue_trees']) }}</span></h6>
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th>Name</th>
                            <th>Parent</th>
                            <th>Type</th>
                            <th class="d-none d-md-table-cell">Rate</th>
                            <th class="d-none d-md-table-cell">Max Limit</th>
                            <th>Bytes</th>
                            <th>Status</th>
                        </tr>

                    <tbody>
                        @foreach($stats['queue_trees'] as $tree)
                        <tr>
                            <td class="qos-td-name"><code>{{ $tree['name'] }}</code></td>
                            <td class="qos-td-nowrap">{{ $tree['parent'] }}</td>
                            <td><span class="badge bg-light text-dark" style="font-size:0.68rem;">{{ $tree['queue'] }}</span></td>
                            <td class="d-none d-md-table-cell qos-td-nowrap">{{ $tree['rate'] ?: '—' }}</td>
                            <td class="d-none d-md-table-cell qos-td-nowrap">{{ $tree['max_limit'] ?: '—' }}</td>
                            <td class="qos-td-nowrap">{{ number_format((int) ($tree['bytes'] ?? 0)) }}</td>
                            <td class="qos-td-nowrap">{!! $tree['disabled'] ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">On</span>' !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
        {{-- ═══ INTERFACES ═══ --}}
        <div class="qos-section mt-4" data-qos="interfacesSection">
        @if(!empty($stats['interfaces']))
            <h6 class="fw-bold"><i class="fa-solid fa-network-wired me-2 text-info"></i>Interface Traffic</h6>
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th>Interface</th>
                            <th>Type</th>
                            <th>Tx Rate</th>
                            <th>Rx Rate</th>
                            <th class="d-none d-md-table-cell">Tx Bytes</th>
                            <th class="d-none d-md-table-cell">Rx Bytes</th>
                            <th>Status</th>
                        </tr>

                    <tbody>
                        @foreach($stats['interfaces'] as $iface)
                        @if(!($iface['link_down'] ?? false))
                        <tr>
                            <td class="qos-td-name"><code>{{ $iface['name'] }}</code></td>
                            <td class="qos-td-nowrap">{{ $iface['type'] }}</td>
                            <td class="qos-td-nowrap">{{ $iface['tx_rate'] }}</td>
                            <td class="qos-td-nowrap">{{ $iface['rx_rate'] }}</td>
                            <td class="d-none d-md-table-cell qos-td-nowrap">{{ number_format((int) ($iface['tx_bytes'] ?? 0)) }}</td>
                            <td class="d-none d-md-table-cell qos-td-nowrap">{{ number_format((int) ($iface['rx_bytes'] ?? 0)) }}</td>
                            <td class="qos-td-nowrap">{!! ($iface['running'] ?? false) ? '<span class="badge bg-success">Up</span>' : '<span class="badge bg-secondary">Down</span>' !!}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </div>
</div>
@endforeach
</div>
@if(empty($routerStats))
<div class="card border-0 shadow-sm qos-empty-card" id="qosEmptyState">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-shield-halved fa-3x mb-3" style="color:rgba(0,0,0,0.1);"></i>
        <h5 class="text-muted">Belum ada data QoS</h5>
        <p class="text-muted mb-3" style="font-size:0.85rem;">
            @if($routers->isEmpty())
                Tidak ditemukan aktif PPPoE router. Pastikan router sudah dikonfigurasi dan status <strong>Active</strong>.
            @else
                Router ditemukan ({{ $routers->count() }}), tetapi gagal mengambil data dari MikroTik.
            @endif
        </p>
        @if($routers->isEmpty())
        <div class="text-start mx-auto" style="max-width:480px;font-size:0.82rem;">
            <div class="bg-light rounded p-3">
                <strong>Ceklist:</strong>
                <ol class="mb-0 mt-1 ps-3">
                    <li>Router sudah ditambahkan di <a href="{{ route('mikrotik.index') }}">MikroTik Center</a>?</li>
                    <li>Status router <strong>Active</strong>?</li>
                    <li>Tipe router <strong>PPPoE</strong> atau <strong>General</strong>?</li>
                    <li>SSH port / API port terkonfigurasi?</li>
                </ol>
            </div>
        </div>
        @else
        <div class="text-start mx-auto" style="max-width:480px;font-size:0.82rem;">
            <div class="bg-light rounded p-3">
                <strong>Router terdeteksi:</strong>
                @foreach($routers as $r)
                <div class="d-flex align-items-center gap-2 py-1">
                    <span class="badge bg-{{ $r->status === 'online' ? 'success' : 'danger' }}">{{ $r->status }}</span>
                    <span>{{ $r->display_identity ?? $r->name }}</span>
                    <span class="text-muted">({{ $r->host }})</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endif
@endsection
@push('scripts')
<script>
(function() {
    var GRADE_COLORS = {'A+':'#198754','A':'#198754','B':'#0d6efd','C':'#ffc107','D':'#dc3545','N/A':'#6c757d'};
    var POLL_URL = '{{ route("qos.health.json") }}';
    var INTERVAL = 10000;
    var timer = null;
    function setGradeColor(el, grade) {
        var c = GRADE_COLORS[grade] || '#6c757d';
        el.style.color = c;
        if (el.classList.contains('qos-grade-badge')) {
            el.style.background = c;
            el.style.color = '#fff';
        }
    }
    function flash(el) {
        el.classList.add('qos-flash-active');
        setTimeout(function(){ el.classList.remove('qos-flash-active'); }, 600);
    }
    function updateVal(el, val) {
        if (el.textContent !== String(val)) {
            el.textContent = val;
            flash(el.closest('.qos-flash') || el);
        }
    }
    function buildSmartQosTableHtml(queues) {
        if (!queues || queues.length === 0) {
            return '<div class="text-muted py-2" style="font-size:0.82rem;"><i class="fa-solid fa-info-circle me-1"></i>Belum ada SmartQos queue.</div>';
        }
        var h = '<div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
<thead class="mon-thead">
    <tr><th>Queue Name</th><th>Target</th><th>Max Limit</th><th>Type</th><th>Rate</th><th>Traffic</th><th class="d-none d-md-table-cell">Comment</th><th>Status</th></tr></thead><tbody>';
        for (var i = 0; i < queues.length; i++) {
            var q = queues[i];
            h += '<tr><td class="qos-td-name"><code>' + esc(q.name) + '</code></td><td class="qos-td-nowrap">' + esc(q.target) + '</td><td class="qos-td-nowrap">' + esc(q.max_limit) + '</td><td><span class="badge bg-success-subtle text-success" style="font-size:0.68rem;">' + esc(q.queue_type) + '</span></td><td class="qos-td-nowrap">' + esc(q.rate) + '</td><td class="qos-td-nowrap">' + Number(q.bytes||0).toLocaleString() + '</td><td class="d-none d-md-table-cell wrap">' + esc(q.comment) + '</td><td class="qos-td-nowrap">' + (q.disabled ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">On</span>') + '</td></tr>';
        }
        h += '</tbody></table></div>';
        return h;
    }
    function buildInterfacesTableHtml(ifaces) {
        if (!ifaces || ifaces.length === 0) return '';
        var h = '<h6 class="fw-bold"><i class="fa-solid fa-network-wired me-2 text-info"></i>Interface Traffic</h6><div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
<thead class="mon-thead">
    <tr><th>Interface</th><th>Type</th><th>Tx Rate</th><th>Rx Rate</th><th class="d-none d-md-table-cell">Tx Bytes</th><th class="d-none d-md-table-cell">Rx Bytes</th><th>Status</th></tr></thead><tbody>';
        for (var i = 0; i < ifaces.length; i++) {
            var f = ifaces[i];
            if (f.link_down) continue;
            h += '<tr><td class="qos-td-name"><code>' + esc(f.name) + '</code></td><td class="qos-td-nowrap">' + esc(f.type) + '</td><td class="qos-td-nowrap">' + esc(f.tx_rate) + '</td><td class="qos-td-nowrap">' + esc(f.rx_rate) + '</td><td class="d-none d-md-table-cell qos-td-nowrap">' + Number(f.tx_bytes||0).toLocaleString() + '</td><td class="d-none d-md-table-cell qos-td-nowrap">' + Number(f.rx_bytes||0).toLocaleString() + '</td><td class="qos-td-nowrap">' + (f.running ? '<span class="badge bg-success">Up</span>' : '<span class="badge bg-secondary">Down</span>') + '</td></tr>';
        }
        h += '</tbody></table></div>';
        return h;
    }
    function buildTreesTableHtml(trees) {
        if (!trees || trees.length === 0) return '';
        var h = '<h6 class="fw-bold"><i class="fa-solid fa-sitemap me-2 text-warning"></i>Queue Trees <span class="badge bg-warning-subtle text-warning ms-1">' + trees.length + '</span></h6><div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
<thead class="mon-thead">
    <tr><th>Name</th><th>Parent</th><th>Type</th><th class="d-none d-md-table-cell">Rate</th><th class="d-none d-md-table-cell">Max Limit</th><th>Bytes</th><th>Status</th></tr></thead><tbody>';
        for (var i = 0; i < trees.length; i++) {
            var t = trees[i];
            h += '<tr><td class="qos-td-name"><code>' + esc(t.name) + '</code></td><td class="qos-td-nowrap">' + esc(t.parent) + '</td><td><span class="badge bg-light text-dark" style="font-size:0.68rem;">' + esc(t.queue) + '</span></td><td class="d-none d-md-table-cell qos-td-nowrap">' + (t.rate||'—') + '</td><td class="d-none d-md-table-cell qos-td-nowrap">' + (t.max_limit||'—') + '</td><td class="qos-td-nowrap">' + Number(t.bytes||0).toLocaleString() + '</td><td class="qos-td-nowrap">' + (t.disabled ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">On</span>') + '</td></tr>';
        }
        h += '</tbody></table></div>';
        return h;
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    function updateRouter(data, idx) {
        var cards = document.querySelectorAll('#qosRouters .qos-card');
        var card = cards[idx];
        if (!card) return;
        var badge = card.querySelector('.qos-cake-badge');
        if (data.cake_active) {
            badge.className = 'badge bg-success qos-cake-badge';
            badge.innerHTML = '<i class="fa-solid fa-check me-1"></i>CAKE Active';
        } else {
            badge.className = 'badge bg-danger qos-cake-badge';
            badge.innerHTML = '<i class="fa-solid fa-xmark me-1"></i>CAKE Missing';
        }
        var gradeBadge = card.querySelector('.qos-grade-badge');
        setGradeColor(gradeBadge, data.grade);
        var gc = GRADE_COLORS[data.grade] || '#6c757d';
        gradeBadge.style.background = gc;
        updateVal(card.querySelector('[data-qos="routerGrade"]'), data.grade);
        updateVal(card.querySelector('[data-qos="routerLatency"]'), data.latency_ms + 'ms');
        var s = data.summary || {};
        updateVal(card.querySelector('[data-qos="rTotalQueues"]'), s.total_simple_queues || 0);
        updateVal(card.querySelector('[data-qos="rSmartQos"]'), s.smartqos_queues || 0);
        updateVal(card.querySelector('[data-qos="rExisting"]'), s.existing_queues || 0);
        updateVal(card.querySelector('[data-qos="rQueueTypes"]'), (data.queue_types || []).length);
        updateVal(card.querySelector('[data-qos="rCakeQueues"]'), s.cake_queues || 0);
        updateVal(card.querySelector('[data-qos="rPfifoQueues"]'), s.pfifo_queues || 0);
        updateVal(card.querySelector('[data-qos="rTotalTrees"]'), s.total_trees || 0);
        updateVal(card.querySelector('[data-qos="rCakeTrees"]'), s.cake_trees || 0);
        updateVal(card.querySelector('[data-qos="rPfifoTrees"]'), s.pfifo_trees || 0);
        updateVal(card.querySelector('[data-qos="rPppActive"]'), s.ppp_active || 0);
        updateVal(card.querySelector('[data-qos="rPppTotal"]'), s.ppp_total || 0);
        updateVal(card.querySelector('[data-qos="rSmartqosBadge"]'), s.smartqos_queues || 0);
        var sqContainer = card.querySelector('[data-qos="smartqosQueuesTable"]');
        if (sqContainer) sqContainer.innerHTML = buildSmartQosTableHtml(data.smartqos_queues);
        var intfContainer = card.querySelector('[data-qos="interfacesSection"]');
        if (intfContainer) intfContainer.innerHTML = buildInterfacesTableHtml(data.interfaces);
        var treesContainer = card.querySelector('[data-qos="treesSection"]');
        if (treesContainer) treesContainer.innerHTML = buildTreesTableHtml(data.queue_trees);
    }
    function poll() {
        fetch(POLL_URL, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var dot = document.getElementById('qosLiveDot');
            var label = document.getElementById('qosPollLabel');
            if (dot) dot.classList.remove('d-none');
            if (label) label.textContent = 'live';
            var stats = data.routerStats || [];
            updateVal(document.querySelector('[data-qos="totalQueues"]'), data.totalQueues || 0);
            updateVal(document.querySelector('[data-qos="totalSmartQos"]'), data.totalSmartQos || 0);
            updateVal(document.querySelector('[data-qos="avgLatency"]'), (data.avgLatency || 0) + 'ms');
            var gradeEl = document.querySelector('[data-qos="overallGrade"]');
            if (gradeEl) {
                updateVal(gradeEl, data.overallGrade || 'N/A');
                setGradeColor(gradeEl, data.overallGrade || 'N/A');
            }
            for (var i = 0; i < stats.length; i++) {
                updateRouter(stats[i], i);
            }
        })
        .catch(function() {
            var dot = document.getElementById('qosLiveDot');
            var label = document.getElementById('qosPollLabel');
            if (dot) dot.classList.add('d-none');
            if (label) label.textContent = 'offline';
        })
        .finally(function() {
            timer = setTimeout(poll, INTERVAL);
        });
    }
    poll();
})();
</script>
@endpush
