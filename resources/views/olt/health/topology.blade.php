@extends('layouts.app')

@section('title', 'Topologi Jaringan')

@section('content')
<style>
    .topo-page { background: #f0f2f5; min-height: 100vh; }
    .topo-header { background: linear-gradient(135deg, #1a1f36 0%, #2d3561 100%); border-radius: 16px; padding: 28px 32px; margin-bottom: 20px; }
    .topo-header h2 { color: #fff; font-weight: 700; font-size: 1.25rem; margin: 0; }
    .topo-header p { color: rgba(255,255,255,.6); font-size: .85rem; margin: 4px 0 0; }
    .topo-stats { display: flex; gap: 16px; margin-top: 16px; }
    .topo-stat { background: rgba(255,255,255,.08); border-radius: 10px; padding: 10px 16px; display: flex; align-items: center; gap: 10px; }
    .topo-stat .stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .topo-stat .stat-val { font-size: 1.1rem; font-weight: 700; color: #fff; }
    .topo-stat .stat-label { font-size: .7rem; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .5px; }

    .topo-main-card { border: none; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); background: #fff; overflow: hidden; }
    .topo-toolbar { padding: 16px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
    .topo-toolbar .btn { border-radius: 8px; font-size: .8rem; font-weight: 500; }

    .topo-canvas { padding: 20px 16px; overflow-x: auto; overflow-y: visible; min-height: 420px; position: relative; }

    /* Tree levels */
    .topo-level { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-bottom: 0; position: relative; }
    .topo-level + .topo-connector { display: flex; justify-content: center; padding: 5px 0; }
    .topo-level + .topo-connector .conn-line { width: 2px; height: 18px; background: linear-gradient(180deg, #d0d5dd 0%, #e4e7ec 100%); }

    /* Node cards */
    .topo-node { position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform .2s ease; }
    .topo-node:hover { transform: translateY(-2px); }
    .topo-node .node-card {
        background: #fff; border: 1px solid #e4e7ec; border-radius: 10px;
        padding: 8px 10px; text-align: center; min-width: 84px; max-width: 120px;
        transition: all .2s ease; position: relative;
    }
    .topo-node:hover .node-card { border-color: #98a2b3; box-shadow: 0 3px 8px rgba(0,0,0,.08); }
    .topo-node .node-icon {
        width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 6px; font-size: 12px; color: #fff; font-weight: 600;
    }
    .topo-node .node-name { font-size: .68rem; font-weight: 700; color: #1a1f36; line-height: 1.25; }
    .topo-node .node-detail { font-size: .6rem; color: #667085; margin-top: 2px; line-height: 1.25; }
    .topo-node .node-status {
        position: absolute; top: -3px; right: -3px; width: 9px; height: 9px;
        border-radius: 50%; border: 2px solid #fff;
    }
    .topo-node .node-status.st-online { background: #12b76a; }
    .topo-node .node-status.st-warning { background: #f79009; }
    .topo-node .node-status.st-offline { background: #f04438; }
    .topo-node .node-status.st-unknown { background: #667085; }

    /* ONU grid under PON */
    .topo-pon-group { display: flex; flex-direction: column; align-items: center; }
    .topo-pon-group .pon-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
    .topo-pon-group .pon-badge { font-size: .58rem; background: #eff8ff; color: #175cd3; border: 1px solid #b2ddff; border-radius: 5px; padding: 1px 6px; font-weight: 600; }

    /* Fan-out connector */
    .topo-fan { display: flex; flex-direction: column; align-items: center; position: relative; padding: 6px 0; }
    .topo-fan::before {
        content: ''; position: absolute; top: 0; left: 50%; width: 2px; height: 10px;
        background: linear-gradient(180deg, #d0d5dd, #e4e7ec);
    }
    .topo-fan .fan-bar {
        position: absolute; top: 10px; height: 2px; background: #e4e7ec;
    }
    .topo-fan .fan-branch {
        position: absolute; width: 2px; background: linear-gradient(180deg, #e4e7ec, #d0d5dd);
    }

    /* ONU compact row */
    .topo-onu-row { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; padding: 3px 0 6px; }
    .topo-onu-chip {
        display: inline-flex; align-items: center; gap: 5px;
        background: #fff; border: 1px solid #e4e7ec; border-radius: 7px;
        padding: 4px 7px; font-size: .62rem; transition: all .15s ease; cursor: pointer;
    }
    .topo-onu-chip:hover { border-color: #98a2b3; box-shadow: 0 2px 5px rgba(0,0,0,.06); }
    .topo-onu-chip .chip-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .topo-onu-chip .chip-name { font-weight: 600; color: #344054; white-space: nowrap; }
    .topo-onu-chip .chip-detail { color: #667085; font-size: .58rem; }

    /* Legend */
    .topo-legend { padding: 16px 24px; border-top: 1px solid #f0f0f0; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
    .topo-legend-item { display: flex; align-items: center; gap: 6px; }
    .topo-legend-dot { width: 10px; height: 10px; border-radius: 3px; }
    .topo-legend-label { font-size: .73rem; color: #667085; font-weight: 500; }

    /* Summary row */
    .topo-summary { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
    .topo-summary-chip { display: flex; align-items: center; gap: 6px; background: #f9fafb; border: 1px solid #eaecf0; border-radius: 8px; padding: 6px 12px; }
    .topo-summary-chip .sum-dot { width: 8px; height: 8px; border-radius: 50%; }
    .topo-summary-chip .sum-text { font-size: .72rem; color: #344054; }
    .topo-summary-chip .sum-text strong { font-weight: 700; }

    @media (max-width: 768px) {
        .topo-level { flex-wrap: wrap; gap: 8px; }
        .topo-node .node-card { min-width: 74px; padding: 7px 9px; }
        .topo-node .node-icon { width: 24px; height: 24px; font-size: 11px; }
        .topo-stats { flex-wrap: wrap; gap: 8px; }
    }
</style>

<div class="topo-page px-3 py-3">
    {{-- Header --}}
    <div class="topo-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2><i class="fa-solid fa-diagram-project me-2" style="opacity:.7"></i>Topologi Jaringan</h2>
                <p>Visualisasi infrastruktur fiber dari OLT sampai pelanggan</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('onu-health.dashboard') }}" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border:none">
                    <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
                </a>
                <button class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border:none" onclick="location.reload()">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="topo-stats" id="topoStats"></div>
    </div>

    {{-- Main --}}
    <div class="topo-main-card">
        <div class="topo-canvas" id="topoCanvas"></div>
        <div class="topo-legend" id="topoLegend"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const topologyData = @json($topology);
const nodeConfig = {
    internet: { color: '#444ce7', icon: 'fa-solid fa-globe',        bg: '#eeecfd' },
    router:   { color: '#6938ef', icon: 'fa-solid fa-network-wired', bg: '#f4ebff' },
    olt:      { color: '#12b76a', icon: 'fa-solid fa-server',        bg: '#dcfce7' },
    pon:      { color: '#0ea5e9', icon: 'fa-solid fa-ethernet',      bg: '#e0f2fe' },
    odc:      { color: '#f97316', icon: 'fa-solid fa-plug',          bg: '#fff7ed' },
    odp:      { color: '#eab308', icon: 'fa-solid fa-ethernet',      bg: '#fefce8' },
    onu:      { color: '#667085', icon: 'fa-solid fa-tower-broadcast', bg: '#f9fafb' },
    customer: { color: '#0d9488', icon: 'fa-solid fa-user',          bg: '#f0fdfa' },
};
const statusClass = { online: 'st-online', warning: 'st-warning', offline: 'st-offline' };
const statusColor = { online: '#12b76a', warning: '#f79009', offline: '#f04438', unknown: '#667085' };

document.addEventListener('DOMContentLoaded', () => {
    buildTopology();
    buildLegend();
});

function buildTopology() {
    const canvas = document.getElementById('topoCanvas');
    if (!canvas) return;
    const nodes = topologyData.nodes || [];
    const edges = topologyData.edges || [];
    if (!nodes.length) {
        canvas.innerHTML = '<div class="text-center py-5 text-muted"><i class="fa-solid fa-diagram-project fa-3x mb-3 d-block" style="opacity:.2"></i>Belum ada data topologi</div>';
        return;
    }

    // Group by type
    const groups = {};
    nodes.forEach(n => { (groups[n.type] = groups[n.type] || []).push(n); });

    // Summary stats
    let totalOnu = (groups.onu || []).length;
    let onlineOnu = (groups.onu || []).filter(n => n.status === 'online').length;
    let totalOlt = (groups.olt || []).length;
    let totalPon = (groups.pon || []).length;
    let totalOdc = (groups.odc || []).length;
    let totalOdp = (groups.odp || []).length;
    document.getElementById('topoStats').innerHTML = [
        statItem(totalOlt, 'OLT', '#12b76a'),
        statItem(totalOdc, 'ODC', '#f97316'),
        statItem(totalOdp, 'ODP', '#eab308'),
        statItem(totalPon, 'PON Port', '#0ea5e9'),
        statItem(totalOnu, 'Total ONU', '#667085'),
        statItem(onlineOnu, 'Online', '#12b76a'),
    ].join('');

    // Build HTML tree
    let html = '';

    // Level 0: Internet
    html += levelRow('internet');
    html += connector();

    // Level 1: Core Router
    html += levelRow('router');
    html += connector();

    // Level 2: OLT(s)
    html += levelRow('olt');
    html += connector();

    // Level 3: ODC
    html += '<div class="topo-level" style="gap:8px">';
    (groups.odc || []).forEach(odc => { html += nodeCard(odc); });
    html += '</div>';
    html += connector();

    // Level 4: ODP
    html += '<div class="topo-level" style="gap:8px">';
    (groups.odp || []).forEach(odp => { html += nodeCard(odp); });
    html += '</div>';
    html += connector();

    // Level 5: PON Ports (grouped under OLT)
    html += '<div class="topo-level" style="gap:8px">';
    (groups.pon || []).forEach(pon => {
        html += nodeCard(pon);
    });
    html += '</div>';
    html += connector();

    // Level 4: ONU grouped by PON port
    const onuByPon = {};
    (groups.onu || []).forEach(onu => {
        const edge = edges.find(e => e.to === 'onu-' + onu.id);
        const fromPon = edge ? edge.from : null;
        (onuByPon[fromPon] = onuByPon[fromPon] || []).push(onu);
    });

    html += '<div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center">';
    Object.keys(onuByPon).sort().forEach(ponId => {
        const onus = onuByPon[ponId];
        const ponNode = nodes.find(n => n.id === ponId);
        const ponLabel = ponNode ? ponNode.label : ponId;

        html += `<div class="topo-pon-group">`;
        html += `<div class="pon-header"><span class="pon-badge">${escapeHtml(ponLabel)}</span><span style="font-size:.65rem;color:#667085">${onus.length} ONU</span></div>`;
        html += `<div style="width:2px;height:8px;background:#e4e7ec"></div>`;
        html += `<div class="topo-onu-row">`;
        onus.forEach(onu => {
            const sc = statusColor[onu.status] || statusColor.unknown;
            const custLink = onu.detail && onu.detail !== 'Unlinked' ? escapeHtml(onu.detail) : '';
            const href = onu.onu_id ? `{{ url('onu-health') }}/${onu.onu_id}` : '#';
            const rx = onu.rx_power !== null && onu.rx_power !== undefined ? onu.rx_power + ' dBm' : '';
            html += `<a href="${href}" ${onu.onu_id ? 'target="_blank"' : ''} class="topo-onu-chip" style="text-decoration:none">`;
            html += `<span class="chip-dot" style="background:${sc}"></span>`;
            html += `<span>`;
            html += `<span class="chip-name">${escapeHtml(onu.label)}</span>`;
            if (custLink) html += `<br><span class="chip-detail">${custLink}</span>`;
            if (rx) html += `<br><span class="chip-detail" style="color:#667085">${rx}</span>`;
            html += `</span></a>`;
        });
        html += `</div></div>`;
    });
    html += '</div>';

    canvas.innerHTML = html;
}

function levelRow(type) {
    const groups = {};
    (topologyData.nodes || []).filter(n => n.type === type).forEach(n => { (groups[n.type] = groups[n.type] || []).push(n); });
    const items = groups[type] || [];
    if (!items.length) return '';
    let html = '<div class="topo-level">';
    items.forEach(n => { html += nodeCard(n); });
    html += '</div>';
    return html;
}

function nodeCard(node) {
    const cfg = nodeConfig[node.type] || nodeConfig.onu;
    const sc = statusClass[node.status] || 'st-unknown';
    const href = node.onu_id ? `{{ url('onu-health') }}/${node.onu_id}` : 'javascript:void(0)';
    const detail = node.detail ? `<div class="node-detail">${escapeHtml(node.detail)}</div>` : '';
    const label = node.label.length > 22 ? node.label.substring(0, 20) + '...' : node.label;

    return `<a href="${href}" ${node.onu_id ? 'target="_blank"' : ''} class="topo-node" style="text-decoration:none">
        <div class="node-card">
            <span class="node-status ${sc}"></span>
            <div class="node-icon" style="background:${cfg.color}"><i class="${cfg.icon}"></i></div>
            <div class="node-name">${escapeHtml(label)}</div>
            ${detail}
        </div>
    </a>`;
}

function connector() {
    return '<div class="topo-connector"><div class="conn-line"></div></div>';
}

function statItem(val, label, color) {
    return `<div class="topo-stat">
        <div class="stat-icon" style="background:${color}18;color:${color}"><i class="fa-solid fa-circle" style="font-size:10px"></i></div>
        <div><div class="stat-val">${val}</div><div class="stat-label">${label}</div></div>
    </div>`;
}

function buildLegend() {
    const items = [
        ['Internet', '#444ce7'], ['Router', '#6938ef'], ['OLT', '#12b76a'],
        ['PON', '#0ea5e9'], ['ODC', '#f97316'], ['ODP', '#eab308'],
        ['ONU', '#667085'], ['Pelanggan', '#0d9488'],
    ];
    const statusItems = [
        ['Online', '#12b76a'], ['Warning', '#f79009'], ['Offline', '#f04438'],
    ];
    let html = '';
    items.forEach(([label, color]) => {
        html += `<div class="topo-legend-item"><div class="topo-legend-dot" style="background:${color}"></div><span class="topo-legend-label">${label}</span></div>`;
    });
    html += '<div style="width:1px;height:20px;background:#e4e7ec;margin:0 4px"></div>';
    statusItems.forEach(([label, color]) => {
        html += `<div class="topo-legend-item"><div style="width:16px;height:3px;border-radius:2px;background:${color}"></div><span class="topo-legend-label">${label}</span></div>`;
    });
    document.getElementById('topoLegend').innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
@endpush
