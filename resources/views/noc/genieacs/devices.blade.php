@extends('layouts.app')

@section('title', 'GenieACS Device — NOC')

@push('styles')
<style>
    #content { padding: 12px 16px 24px; max-width: none; }
    .top-navbar { padding: 6px 16px; }
    .top-navbar-avatar { width: 26px; height: 26px; font-size: 11px; border-radius: 7px; }
    .top-navbar-user { gap: 7px; }
    .top-navbar-name { font-size: 11.5px; }
    .top-navbar-role { font-size: 9px; }
    .top-navbar-logout { padding: 4px 8px; font-size: 12px; border-radius: 7px; }
    .page-header { margin-bottom: 10px; }
    .page-header h2 { font-size: 1.05rem; }
    .section-subtitle { font-size: 0.75rem; }
    .acs-nav-btn { padding: 5px 14px; font-size: 0.75rem; border-radius: 8px; font-weight: 600; }
    .acs-table { width: 100%; table-layout: fixed; min-width: 0; font-size: 0.78rem; }
    .acs-table thead th {
        background: rgba(245,158,11,0.10); color: #d97706; font-size: 0.72rem;
        font-weight: 700; letter-spacing: 0.02em; white-space: nowrap; height: 36px;
        padding: 0 12px !important; vertical-align: middle;
        border-bottom: 1px solid var(--bs-border-color);
        overflow: hidden; text-overflow: ellipsis;
    }
    .acs-table tbody td {
        vertical-align: middle; white-space: nowrap; height: 37px;
        padding: 0 12px !important; overflow: hidden; text-overflow: ellipsis;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .acs-table th:nth-child(1), .acs-table td:nth-child(1) { width: 15%; }
    .acs-table th:nth-child(2), .acs-table td:nth-child(2) { width: 17%; }
    .acs-table th:nth-child(3), .acs-table td:nth-child(3) { width: 11%; }
    .acs-table th:nth-child(4), .acs-table td:nth-child(4) { width: 9%; }
    .acs-table th:nth-child(5), .acs-table td:nth-child(5) { width: 14%; }
    .acs-table th:nth-child(6), .acs-table td:nth-child(6) { width: 16%; }
    .acs-table th:nth-child(7), .acs-table td:nth-child(7) { width: 18%; }
    .acs-table tbody tr { transition: background .15s; }
    .acs-aksi-head {
        height: 36px; display: flex; align-items: center; gap: 6px; padding: 0 12px;
        background: rgba(245,158,11,0.10); color: #d97706;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em; white-space: nowrap;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .acs-aksi-row {
        height: 37px; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 0 10px;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .acs-btn-xs { padding: 2px 10px; font-size: 0.68rem; line-height: 1.4; border-radius: 6px; white-space: nowrap; }
    .acs-aksi-row .btn-warning.acs-btn-xs:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(245,158,11,0.35);
    }
    .acs-monospace { font-family: var(--font-mono); font-size: 0.72rem; }
    .acs-ip-link { color: var(--primary); text-decoration: none; }
    .acs-ip-link:hover { text-decoration: underline; }
    .acs-card { transition: none !important; }
    .acs-card:hover {
        transform: none !important;
        box-shadow: var(--shadow-card) !important;
        border-color: var(--border-light) !important;
        background: linear-gradient(135deg, #ffffff, #fafcff) !important;
        backdrop-filter: blur(4px) !important;
    }
    .acs-card::before, .acs-card:hover::before { opacity: 0 !important; }
    /* Wrapper scroll horizontal - Aksi tetap sejajar */
    .acs-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
    .acs-table-wrap::-webkit-scrollbar { height: 6px; }
    .acs-table-wrap::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 3px; }
    .acs-table { width: 100%; table-layout: auto; min-width: 720px; font-size: 0.78rem; white-space: nowrap; }
    .acs-table thead th, .acs-table tbody td { white-space: nowrap; }
    .acs-table .col-aksi { min-width: 150px; background: #fff; }
    .acs-layout { display: flex; align-items: stretch; }
    .acs-layout .acs-main { flex: 1 1 auto; min-width: 0; }
    .acs-layout .acs-side { flex: 0 0 auto; border-left: 1px solid var(--bs-border-color); }
    @media (max-width: 991.98px) {
        .acs-layout { flex-direction: column; }
        .acs-layout .acs-side { border-left: none; border-top: 1px solid var(--bs-border-color); }
    }
    @media (max-width: 575.98px) {
        .acs-table { font-size: 0.72rem; min-width: 620px; }
        .acs-table thead th, .acs-table tbody td { padding: 0 8px !important; height: 32px; }
        .page-header h2 { font-size: 0.95rem; }
        .acs-nav-btn { padding: 4px 10px; font-size: 0.7rem; }
        .acs-card { border-radius: 12px !important; }
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="flex-grow-1" style="min-width:200px;">
        <h2 class="mb-0"><i class="fa-solid fa-hard-drive me-2" style="color:var(--primary);"></i>ACS Config &amp; Monitoring</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $total }} perangkat terdaftar di GenieACS</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex flex-wrap gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-outline-secondary acs-nav-btn"><i class="fa-solid fa-gauge-high me-1"></i>Overview</a>
        <a href="{{ route('noc.genieacs.devices') }}" class="btn btn-primary shadow-sm acs-nav-btn"><i class="fa-solid fa-hard-drive me-1"></i>Device</a>
        <a href="{{ route('noc.genieacs.settings') }}" class="btn btn-outline-secondary acs-nav-btn"><i class="fa-solid fa-gear me-1"></i>Settings</a>
    </div>
</div>

@if(! $connected)
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>
        <strong>GenieACS unreachable.</strong> {{ $error }}
    </div>
</div>
@endif

{{-- ═══ DEVICE LIST ═══ --}}
@if(empty($rows))
<div class="card shadow-sm border-0" style="border-radius:16px;">
    <div class="card-body p-0">
        <div class="text-center py-5">
            <i class="fa-solid fa-satellite-dish fa-3x mb-3" style="color:rgba(255,255,255,0.1);"></i>
            <h5 class="text-muted">Tidak ada device ditemukan</h5>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Pastikan GenieACS server aktif dan konfigurasi benar.</p>
        </div>
    </div>
</div>
@else
<div class="card shadow-sm border-0 acs-card overflow-hidden" style="border-radius:16px;">
    <div class="card-body p-0">
        <!-- Satu tabel utuh + wrapper scroll - Aksi tetap sejajar -->
        <div class="acs-table-wrap">
            <table class="table table-hover align-middle mb-0 acs-table">
                <thead>
                    <tr>
                        <th class="ps-3" style="min-width:140px;">SN</th>
                        <th style="min-width:130px;">MAC</th>
                        <th style="min-width:80px;">Type</th>
                        <th style="min-width:90px;">Mode</th>
                        <th style="min-width:120px;">IP PPPoE</th>
                        <th style="min-width:120px;">IP WAN/TR069</th>
                        <th style="min-width:120px;">SSID</th>
                        <th class="text-center col-aksi" style="min-width:150px;"><i class="fa-solid fa-bolt me-1"></i>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $deviceId = $row['device_id']; $label = $row['serial'] ?? $deviceId; @endphp
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('noc.genieacs.device-detail', $deviceId) }}" class="text-decoration-none fw-semibold acs-monospace" style="color:var(--primary);" title="{{ $row['serial'] ?? '' }}">
                                {{ Str::limit($row['serial'] ?? 'Belum diisi', 24) }}
                            </a>
                        </td>
                        <td class="acs-monospace">{{ Str::limit($row['mac'] ?? 'Belum diisi', 20) }}</td>
                        <td>{{ $row['manufacturer'] ?: 'Belum diisi' }}</td>
                        <td>
                            @if(filled($row['access_type'] ?? null))
                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:0.68rem;">{{ $row['access_type'] }}</span>
                            @else
                                <span class="text-muted">Belum diisi</span>
                            @endif
                        </td>
                        <td class="acs-monospace">
                            @if(filled($row['pppoe_ip'] ?? null))
                            <a href="http://{{ $row['pppoe_ip'] }}" target="_blank" rel="noopener" class="acs-ip-link" title="Buka http://{{ $row['pppoe_ip'] }}">{{ $row['pppoe_ip'] }}</a>
                            @else
                            <span class="text-muted">Belum diisi</span>
                            @endif
                        </td>
                        <td class="acs-monospace">
                            @if(filled($row['wan_ip'] ?? null))
                            <a href="http://{{ $row['wan_ip'] }}" target="_blank" rel="noopener" class="acs-ip-link" title="Buka http://{{ $row['wan_ip'] }}">{{ $row['wan_ip'] }}</a>
                            @else
                            <span class="text-muted">Belum diisi</span>
                            @endif
                        </td>
                        <td>
                            @if(filled($row['ssid'] ?? null))
                                <span><i class="fa-solid fa-wifi me-1" style="color:var(--success);"></i>{{ Str::limit($row['ssid'], 20) }}</span>
                            @else
                                <span class="text-muted">Belum diisi</span>
                            @endif
                        </td>
                        <td class="text-center col-aksi">
                            <div class="d-inline-flex gap-1 flex-nowrap">
                                <a href="{{ route('noc.genieacs.device-detail', $deviceId) }}" class="btn btn-primary acs-btn-xs" title="Detail"><i class="fa-solid fa-circle-info me-1"></i>Detail</a>
                                <button type="button" class="btn btn-warning acs-btn-xs text-dark btn-summon" title="Summon" data-url="{{ route('noc.genieacs.summon', $deviceId) }}" data-label="{{ $label }}"><i class="fa-solid fa-bolt me-1"></i>Summon</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Pagination / Navigation --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3" style="font-size:0.85rem;border-top:1px solid var(--border-subtle);">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted">Menampilkan {{ $skip + 1 }}-{{ min($skip + $limit, $total) }} dari {{ $total }}</span>
                <div class="d-flex align-items-center gap-1">
                    <span class="text-muted">Per halaman</span>
                    <select class="form-select form-select-sm acs-page-size" style="width:auto;font-size:0.78rem;" aria-label="Jumlah per halaman">
                        @foreach([10, 20, 50, 100] as $per)
                        <option value="{{ $per }}" {{ $limit == $per ? 'selected' : '' }}>{{ $per }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex gap-1">
                <a href="{{ route('noc.genieacs.devices', array_merge($filters, ['skip' => max(0, $skip - $limit), 'limit' => $limit])) }}" class="btn btn-sm btn-outline-secondary {{ $skip > 0 ? '' : 'disabled' }}">
                    <i class="fa-solid fa-chevron-left me-1"></i>Sebelumnya
                </a>
                <a href="{{ route('noc.genieacs.devices', array_merge($filters, ['skip' => $skip + $limit, 'limit' => $limit])) }}" class="btn btn-sm btn-outline-secondary {{ $skip + $limit < $total ? '' : 'disabled' }}">
                    Selanjutnya<i class="fa-solid fa-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-summon').forEach(function(btn) {
    btn.addEventListener('click', async function() {
        var url = this.dataset.url;
        var label = this.dataset.label || 'device';
        var original = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Mengirim...';

        try {
            var res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            var data = await res.json();

            if (data.success) {
                alert('Summon terkirim: ' + label + '\n' + (data.message || ''));
            } else {
                alert('Gagal summon: ' + (data.message || 'Terjadi kesalahan'));
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }

        this.disabled = false;
        this.innerHTML = original;
    });
});

document.querySelectorAll('.acs-page-size').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('skip', '0');
        window.location.href = url.toString();
    });
});
</script>
@endpush