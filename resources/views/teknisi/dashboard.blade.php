@extends('layouts.app')

@section('title', 'Monitoring')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-broadcast me-2" style="color:var(--primary);"></i>Monitoring Jaringan</h2>
        <p class="section-subtitle mb-0 mt-1">
            <i class="fa-regular fa-calendar me-1"></i> {{ now()->format('l, d F Y') }}
            <span class="mx-2">•</span>
            <i class="fa-regular fa-clock me-1"></i> <span id="clock" class="dashboard-clock"></span>
        </p>
    </div>
</div>

{{-- STATS CARDS --}}
<div class="row g-4 dashboard-section-gap">
    @foreach([
        ['grad' => 'stat-card-gradient-blue', 'icon' => 'users', 'num' => $totalCustomers, 'label' => 'Total Pelanggan', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> $activeCustomers aktif",
            "<i class=\"fa-regular fa-circle-pause\"></i> $suspendedCustomers suspend",
            "<i class=\"fa-regular fa-circle-xmark\"></i> $inactiveCustomers nonaktif",
        ]],
        ['grad' => 'stat-card-gradient-green', 'icon' => 'tower-cell', 'num' => $olts->count(), 'label' => 'OLT Terdaftar', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> ".$olts->where('status', 'active')->count()." aktif",
            "<i class=\"fa-regular fa-circle-xmark\"></i> ".$olts->where('status', 'inactive')->count()." nonaktif",
        ]],
        ['grad' => 'stat-card-gradient-red', 'icon' => 'network-wired', 'num' => $odps->count(), 'label' => 'Titik ODP', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> ".$odps->sum('port_used_actual')." port terpakai",
            "<i class=\"fa-regular fa-circle-xmark\"></i> ".($odps->sum('port_capacity') - $odps->sum('port_used_actual'))." sisa",
        ]],
        ['grad' => 'stat-card-gradient-dark', 'icon' => 'chart-line', 'num' => $olts->sum('total_onus'), 'label' => 'Total ONU', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> ".$olts->sum('online_onus')." online",
            "<i class=\"fa-regular fa-circle-xmark\"></i> ".($olts->sum('total_onus') - $olts->sum('online_onus'))." offline",
        ]],
    ] as $stat)
    <div class="col-md-3 fade-in fade-in-delay-{{ $loop->iteration }}">
        <div class="card stat-card text-white {{ $stat['grad'] }}">
            <div class="stat-bg"><i class="fa-solid fa-{{ $stat['icon'] }}"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-{{ $stat['icon'] }}"></i></div>
                    <div>
                        <div class="stat-number">{{ $stat['num'] }}</div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                    </div>
                </div>
                @if(!empty($stat['details']))
                <div class="stat-details">
                    @foreach($stat['details'] as $d)
                    <span>{!! $d !!}</span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- MAIN CONTENT ROW --}}
<div class="row g-5 dashboard-section-gap">
    <div class="col-lg-6 d-flex flex-column gap-5">
        {{-- OLT STATUS --}}
        <div class="card dash-table-packages overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:var(--primary);"></span>
                <span>Status OLT</span>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Nama</th><th>IP</th><th>Brand</th><th class="text-center">ONU Online</th><th class="text-center">Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($olts as $olt)
                        <tr>
                            <td class="fw-semibold">{{ $olt->name }}</td>
                            <td><code class="font-mono">{{ $olt->ip_address }}</code></td>
                            <td>{{ $olt->brand ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge" style="background:#f0fdf4;color:#059669;">
                                    {{ $olt->online_onus }}/{{ $olt->total_onus }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($olt->status === 'active')
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Aktif</span>
                                @else
                                    <span class="badge" style="background:#fef2f2;color:#dc2626;">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada OLT terdaftar</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- RECENT OFFLINE ONUS --}}
        <div class="card dash-table-unpaid overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#dc2626;"></span>
                <span>ONU Offline Terbaru</span>
                <span class="badge ms-2" style="background:#fef2f2;color:#dc2626;">{{ $recentOnuOffline->count() }}</span>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0">
                    <tbody>
                        @forelse($recentOnuOffline as $onu)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $onu->serial_number ?? $onu->onu_id }}</span>
                                <br>
                                <small class="text-muted">
                                    OLT: {{ $onu->oltPort->olt->name ?? '-' }}
                                </small>
                            </td>
                            <td class="text-end">
                                <span class="badge" style="background:#fef2f2;color:#dc2626;">Offline</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center py-4 text-muted">Semua ONU online</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex flex-column gap-5">
        {{-- ODP USAGE --}}
        <div class="card dash-table-odp overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#059669;"></span>
                <span>Penggunaan ODP</span>
                <small class="text-muted ms-2">{{ $odps->count() }} titik</small>
            </div>
            <div class="card-body p-0 table-scroll" style="max-height:360px;overflow-y:auto;">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Kode</th><th>Lokasi</th><th class="text-end">Port</th></tr>
                    </thead>
                    <tbody>
                        @forelse($odps as $o)
                        <tr>
                            <td class="fw-semibold">{{ $o->name }}</td>
                            <td class="text-muted small">{{ $o->address ?? '-' }}</td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <small class="text-muted">{{ $o->port_used_actual }}/{{ $o->port_capacity }}</small>
                                    <div class="progress-mini" style="width:50px;">
                                        <div class="progress-bar-fill" style="width:{{ $o->port_usage_percent }}%;background:{{ $o->port_usage_color }};"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada titik ODP</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ACTIVITY LOGS --}}
        <div class="card dash-table-customers overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:var(--accent);"></span>
                <span>Aktivitas Terbaru</span>
                <span class="badge ms-2 badge-soft-primary">{{ $activityLogs->count() }}</span>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0">
                    <tbody>
                        @forelse($activityLogs as $log)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $log->action }}</span>
                                <br>
                                <small class="text-muted">{{ $log->description }}</small>
                            </td>
                            <td class="text-end text-muted small">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center py-4 text-muted">Belum ada aktivitas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
