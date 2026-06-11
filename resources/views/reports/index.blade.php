@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-pie me-2" style="color:var(--primary);"></i>Laporan</h2>
        <p class="section-subtitle mb-0 mt-1">Rekap pemasukan, piutang, dan statistik billing</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('export.invoices') }}" class="btn btn-outline-success px-3">
            <i class="fa-solid fa-file-excel me-1"></i>Export Invoice
        </a>
        <a href="{{ route('export.payments') }}" class="btn btn-outline-success px-3">
            <i class="fa-solid fa-file-excel me-1"></i>Export Pembayaran
        </a>
    </div>
</div>

{{-- FILTER BULAN --}}
<div class="card shadow-sm border-0 mb-4 report-filter-card">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="dot" style="background:var(--primary);"></span>
        <span>Filter Periode</span>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tahun</label>
                <select name="year" class="form-select form-select-sm">
                    @for($y = now()->year - 2; $y <= now()->year; $y++)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Bulan</label>
                <select name="month" class="form-select form-select-sm">
                    @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $m)
                        <option value="{{ $i + 1 }}" {{ $month == $i + 1 ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- STATS CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 fade-in fade-in-delay-1">
        <div class="card stat-card text-white stat-card-gradient-green">
            <div class="stat-bg"><i class="fa-solid fa-money-bill-trend-up"></i></div>
            <div class="card-body">
                <div class="stat-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <div class="stat-number stat-number-currency">Rp{{ number_format($monthlyRevenue, 0, ',', '.') }}</div>
                <div class="stat-label">Pemasukan {{ now()->month((int)$month)->format('M') }} {{ $year }}</div>
                <div class="stat-details">
                    <span><i class="fa-regular fa-receipt"></i>{{ $monthlyCount }} transaksi</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in fade-in-delay-2">
        <div class="card stat-card text-white stat-card-gradient-red">
            <div class="stat-bg"><i class="fa-solid fa-clock"></i></div>
            <div class="card-body">
                <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-number stat-number-currency">Rp{{ number_format($totalOutstanding, 0, ',', '.') }}</div>
                <div class="stat-label">Piutang Tertagih</div>
                <div class="stat-details">
                    <span><i class="fa-regular fa-receipt"></i>{{ $outstandingCount }} tagihan</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in fade-in-delay-3">
        <div class="card stat-card text-white stat-card-gradient-blue">
            <div class="stat-bg"><i class="fa-solid fa-users"></i></div>
            <div class="card-body">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div class="stat-number">{{ $activeCustomers }}</div>
                <div class="stat-label">Pelanggan Aktif</div>
                <div class="stat-details">
                    <span><i class="fa-regular fa-user"></i>dari {{ $totalCustomers }} total</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in fade-in-delay-4">
        <div class="card stat-card text-white stat-card-gradient-dark">
            <div class="stat-bg"><i class="fa-solid fa-percentage"></i></div>
            <div class="card-body">
                @php $collectionRate = $monthlyRevenue + $totalOutstanding > 0 ? round(($monthlyRevenue / ($monthlyRevenue + $totalOutstanding)) * 100) : 0; @endphp
                <div class="stat-icon"><i class="fa-solid fa-percentage"></i></div>
                <div class="stat-number">{{ $collectionRate }}%</div>
                <div class="stat-label">Rasio Tertagih</div>
                <div class="stat-details">
                    <span><i class="fa-regular fa-calendar"></i>Bulan {{ now()->month((int)$month)->format('M') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- REVENUE CHART --}}
    <div class="col-lg-8 fade-in fade-in-delay-2">
        <div class="card shadow-sm border-0 report-chart-card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:var(--primary);"></span>
                <span>Grafik Pemasukan 12 Bulan</span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="250"></canvas>
            </div>
        </div>
    </div>

    {{-- PAYMENT METHOD BREAKDOWN --}}
    <div class="col-lg-4 fade-in fade-in-delay-3">
        <div class="card shadow-sm border-0 report-payment-card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#059669;"></span>
                <span>Metode Pembayaran</span>
            </div>
            <div class="card-body p-0">
                @if($methodBreakdown->count())
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Metode</th><th class="text-end">Jumlah</th><th class="text-end">Transaksi</th></tr>
                        </thead>
                        <tbody>
                            @foreach($methodBreakdown as $m)
                                <tr>
                                    <td>
                                        <span class="badge badge-soft-green">
                                            {{ ucfirst($m->payment_method) }}
                                        </span>
                                    </td>
                                    <td class="fw-bold text-end" style="color:#059669;">Rp{{ number_format($m->total, 0, ',', '.') }}</td>
                                    <td class="text-end text-muted">{{ $m->count }}x</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-center text-muted py-4">Belum ada data pembayaran</p>
                @endif
            </div>
        </div>
    </div>

    {{-- TOP UNPAID --}}
    <div class="col-12 fade-in fade-in-delay-4">
        <div class="card shadow-sm border-0 report-unpaid-card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="dot" style="background:#dc2626;"></span>
                <span>Piutang Tertinggi</span>
                <span class="badge ms-2 badge-soft-red">{{ $topUnpaid->count() }}</span>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Pelanggan</th><th>Invoice</th><th>Paket</th><th class="text-end">Tagihan</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topUnpaid as $inv)
                            <tr>
                                <td class="fw-medium">{{ $inv->customer->name ?? '-' }}</td>
                                <td><span class="badge badge-soft-primary">{{ $inv->invoice_code }}</span></td>
                                <td>{{ $inv->customer->package->name ?? '-' }}</td>
                                <td class="fw-bold text-end">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted"><i class="fa-regular fa-circle-check me-2 text-green-check"></i>Semua tagihan lunas!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var months = @json($months);
        var revenue = @json($revenueData);

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Pemasukan',
                    data: revenue,
                    borderColor: '#2563eb',
                    backgroundColor: function(ctx) {
                        var c = ctx.chart.ctx;
                        var g = c.createLinearGradient(0, 0, 0, 250);
                        g.addColorStop(0, 'rgba(37,99,235,0.2)');
                        g.addColorStop(1, 'rgba(37,99,235,0)');
                        return g;
                    },
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb',
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
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    }
                }
            }
        });
    });
</script>
@endpush
