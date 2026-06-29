@extends('layouts.app')

@php
$isAdmin = auth()->user()->role === 'admin';
@endphp

@section('title', 'Monitor Gangguan OLT')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-broadcast me-2" style="color:var(--primary);"></i>Monitor Gangguan</h2>
        <p class="section-subtitle mb-0 mt-1">Pemantauan redaman semua pelanggan — urut dari sinyal terlemah</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="{{ route('onu.export', ['status' => 'offline']) }}" class="btn btn-outline-danger px-3 py-2">
            <i class="fa-solid fa-download me-1"></i>Export ONU Offline
        </a>
        <a href="{{ route('onu.search') }}" class="btn btn-outline-primary px-3 py-2">
            <i class="fa-solid fa-search me-1"></i>Cari ONU
        </a>
    </div>
</div>

{{-- STATS CARDS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(96,165,250,0.15);color:#60a5fa;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalCustomers }}</div>
                <div class="stat-label">Total Pelanggan</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                <i class="fa-solid fa-wifi"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalOnline }}</div>
                <div class="stat-label">ONU Online</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">
                <i class="fa-solid fa-wifi"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalOffline }}</div>
                <div class="stat-label">ONU Offline</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(250,204,21,0.15);color:#eab308;">
                <i class="fa-solid fa-signal"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">{{ $totalWeak }}</div>
                <div class="stat-label">Sinyal Lemah (Rx < -27 dBm)</div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER & SEARCH --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" id="searchPelanggan" class="form-control" placeholder="Cari nama pelanggan..." onkeyup="filterTable()">
            </div>
            <div class="col-md-3">
                <select id="filterStatus" class="form-select" onchange="filterTable()">
                    <option value="">Semua Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="no_onu">Tidak Punya ONU</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterRedaman" class="form-select" onchange="filterTable()">
                    <option value="">Semua Redaman</option>
                    <option value="critical">Kritis (Rx < -30 dBm)</option>
                    <option value="weak">Lemah (Rx -30 s/d -25 dBm)</option>
                    <option value="normal">Normal (Rx > -25 dBm)</option>
                    <option value="no_data">Tidak Ada Data</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <span class="text-muted small" id="rowCount"></span>
            </div>
        </div>
    </div>
</div>

