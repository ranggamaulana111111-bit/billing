@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-line me-2" style="color:var(--primary);"></i>Dashboard</h2>
        <p class="section-subtitle mb-0 mt-1">
            <i class="fa-regular fa-calendar me-1"></i> {{ now()->format('l, d F Y') }}
            <span class="mx-2">•</span>
            <i class="fa-regular fa-clock me-1"></i> <span id="clock" class="dashboard-clock"></span>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('customer.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-user-plus me-2"></i>Pasang Baru
        </a>
        <a href="{{ route('invoices.create') }}" class="btn btn-outline-success px-4 py-2">
            <i class="fa-solid fa-file-invoice me-2"></i>Buat Tagihan
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- STATS CARDS --}}
<div class="row g-4 dashboard-section-gap">
    @foreach([
        ['grad' => 'stat-card-gradient-blue', 'icon' => 'users', 'num' => $totalCustomers, 'label' => 'Total Pelanggan', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> $activeCustomers aktif",
            "<i class=\"fa-regular fa-circle-pause\"></i> $suspendedCustomers suspend",
            "<i class=\"fa-regular fa-circle-xmark\"></i> $inactiveCustomers nonaktif",
        ]],
        ['grad' => 'stat-card-gradient-green', 'icon' => 'money-bill-trend-up', 'num' => 'Rp '.number_format($todayRevenue, 0, ',', '.'), 'numClass' => 'stat-number-currency', 'label' => 'Pemasukan Hari Ini', 'details' => [
            "<i class=\"fa-regular fa-calendar\"></i> ".now()->format('d M Y'),
            "<i class=\"fa-regular fa-circle-check\"></i> Bulan ini Rp ".number_format($summary['total_paid'] ?? 0, 0, ',', '.'),
        ]],
        ['grad' => 'stat-card-gradient-red', 'icon' => 'clock', 'num' => 'Rp '.number_format($summary['total_unpaid'] ?? 0, 0, ',', '.'), 'numClass' => 'stat-number-currency', 'label' => 'Piutang Tertagih', 'details' => [
            "<i class=\"fa-regular fa-bell\"></i> ".$unpaidInvoices->count().' tagihan',
            "<i class=\"fa-regular fa-flag\"></i> $overdueCount overdue",
        ]],
        ['grad' => 'stat-card-gradient-dark', 'icon' => 'bolt', 'num' => $totalInvoices, 'label' => 'Total Tagihan', 'details' => [
            "<i class=\"fa-regular fa-circle-check\"></i> $paidCount lunas",
            "<i class=\"fa-regular fa-circle-xmark\"></i> $unpaidCount belum",
        ]],
    ] as $stat)
    <div class="col-md-3 fade-in fade-in-delay-{{ $loop->iteration }}">
        <div class="card stat-card text-white {{ $stat['grad'] }}">
            <div class="stat-bg"><i class="fa-solid fa-{{ $stat['icon'] }}"></i></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fa-solid fa-{{ $stat['icon'] }}"></i></div>
                    <div>
                        <div class="stat-number {{ $stat['numClass'] ?? '' }}">{{ $stat['num'] }}</div>
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

