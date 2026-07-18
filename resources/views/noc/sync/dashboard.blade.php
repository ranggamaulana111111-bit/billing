@extends('layouts.app')

@section('title', 'Config Sync Dashboard — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-rotate me-2" style="color:var(--primary);"></i>Config Sync Dashboard</h2>
        <p class="section-subtitle mb-0 mt-1">
            RouterOS Configuration Synchronization Engine
            <span class="badge bg-success ms-2" style="font-size:0.65rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;animation:pulse 1.5s infinite;"></span>LIVE
            </span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 align-items-center">
        <div class="d-flex align-items-center gap-2 me-2" style="font-size:0.8rem;">
            <span class="text-muted">Auto Refresh:</span>
            <select id="autoRefreshSelect" class="form-select form-select-sm" style="width:auto;">
                <option value="0">Manual</option>
                <option value="10" selected>10 detik</option>
                <option value="30">30 detik</option>
                <option value="60">1 menit</option>
            </select>
            <span id="refreshCountdown" class="text-muted" style="min-width:40px;"></span>
        </div>
        <button type="button" class="btn btn-outline-primary px-3 py-2" id="btnRefresh" title="Refresh Sekarang">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </button>
        <form action="{{ route('noc.sync.sync-all') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary px-3 py-2" onclick="return confirm('Sync semua router aktif sekarang?')">
                <i class="fa-solid fa-rotate me-1"></i>Sync All Now
            </button>
        </form>
        <a href="{{ route('noc.sync.logs') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-clock-rotate-left me-1"></i>Logs
        </a>
    </div>
</div>

{{-- ═══ STAT CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(13,202,240,0.1);">
                        <i class="fa-solid fa-database fa-lg" style="color:#0dcaf0;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Total Synced Items</div>
                        <h4 class="mb-0 fw-bold" id="statTotalSynced">{{ number_format($summary['total_synced_items']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(255,193,7,0.1);">
                        <i class="fa-solid fa-triangle-exclamation fa-lg" style="color:#ffc107;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Konflik</div>
                        <h4 class="mb-0 fw-bold" id="statConflicts">{{ $summary['conflict_count'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(220,53,69,0.1);">
                        <i class="fa-solid fa-xmark-circle fa-lg" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Gagal (24 jam)</div>
                        <h4 class="mb-0 fw-bold" id="statFailures">{{ $summary['recent_failures_24h'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;background:rgba(25,135,84,0.1);">
                        <i class="fa-solid fa-hand-pointer fa-lg" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.78rem;">Manual Sync (24 jam)</div>
                        <h4 class="mb-0 fw-bold" id="statManual">{{ $summary['recent_manual_syncs_24h'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ROUTER SYNC STATUS ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-server me-2"></i>Router Sync Status</h6>
        <small class="text-muted">Last 10 sync operations</small>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.78rem;">Router</th>
                        <th style="font-size:0.78rem;">Host</th>
                        <th style="font-size:0.78rem;">Status</th>
                        <th style="font-size:0.78rem;">Last Sync</th>
                        <th style="font-size:0.78rem;">Ago</th>
                        <th style="font-size:0.78rem;" class="text-end">Action</th>
                    </tr>

                <tbody>
                    @forelse($summary['router_statuses'] as $rs)
                    <tr>
                        <td>
                            <span class="fw-semibold" style="font-size:0.85rem;">{{ $rs['router']->display_identity }}</span>
                            @if($rs['router']->site)
                                <br><small class="text-muted">{{ $rs['router']->site }}</small>
                            @endif
                        </td>
                        <td><code style="font-size:0.78rem;">{{ $rs['router']->host }}</code></td>
                        <td>
                            @if($rs['last_sync_status'] === 'success')
                                <span class="badge bg-success">Synced</span>
                            @elseif($rs['last_sync_status'] === 'partial')
                                <span class="badge bg-warning">Partial</span>
                            @elseif($rs['last_sync_status'] === 'failed')
                                <span class="badge bg-danger">Failed</span>
                            @else
                                <span class="badge bg-secondary">Never</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">
                            @if($rs['last_sync'])
                                {{ $rs['last_sync']->started_at->format('d M H:i:s') }}
                                <br><small class="text-muted">{{ $rs['last_sync']->duration_human }} | {{ $rs['last_sync']->total_items }} items</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">{{ $rs['last_sync_ago'] }}</td>
                        <td class="text-end">
                            <form action="{{ route('noc.sync.sync-now') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="router_id" value="{{ $rs['router']->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Sync Now">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-server fa-2x mb-2 d-block opacity-25"></i>
                            Belum ada router aktif. Tambahkan router di Device Manager.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ RECENT SYNC LOGS ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Sync Logs</h6>
        <a href="{{ route('noc.sync.logs') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.78rem;">Time</th>
                        <th style="font-size:0.78rem;">Router</th>
                        <th style="font-size:0.78rem;">Type</th>
                        <th style="font-size:0.78rem;">Status</th>
                        <th style="font-size:0.78rem;">Items</th>
                        <th style="font-size:0.78rem;">New</th>
                        <th style="font-size:0.78rem;">Updated</th>
                        <th style="font-size:0.78rem;">Deleted</th>
                        <th style="font-size:0.78rem;">Duration</th>
                    </tr>

                <tbody>
                    @forelse($recentLogs as $log)
                    <tr>
                        <td style="font-size:0.8rem;">{{ $log->started_at->format('d M H:i:s') }}</td>
                        <td style="font-size:0.85rem;">{{ $log->router->display_identity ?? '—' }}</td>
                        <td>
                            @if($log->sync_type === 'manual')
                                <span class="badge bg-info">Manual</span>
                            @else
                                <span class="badge bg-secondary">Scheduled</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $log->status_badge_color }}">{{ ucfirst($log->status) }}</span>
                        </td>
                        <td style="font-size:0.8rem;">{{ $log->total_items }}</td>
                        <td style="font-size:0.8rem;">
                            @if($log->new_items > 0)
                                <span class="text-success fw-semibold">+{{ $log->new_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">
                            @if($log->updated_items > 0)
                                <span class="text-warning fw-semibold">~{{ $log->updated_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">
                            @if($log->deleted_items > 0)
                                <span class="text-danger fw-semibold">-{{ $log->deleted_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">{{ $log->duration_human }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 d-block opacity-25"></i>
                            Belum ada riwayat sinkronisasi.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══ SUPPORTED MODULES ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-puzzle-piece me-2"></i>Supported Modules</h6>
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($modules as $key => $module)
            <div class="col-md-3 col-sm-4 col-6">
                <div class="d-flex align-items-center p-2 rounded" style="background:rgba(var(--bs-primary-rgb),0.04);font-size:0.82rem;">
                    <i class="fa-solid fa-cube me-2 text-primary"></i>
                    <span>{{ $module['label'] }}</span>
                    @if($module['enabled'])
                        <i class="fa-solid fa-check-circle ms-auto text-success" style="font-size:0.7rem;"></i>
                    @else
                        <i class="fa-solid fa-minus-circle ms-auto text-muted" style="font-size:0.7rem;"></i>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    let refreshInterval = null;
    let countdown = 0;

    const select = document.getElementById('autoRefreshSelect');
    const countdownEl = document.getElementById('refreshCountdown');
    const refreshBtn = document.getElementById('btnRefresh');

    function fetchLiveData() {
        fetch('{{ route("noc.sync.live-api") }}')
            .then(r => r.json())
            .then(data => {
                if (data.total_synced_items !== undefined) {
                    document.getElementById('statTotalSynced').textContent = Number(data.total_synced_items).toLocaleString();
                }
                if (data.conflict_count !== undefined) {
                    document.getElementById('statConflicts').textContent = data.conflict_count;
                }
                if (data.recent_failures_24h !== undefined) {
                    document.getElementById('statFailures').textContent = data.recent_failures_24h;
                }
            })
            .catch(() => {});
    }

    function startAutoRefresh(seconds) {
        stopAutoRefresh();
        if (seconds <= 0) {
            countdownEl.textContent = '';
            return;
        }
        countdown = seconds;
        countdownEl.textContent = countdown + 's';
        refreshInterval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown + 's';
            if (countdown <= 0) {
                fetchLiveData();
                countdown = seconds;
            }
        }, 1000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    select.addEventListener('change', () => startAutoRefresh(parseInt(select.value)));
    refreshBtn.addEventListener('click', () => { fetchLiveData(); startAutoRefresh(parseInt(select.value)); });

    startAutoRefresh(parseInt(select.value));
})();
</script>
@endpush