{{-- MAIN TABLE --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-table me-1"></i> Status Redaman Semua Pelanggan</span>
        <span class="badge bg-secondary" id="totalCount">{{ count($customerSignals) }} pelanggan</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="redamanTable">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Pelanggan</th>
                        <th style="width:110px;">Redaman (Rx)</th>
                        <th style="width:100px;">Tx Power</th>
                        <th style="width:90px;">Status</th>
                        <th>OLT / Port</th>
                        <th style="width:120px;">ONU ID</th>
                        <th style="width:140px;">Terakhir Lihat</th>
                        <th style="width:60px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customerSignals as $i => $cs)
                    @php
                        $rx = $cs['rx_power'];
                        $status = $cs['status'];
                        $rowClass = '';

                        if ($rx === null) {
                            $rowClass = 'table-secondary';
                        } elseif ($status === 'offline') {
                            $rowClass = 'table-danger';
                        } elseif ($rx < -30) {
                            $rowClass = 'table-danger';
                        } elseif ($rx < -25) {
                            $rowClass = 'table-warning';
                        }

                        $badgeClass = match(true) {
                            $rx === null => 'bg-secondary',
                            $status === 'offline' => 'bg-danger',
                            $rx < -30 => 'bg-danger',
                            $rx < -25 => 'bg-warning text-dark',
                            default => 'bg-success',
                        };

                        $rxLabel = match(true) {
                            $rx === null => 'N/A',
                            $status === 'offline' => number_format($rx, 1).' dBm',
                            $rx < -30 => number_format($rx, 1).' dBm',
                            default => number_format($rx, 1).' dBm',
                        };

                        $statusLabel = match($status) {
                            'online' => 'Online',
                            'offline' => 'Offline',
                            default => 'No ONU',
                        };

                        $statusBadge = match($status) {
                            'online' => 'bg-success',
                            'offline' => 'bg-danger',
                            default => 'bg-secondary',
                        };

                        $oltName = $cs['olt']?->name ?? '-';
                        $portName = $cs['oltPort']?->port_name ?? $cs['oltPort']
                            ? "Slot {$cs['oltPort']->slot_number}/Port {$cs['oltPort']->port_number}"
                            : '-';
                    @endphp
                    <tr class="{{ $rowClass }}" data-status="{{ $status }}" data-rx="{{ $rx ?? -999 }}" data-name="{{ strtolower($cs['customer']->name) }}">
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold">{{ $cs['customer']->name }}</div>
                            @if($cs['customer']->phone)
                                <small class="text-muted">{{ $cs['customer']->phone }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $badgeClass }}" style="font-size:0.8rem;">
                                {{ $rxLabel }}
                            </span>
                            @if($rx !== null)
                            <div class="mt-1" style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                @php
                                    $pct = max(0, min(100, (($rx + 35) / 25) * 100));
                                @endphp
                                <div style="width:{{ $pct }}%;height:100%;background:{{ $rx < -25 ? '#ef4444' : '#22c55e' }};border-radius:2px;"></div>
                            </div>
                            @endif
                        </td>
                        <td>
                            @if($cs['tx_power'] !== null)
                                <span class="small">{{ number_format($cs['tx_power'], 1) }} dBm</span>
                            @else
                                <span class="small text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td>
                            <small>
                                @if($cs['olt'])
                                    <div class="fw-semibold">{{ $oltName }}</div>
                                    <div class="text-muted">{{ $portName }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </small>
                        </td>
                        <td>
                            @if($cs['onu_id'])
                                <code class="small">{{ $cs['onu_id'] }}</code>
                                @if($cs['serial'])
                                    <div class="text-muted" style="font-size:0.7rem;">{{ $cs['serial'] }}</div>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($cs['last_seen'])
                                {{ $cs['last_seen']->format('d/m/Y H:i') }}
                                <div class="text-muted">{{ $cs['last_seen']->diffForHumans() }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($cs['onu'] && $cs['onu']->oltPort?->olt)
                                <form action="{{ route('olt.onu.reboot', [$cs['onu']->oltPort->olt, $cs['onu']]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-warning" title="Reboot ONU" onclick="return confirm('Reboot ONU {{ $cs['onu']->onu_id }} milik {{ $cs['customer']->name }}?')">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">Tidak ada data pelanggan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function filterTable() {
    const search = document.getElementById('searchPelanggan').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const redamanFilter = document.getElementById('filterRedaman').value;
    const rows = document.querySelectorAll('#redamanTable tbody tr');
    let visible = 0;

    rows.forEach(row => {
        if (row.querySelectorAll('td').length < 2) return;

        const name = row.getAttribute('data-name') || '';
        const status = row.getAttribute('data-status') || '';
        const rx = parseFloat(row.getAttribute('data-rx'));

        const matchSearch = name.includes(search);
        const matchStatus = !statusFilter || status === statusFilter;

        let matchRedaman = true;
        if (redamanFilter === 'critical') {
            matchRedaman = rx !== -999 && rx < -30;
        } else if (redamanFilter === 'weak') {
            matchRedaman = rx !== -999 && rx >= -30 && rx < -25;
        } else if (redamanFilter === 'normal') {
            matchRedaman = rx !== -999 && rx >= -25;
        } else if (redamanFilter === 'no_data') {
            matchRedaman = rx === -999;
        }

        if (matchSearch && matchStatus && matchRedaman) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('totalCount').textContent = visible + ' pelanggan';
    document.getElementById('rowCount').textContent = visible + ' baris';
}
filterTable();
</script>
@endpush
@endsection