{{-- BILLING INSIGHTS --}}
<div class="row g-4 dashboard-section-gap">
    @foreach([
        ['title' => 'Revenue Bulan Ini', 'value' => 'Rp '.number_format($summary['total_paid'] ?? 0, 0, ',', '.'), 'color' => '#059669', 'bg' => '#ecfdf5', 'icon' => 'arrow-trend-up', 'footer' => 'Rasio lunas '.$paymentRate.'% dari '.$totalInvoices.' tagihan'],
        ['title' => 'Piutang Bulan Ini', 'value' => 'Rp '.number_format($monthUnpaid, 0, ',', '.'), 'color' => '#f97316', 'bg' => '#fff7ed', 'icon' => 'file-invoice-dollar', 'footer' => 'Total piutang semua periode Rp '.number_format($summary['total_unpaid'] ?? 0, 0, ',', '.')],
        ['title' => 'Overdue', 'value' => $overdueCount.' tagihan', 'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'triangle-exclamation', 'footer' => 'Nilai overdue Rp '.number_format($overdueTotal, 0, ',', '.')],
        ['title' => 'Paket Terlaris', 'value' => $topPackage?->name ?? '-', 'color' => 'var(--primary)', 'bg' => '#eef2ff', 'icon' => 'wifi', 'footer' => ($topPackage?->customers_count ?? 0).' pelanggan • '.$activePackageCount.' aktif / '.$inactivePackageCount.' nonaktif'],
    ] as $insight)
    <div class="col-md-6 col-xl-3">
        <div class="card insight-card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="insight-label">{{ $insight['title'] }}</div>
                        <div class="insight-value mt-1" style="color:{{ $insight['color'] }};">{{ $insight['value'] }}</div>
                    </div>
                    <div class="insight-icon" style="background:{{ $insight['bg'] }};color:{{ $insight['color'] }};"><i class="fa-solid fa-{{ $insight['icon'] }}"></i></div>
                </div>
                <div class="insight-footer">{{ $insight['footer'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- MAIN CONTENT ROW --}}
<div class="row g-5 dashboard-section-gap">
    <div class="col-lg-5 d-flex flex-column gap-5">
        {{-- SMALL CHARTS --}}
        <div class="row g-2">
            <div class="col-4">
                <div class="card dash-chart-sm dash-chart-green h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <span class="dot" style="background:#059669;"></span>
                        <span>Status Tagihan</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="statusChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card dash-chart-sm dash-chart-purple h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <span class="dot" style="background:#8b5cf6;"></span>
                        <span>Metode Bayar</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="paymentMethodChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card dash-chart-sm dash-chart-amber h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <span class="dot" style="background:#f59e0b;"></span>
                        <span>Pelanggan per Paket</span>
                    </div>
                    <div class="card-body">
                        <canvas id="packageChart" height="140"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @php $packetDot = 'var(--primary)'; @endphp
        <div class="card dash-table-packages overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:var(--primary);"></span>
                <span>Daftar Paket Internet</span>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Paket</th><th>Speed</th><th class="text-end">Harga</th><th class="text-end">Pelanggan</th></tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $p)
                        @php $speedLabel = preg_match('/mbps/i', (string) $p->speed) ? $p->speed : $p->speed.' Mbps'; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $p->name }}</td>
                            <td><span class="badge badge-soft-primary"><i class="fa-solid fa-wifi me-1"></i>{{ $speedLabel }}</span></td>
                            <td class="fw-bold text-end">Rp{{ number_format($p->price, 0, ',', '.') }}</td>
                            <td class="text-end">{{ $p->customers_count ?? $p->customers()->count() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada paket tersedia</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card dash-table-odp overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#059669;"></span>
                <span>Titik ODP</span>
            </div>
            <div class="card-body p-0 pb-3 table-scroll" style="max-height:360px;overflow-y:auto;">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Kode</th><th>Lokasi</th><th class="text-end">Port</th></tr>
                    </thead>
                    <tbody>
                        @forelse($odps as $o)
                        <tr>
                            <td class="fw-semibold">{{ $o->name }}</td>
                            <td class="text-muted small">{{ $o->address }}</td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <small class="text-muted">{{ $o->port_used_actual }}/{{ $o->port_capacity }}</small>
                                    <div class="progress-mini" style="width:50px;">
                                        <div class="progress-bar-fill" style="width:{{ $o->port_usage_percent }}%;background:{{ $o->port_usage_color }};"></div>
                                    </div>
                                </div>
                                @if($o->customers->count() > 0)
                                    <div class="mt-1">
                                        @foreach($o->customers as $customer)
                                        <small class="d-block text-muted text-micro">
                                            <i class="fa-solid fa-plug me-1" style="color:var(--primary);font-size:8px;"></i>{{ $customer->name }}
                                        </small>
                                        @endforeach
                                    </div>
                                @else
                                    <small class="text-muted text-micro">Kosong</small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada titik ODP</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="col-lg-7 d-flex flex-column gap-5">
        <div class="card dash-chart-revenue overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:var(--primary);"></span>
                <span>Grafik Pemasukan</span>
                <small class="text-muted ms-2">6 bulan terakhir</small>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="200"></canvas>
            </div>
        </div>
        <div class="card dash-table-payments overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#059669;"></span>
                <span>Pembayaran Terakhir</span>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0">
                    <tbody>
                        @forelse($paidInvoices as $paid)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $paid->customer->name }}</span>
                                <br>
                                <small class="text-muted">{{ $paid->invoice_code }} • {{ $paid->paid_at ? \Carbon\Carbon::parse($paid->paid_at)->format('d/m') : $paid->created_at->format('d/m') }}</small>
                            </td>
                            <td class="text-end fw-bold text-green">
                                @if($paid->payment_method)
                                    <small class="text-muted fw-normal text-micro">{{ ucfirst($paid->payment_method) }}</small><br>
                                @endif
                                +Rp{{ number_format($paid->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center py-4 text-muted">Belum ada pembayaran</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card dash-map overflow-hidden">
            <div class="card-header card-header-wrap">
                <div class="card-header-inner">
                    <span class="dot" style="background:var(--primary);"></span>
                    <span>Live Monitoring Infrastruktur</span>
                </div>
                <div class="card-header-inner">
                    <span class="badge badge-online">
                        <span class="dot dot-sm" style="background:#059669;margin-right:4px;vertical-align:middle;"></span>Online
                    </span>
                    <small class="text-muted">{{ $odps->count() + $newOdps->count() }} titik ODP</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="map"></div>
            </div>
        </div>
    </div>
</div>

{{-- CUSTOMERS TABLE --}}
<div class="card dash-table-customers overflow-hidden mb-4 mt-2">
    <div class="card-header card-header-wrap">
        <div class="card-header-inner">
            <span class="dot" style="background:var(--accent);"></span>
            <span>Pelanggan Terbaru</span>
            <span class="badge ms-2 badge-soft-primary">{{ $customers->count() }}</span>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-premium">Lihat Semua</a>
    </div>
    <div class="card-body p-0 table-scroll">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Paket</th>
                    <th>ODP</th>
                    <th>PPPoE User</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                @php
                    $overdue = $c->due_date ? \Carbon\Carbon::parse($c->due_date)->isPast() : false;
                    $statusBadge = match($c->status) {
                        'active' => ['bg' => '#f0fdf4', 'text' => '#059669'],
                        'suspended' => ['bg' => '#fef2f2', 'text' => '#dc2626'],
                        'inactive' => ['bg' => '#f1f5f9', 'text' => '#94a3b8'],
                        default => ['bg' => '#f1f5f9', 'text' => '#94a3b8'],
                    };
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-initials">{{ strtoupper(substr($c->name, 0, 1)) }}</div>
                            <div>
                                <div class="fw-semibold">{{ $c->name }}</div>
                                <small class="text-muted">{{ $c->location ?? '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td class="fw-medium">{{ $c->package->name ?? '-' }}</td>
                    <td><span class="badge badge-soft-slate">{{ $c->odp->name ?? '-' }}</span></td>
                    <td><code class="font-mono">{{ $c->pppoe_username }}</code></td>
                    <td>
                        @if($c->due_date)
                            <span class="badge" style="background:{{ $overdue ? '#fef2f2' : '#f0fdf4' }};color:{{ $overdue ? '#dc2626' : '#059669' }};">{{ \Carbon\Carbon::parse($c->due_date)->format('d M') }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td><span class="badge" style="background:{{ $statusBadge['bg'] }};color:{{ $statusBadge['text'] }};">{{ ucfirst($c->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('invoices.create') }}" class="btn btn-sm btn-gradient-orange fw-semibold px-3"><i class="fa-solid fa-bolt me-1"></i>Tagih</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada pelanggan terdaftar</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- BOTTOM ROW --}}
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card dash-table-unpaid overflow-hidden h-100">
            <div class="card-header card-header-wrap">
                <div class="card-header-inner">
                    <span class="dot" style="background:#dc2626;"></span>
                    <span>Tagihan Belum Dibayar</span>
                    <span class="badge ms-2 badge-soft-red">{{ $unpaidInvoices->count() }}</span>
                    @if($overdueCount > 0)
                        <span class="badge badge-overdue">{{ $overdueCount }} overdue</span>
                    @endif
                </div>
                <a href="{{ route('invoices.index', ['status' => 'unpaid']) }}" class="btn btn-sm btn-outline-premium">Lihat Semua</a>
            </div>
            <div class="card-body p-0 table-scroll">
                <table class="table mb-0 table-unpaid">
                    <thead>
                        <tr><th>Invoice</th><th>Pelanggan</th><th class="text-end">Total</th><th>Jatuh Tempo</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse($unpaidInvoices as $inv)
                        @php
                            $dueDate = $inv->customer?->due_date ? \Carbon\Carbon::parse($inv->customer->due_date) : null;
                            $isOverdue = $dueDate && $dueDate->isPast();
                        @endphp
                        <tr class="{{ $isOverdue ? 'row-overdue' : '' }}">
                            <td><span class="badge badge-soft-primary">{{ $inv->invoice_code }}</span></td>
                            <td class="fw-medium">{{ $inv->customer->name ?? '-' }}</td>
                            <td class="fw-bold text-end">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                            <td>
                                @if($dueDate)
                                    <span class="badge" style="background:{{ $isOverdue ? '#fef2f2' : '#f0fdf4' }};color:{{ $isOverdue ? '#dc2626' : '#059669' }};">{{ $dueDate->format('d/m') }}@if($isOverdue) ({{ $dueDate->diffForHumans() }})@endif</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1 action-compact">
                                    <a href="{{ route('invoice.reminder', $inv->id) }}" class="btn btn-sm btn-gradient-green" title="Kirim WA Reminder" onclick="return confirm('Kirim reminder pembayaran ke {{ $inv->customer->name ?? '?' }}?')"><i class="fa-brands fa-whatsapp"></i></a>
                                    <a href="{{ route('payment.create', $inv->id) }}" class="btn btn-sm btn-dark" title="Bayar"><i class="fa-solid fa-qrcode"></i></a>
                                    <a href="{{ route('invoice.paid', $inv->id) }}" class="btn btn-sm btn-info text-white" title="Tandai Lunas" onclick="return confirm('Konfirmasi pembayaran untuk {{ $inv->customer->name ?? '?' }}?')"><i class="fa-solid fa-check"></i></a>
                                    <a href="{{ route('invoice.print', $inv->id) }}" class="btn btn-sm btn-outline-secondary" title="Cetak Faktur" target="_blank"><i class="fa-solid fa-print"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">
                            <i class="fa-regular fa-circle-check me-2 text-green-check"></i>Semua tagihan lunas!
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5 d-flex flex-column gap-4">
        <div class="card dash-table-activity overflow-hidden">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#64748b;"></span>
                <span>Aktivitas Terakhir</span>
            </div>
            <div class="card-body p-0">
                <div style="max-height:160px;overflow-y:auto;">
                    <table class="table mb-0">
                        <tbody>
                            @forelse($activityLogs as $log)
                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="dot dot-sm" style="background:var(--primary);"></span>
                                        <div>
                                            <small class="fw-medium d-block" style="line-height:1.3;">{{ $log->action }}</small>
                                            <small class="text-muted" style="font-size:0.7rem;">{{ $log->details }} • {{ $log->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td class="text-center py-3 text-muted">Belum ada aktivitas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var primary = '#2563eb';
        var accent = '#6366f1';

        // Revenue Chart
        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: @json($months),
                datasets: [{
                    label: 'Pemasukan',
                    data: @json($monthlyRevenue),
                    backgroundColor: function(ctx) {
                        var c = ctx.chart.ctx;
                        var g = c.createLinearGradient(0, 0, 0, 300);
                        g.addColorStop(0, primary);
                        g.addColorStop(1, accent);
                        return g;
                    },
                    borderColor: primary,
                    borderWidth: 0,
                    borderRadius: 8,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return 'Rp ' + Number(ctx.raw).toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 },
                            callback: function(v) {
                                return 'Rp' + (v / 1000).toFixed(0) + 'k';
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    }
                }
            }
        });

        // Status Donut
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Lunas', 'Belum'],
                datasets: [{
                    data: [{{ $paidCount }}, {{ $unpaidCount }}],
                    backgroundColor: ['#10b981', '#f97316'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 8, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#475569' }
                    },
                    tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 12, cornerRadius: 8 }
                }
            }
        });

        // Payment Method Chart
        var pmLabels = [];
        var pmData = [];
        var pmColors = [];
        var colorMap = { cash: '#10b981', transfer: '#3b82f6', qris: '#8b5cf6', midtrans: '#f59e0b' };
        @foreach($paymentMethods as $pm)
        pmLabels.push('{{ ucfirst($pm->payment_method) }}');
        pmData.push({{ $pm->count }});
        pmColors.push(colorMap['{{ $pm->payment_method }}'] || '#94a3b8');
        @endforeach

        if (pmLabels.length === 0) {
            pmLabels = ['Belum ada data'];
            pmData = [1];
            pmColors = ['#e2e8f0'];
        }

        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'doughnut',
            data: {
                labels: pmLabels,
                datasets: [{
                    data: pmData,
                    backgroundColor: pmColors,
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 6, usePointStyle: true, pointStyleWidth: 6, font: { size: 9 }, color: '#475569' }
                    },
                    tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 }
                }
            }
        });

        // Package Distribution Chart
        var pkgLabels = [];
        var pkgData = [];
        var pkgColors = ['#2563eb', '#059669', '#d97706', '#dc2626', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        @foreach($packageDistribution as $i => $pkg)
        pkgLabels.push('{{ $pkg->name }}');
        pkgData.push({{ $pkg->customers_count ?? $pkg->customers()->count() }});
        @endforeach

        if (pkgData.every(v => v === 0)) {
            pkgLabels = ['Belum ada pelanggan'];
            pkgData = [1];
            pkgColors = ['#e2e8f0'];
        }

        new Chart(document.getElementById('packageChart'), {
            type: 'bar',
            data: {
                labels: pkgLabels,
                datasets: [{
                    label: 'Pelanggan',
                    data: pkgData,
                    backgroundColor: pkgColors.slice(0, pkgLabels.length),
                    borderRadius: 4,
                    barPercentage: 0.7,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#94a3b8', font: { size: 9 } },
                        grid: { color: '#f1f5f9' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#475569', font: { size: 9 } }
                    }
                }
            }
        });

        // Live clock
        function updateClock() {
            var now = new Date();
            var time = now.getHours().toString().padStart(2, '0') + ':' +
                       now.getMinutes().toString().padStart(2, '0') + ':' +
                       now.getSeconds().toString().padStart(2, '0');
            var el = document.getElementById('clock');
            if (el) el.textContent = time;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Map
        var map = L.map('map').setView([-6.476, 106.014], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            className: 'map-tiles'
        }).addTo(map);

        var odpsData = @json($odps);
        var newOdpsData = @json($newOdps);
        var markerBounds = [];

        var icons = {
            green: L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:18px;height:18px;background:#059669;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            }),
            red: L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:18px;height:18px;background:#dc2626;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            })
        };

        odpsData.forEach(function(odp) {
            if (odp.latitude && odp.longitude) {
                var terpakai = odp.customers ? odp.customers.length : 0;
                var totalCapacity = odp.port_capacity ?? 16;
                var sisaPort = totalCapacity - terpakai;
                var isFull = sisaPort <= 0;

                var marker = L.marker([odp.latitude, odp.longitude], {
                    icon: isFull ? icons.red : icons.green
                }).addTo(map);

                var pct = totalCapacity > 0 ? Math.round((terpakai / totalCapacity) * 100) : 0;

                var popupContent = `
                    <div style="font-family:'Inter',sans-serif;min-width:170px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:${isFull ? '#dc2626' : '#059669'};"></div>
                            <h6 style="margin:0;font-weight:700;font-size:14px;color:#0f172a;">${odp.name}</h6>
                        </div>
                        <small style="color:#64748b;"><i class="fa-solid fa-location-dot"></i> ${odp.address ?? '-'}</small>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                <span style="color:#475569;">Terpakai</span>
                                <span style="font-weight:600;">${terpakai}/${totalCapacity}</span>
                            </div>
                            <div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${isFull ? '#dc2626' : '#059669'};border-radius:2px;"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;">
                                <span style="color:${isFull ? '#dc2626' : '#059669'};font-weight:600;">Sisa ${sisaPort} port</span>
                                <span style="color:#94a3b8;">${pct}%</span>
                            </div>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent, { className: 'custom-popup' });
                markerBounds.push([odp.latitude, odp.longitude]);
            }
        });

        newOdpsData.forEach(function(odp) {
            if (odp.latitude && odp.longitude) {
                var terpakai = odp.used ?? 0;
                var totalCapacity = odp.port_capacity ?? 16;
                var isFull = terpakai >= totalCapacity;

                var marker = L.marker([odp.latitude, odp.longitude], {
                    icon: isFull ? icons.red : icons.green
                }).addTo(map);

                var popupContent = `
                    <div style="font-family:'Inter',sans-serif;min-width:170px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:${isFull ? '#dc2626' : '#059669'};"></div>
                            <h6 style="margin:0;font-weight:700;font-size:14px;color:#0f172a;">${odp.name}</h6>
                        </div>
                        <small style="color:#64748b;"><i class="fa-solid fa-server"></i> ${odp.address ?? '-'}</small>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                <span style="color:#475569;">Terpakai</span>
                                <span style="font-weight:600;">${terpakai}/${totalCapacity}</span>
                            </div>
                            <div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:${totalCapacity > 0 ? Math.round((terpakai / totalCapacity) * 100) : 0}%;background:${isFull ? '#dc2626' : '#059669'};border-radius:2px;"></div>
                            </div>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent, { className: 'custom-popup' });
                markerBounds.push([odp.latitude, odp.longitude]);
            }
        });

        if (markerBounds.length > 0) {
            var bounds = L.latLngBounds(markerBounds);
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    });
</script>
@endpush
