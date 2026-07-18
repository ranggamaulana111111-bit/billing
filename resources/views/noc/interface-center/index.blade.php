@extends('layouts.app')

@section('title', 'Interface Center — Semua Interface')

@php
    function ifListFmtBytes($bytes) {
        if ($bytes == 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[$i];
    }
@endphp

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-network-wired me-2" style="color:var(--primary);"></i>Semua Interface</h2>
        <p class="section-subtitle mb-0 mt-1">{{ count($interfaces) }} interface dari seluruh router</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.interface-center.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
        </a>
        <button type="button" class="btn btn-outline-warning px-3 py-2" id="btnBulkAction" disabled>
            <i class="fa-solid fa-layer-group me-1"></i>Bulk Action
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Pencarian</label>
                <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Nama interface, router...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select id="filterRouter" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['routers'] as $r)
                        <option value="{{ $r->id }}">{{ $r->display_identity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Tipe</label>
                <select id="filterType" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(array_keys($interfaceTypes) as $type)
                        <option value="{{ $type }}">{{ $type }} ({{ $interfaceTypes[$type] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="up">Up</option>
                    <option value="down">Down</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Site</label>
                <select id="filterSite" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['sites'] as $site)
                        <option value="{{ $site }}">{{ $site }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Tag</label>
                <select id="filterTag" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions['tags'] as $tag)
                        <option value="{{ $tag }}">{{ $tag }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- ═══ BULK ACTION BAR ═══ --}}
<div class="card shadow-sm border-0 mb-3 d-none" id="bulkBar">
    <div class="card-body py-2 d-flex align-items-center gap-3">
        <span id="bulkCount" class="fw-semibold" style="font-size:0.85rem;">0 dipilih</span>
        <div class="vr"></div>
        <button class="btn btn-sm btn-outline-success" data-bulk="enable"><i class="fa-solid fa-toggle-on me-1"></i>Enable</button>
        <button class="btn btn-sm btn-outline-danger" data-bulk="disable"><i class="fa-solid fa-toggle-off me-1"></i>Disable</button>
        <button class="btn btn-sm btn-outline-info" data-bulk="set_tag"><i class="fa-solid fa-tag me-1"></i>Add Tag</button>
        <button class="btn btn-sm btn-outline-secondary" data-bulk="remove_tag"><i class="fa-solid fa-tags me-1"></i>Remove Tag</button>
        <button class="btn btn-sm btn-outline-primary" data-bulk="refresh"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
        <button class="btn btn-sm btn-outline-dark ms-auto" id="btnClearSelection"><i class="fa-solid fa-xmark me-1"></i>Clear</button>
    </div>
</div>

{{-- ═══ INTERFACE TABLE ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                        <th>Router</th>
                        <th>Nama</th>
                        <th>Alias</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>MTU</th>
                        <th>MAC</th>
                        <th class="text-end">RX</th>
                        <th class="text-end">TX</th>
                        <th class="text-end">RX Err</th>
                        <th class="text-end">TX Err</th>
                        <th>Tag</th>
                        <th style="width:80px;">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($interfaces as $iface)
                        <tr class="iface-row"
                            data-router-id="{{ $iface['router_id'] }}"
                            data-name="{{ strtolower($iface['name']) }}"
                            data-router="{{ strtolower($iface['router_name']) }}"
                            data-type="{{ strtolower($iface['type']) }}"
                            data-status="{{ $iface['disabled'] ? 'disabled' : ($iface['running'] ? 'up' : 'down') }}"
                            data-site="{{ strtolower($iface['site'] ?? '') }}"
                            data-tags="{{ strtolower(implode(' ', $iface['tags'] ?? [])) }}">
                            <td><input type="checkbox" class="iface-check" data-router="{{ $iface['router_id'] }}" data-name="{{ $iface['name'] }}"></td>
                            <td>
                                <a href="{{ route('noc.mikrotik.detail', $iface['router_id']) }}" class="text-decoration-none fw-semibold" style="font-size:0.78rem;">
                                    {{ $iface['router_name'] }}
                                </a>
                            </td>
                            <td class="fw-semibold">{{ $iface['name'] }}</td>
                            <td style="font-size:0.78rem;">{{ $iface['alias'] ?? '-' }}</td>
                            <td><span class="badge bg-secondary" style="font-size:0.68rem;">{{ $iface['type'] }}</span></td>
                            <td>
                                @if($iface['disabled'])
                                    <span class="badge" style="background:#64748b;color:#fff;font-size:0.6rem;">DISABLED</span>
                                @elseif($iface['running'])
                                    <span class="badge bg-success" style="font-size:0.6rem;">
                                        <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#fff;margin-right:3px;"></span>UP
                                    </span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.6rem;">DOWN</span>
                                @endif
                            </td>
                            <td style="font-size:0.78rem;">{{ $iface['mtu'] ?? '-' }}</td>
                            <td style="font-size:0.72rem;"><code>{{ $iface['mac_address'] ?? '-' }}</code></td>
                            <td class="text-end" style="font-size:0.78rem;">{{ ifListFmtBytes($iface['rx_byte']) }}</td>
                            <td class="text-end" style="font-size:0.78rem;">{{ ifListFmtBytes($iface['tx_byte']) }}</td>
                            <td class="text-end" style="font-size:0.78rem;">
                                <span class="{{ $iface['rx_error'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $iface['rx_error'] }}</span>
                            </td>
                            <td class="text-end" style="font-size:0.78rem;">
                                <span class="{{ $iface['tx_error'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $iface['tx_error'] }}</span>
                            </td>
                            <td>
                                @foreach(($iface['tags'] ?? []) as $tag)
                                    <span class="badge bg-info" style="font-size:0.6rem;">{{ $tag }}</span>
                                @endforeach
                            </td>
                            <td>
                                <a href="{{ route('noc.interface-center.detail', [$iface['router_id'], $iface['name']]) }}" class="btn btn-sm btn-outline-primary" style="font-size:0.7rem;" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-network-wired" style="font-size:2rem;"></i>
                                <p class="mt-2 mb-0">Tidak ada interface ditemukan</p>
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
(function() {
    // Filter logic
    function applyFilters() {
        const search = document.getElementById('filterSearch').value.toLowerCase();
        const router = document.getElementById('filterRouter').value;
        const type = document.getElementById('filterType').value.toLowerCase();
        const status = document.getElementById('filterStatus').value;
        const site = document.getElementById('filterSite').value.toLowerCase();
        const tag = document.getElementById('filterTag').value.toLowerCase();

        document.querySelectorAll('.iface-row').forEach(row => {
            let show = true;
            if (search && !row.dataset.name.includes(search) && !row.dataset.router.includes(search)) show = false;
            if (router && row.dataset.routerId !== router) show = false;
            if (type && row.dataset.type !== type) show = false;
            if (status && row.dataset.status !== status) show = false;
            if (site && !row.dataset.site.includes(site)) show = false;
            if (tag && !row.dataset.tags.includes(tag)) show = false;
            row.style.display = show ? '' : 'none';
        });
    }

    ['filterSearch', 'filterRouter', 'filterType', 'filterStatus', 'filterSite', 'filterTag'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', applyFilters);
    });

    // Bulk selection
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkBar');
    const bulkCount = document.getElementById('bulkCount');
    const checks = document.querySelectorAll('.iface-check');

    function updateBulk() {
        const selected = document.querySelectorAll('.iface-check:checked');
        const count = selected.length;
        bulkCount.textContent = count + ' dipilih';
        bulkBar.classList.toggle('d-none', count === 0);
        document.getElementById('btnBulkAction').disabled = count === 0;
    }

    selectAll?.addEventListener('change', function() {
        checks.forEach(c => {
            if (c.closest('tr').style.display !== 'none') c.checked = selectAll.checked;
        });
        updateBulk();
    });

    checks.forEach(c => c.addEventListener('change', updateBulk));

    document.getElementById('btnClearSelection')?.addEventListener('click', function() {
        checks.forEach(c => c.checked = false);
        selectAll.checked = false;
        updateBulk();
    });

    document.getElementById('btnBulkAction')?.addEventListener('click', function() {
        updateBulk();
    });

    // Bulk actions
    document.querySelectorAll('[data-bulk]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const action = this.dataset.bulk;
            const selected = [...document.querySelectorAll('.iface-check:checked')];
            if (selected.length === 0) return;

            const routerId = selected[0].dataset.router;
            const interfaces = selected.map(c => c.dataset.name);

            // Confirm
            const actionLabels = { enable: 'Enable', disable: 'Disable', set_tag: 'Tambah Tag', remove_tag: 'Hapus Tag', refresh: 'Refresh' };
            if (!confirm(actionLabels[action] + ' ' + interfaces.length + ' interface?')) return;

            let params = {};
            if (action === 'set_tag') {
                const tag = prompt('Masukkan tag (pisahkan koma untuk banyak tag):');
                if (!tag) return;
                params.tags = tag.split(',').map(t => t.trim()).filter(Boolean);
            } else if (action === 'remove_tag') {
                const tag = prompt('Masukkan tag yang ingin dihapus:');
                if (!tag) return;
                params.tags = tag.split(',').map(t => t.trim()).filter(Boolean);
            }

            try {
                const resp = await fetch('{{ route("noc.interface-center.bulk") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ router_id: routerId, interfaces, action, params })
                });
                const data = await resp.json();
                alert(data.message || 'Selesai');
                if (data.success) location.reload();
            } catch (e) {
                alert('Error: ' + e.message);
            }
        });
    });
})();
</script>
@endpush

