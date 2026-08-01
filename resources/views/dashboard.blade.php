@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $hour = now()->hour;
    $greeting = match(true) {
        $hour < 6  => 'Selamat Malam',
        $hour < 12 => 'Selamat Pagi',
        $hour < 17 => 'Selamat Siang',
        $hour < 21 => 'Selamat Sore',
        default    => 'Selamat Malam',
    };
    $portUsage = $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100) : 0;
    $routeCount = $totalRoutes;
    $odpCount = $totalPoints;
    $revValues = $monthlyRevenue->toArray();
    $revAvg = count($revValues) > 0 ? round(array_sum($revValues) / count($revValues)) : 0;
    $revMax = count($revValues) > 0 ? max($revValues) : 0;
    $revMin = count($revValues) > 0 ? min($revValues) : 0;
    $revCurrent = end($revValues);
    $revPrev = count($revValues) > 1 ? $revValues[count($revValues) - 2] : 0;
    $revGrowth = $revPrev > 0 ? round((($revCurrent - $revPrev) / $revPrev) * 100) : ($revCurrent > 0 ? 100 : 0);
    $topPkgs = $packageDistribution->sortByDesc('customers_count')->take(5);
    $maxPkgCount = $topPkgs->max('customers_count') ?: 1;
    $collectionTarget = 95;
    $collectionDelta = $paymentRate - $collectionTarget;
    $critCount = $activeIncidents->where('severity','critical')->count();
    $warnCount = $activeIncidents->where('severity','high')->count();
    $maintCount = $activeIncidents->where('severity','medium')->count();
    $totalAlerts = $critCount + $warnCount + $maintCount + $overdueCount;
    $healthScore = 100 - ($critCount * 15) - ($warnCount * 8) - ($maintCount * 3) - ($overdueCount * 2);
    $healthScore = max(0, min(100, $healthScore));
    $healthColor = $healthScore >= 80 ? '#22c55e' : ($healthScore >= 60 ? '#f59e0b' : '#dc2626');
    $healthLabel = $healthScore >= 80 ? 'HEALTHY' : ($healthScore >= 60 ? 'DEGRADED' : 'CRITICAL');
@endphp

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- ═══ HERO ═══ --}}
<div class="v4-hero">
    <div class="v4-hero-row">
        <div class="v4-hero-left">
            <div class="v4-hero-greeting">{{ $greeting }}, Admin</div>
            <div class="v4-hero-meta">
                <span class="v4-hero-meta-item"><i class="fa-regular fa-calendar"></i> {{ now()->format('l, d M Y') }}</span>
                <span class="v4-hero-clock"><span class="v4-clock-dot"></span><span id="clock">--:--:--</span></span>
                <span class="v4-hero-meta-item"><span class="v4-status-dot" style="background:#22c55e;"></span> System Online</span>
            </div>
        </div>
        <div class="v4-hero-right">
            <a href="{{ route('customer.create') }}" class="v4-btn v4-btn-primary"><i class="fa-solid fa-user-plus"></i> Pasang Baru</a>
            <a href="{{ route('invoices.create') }}" class="v4-btn v4-btn-success"><i class="fa-solid fa-file-invoice"></i> Buat Tagihan</a>
            <button type="button" class="v4-btn v4-btn-ghost" onclick="window.location.reload()" title="Refresh"><i class="fa-solid fa-arrows-rotate"></i></button>
            <a href="{{ route('customers.index') }}" class="v4-btn v4-btn-ghost" title="Pelanggan"><i class="fa-solid fa-users"></i></a>
            {{-- <a href="{{ route('noc.mikrotik.dashboard') }}" class="v4-btn v4-btn-ghost" title="Monitoring"><i class="fa-solid fa-network-wired"></i></a> --}}
        </div>
    </div>
    <div class="v4-hero-strip">
        <span class="v4-strip-item"><span class="v4-strip-dot v4-strip-dot-on"></span> Internet</span>
        <span class="v4-strip-item"><span class="v4-strip-dot v4-strip-dot-on"></span> OLT · {{ $odpCount }} ODP</span>
        <span class="v4-strip-item"><span class="v4-strip-dot {{ $portUsage >= 80 ? 'v4-strip-dot-off' : 'v4-strip-dot-on' }}"></span> ONU {{ $totalUsed }}/{{ $totalCapacity }}</span>
        <span class="v4-strip-item"><span class="v4-strip-dot {{ $portUsage >= 90 ? 'v4-strip-dot-off' : ($portUsage >= 70 ? 'v4-strip-dot-warn' : 'v4-strip-dot-on') }}"></span> Port {{ $portUsage }}%</span>
        <span class="v4-strip-item"><span class="v4-strip-dot v4-strip-dot-on"></span> Uptime 99.9%</span>
        <span class="v4-strip-health">
            <span class="v4-health-ring" style="--health-pct:{{ $healthScore }};--health-color:{{ $healthColor }};"></span>
            <span class="v4-health-label">{{ $healthScore }}% <span class="v4-health-tag" style="color:{{ $healthColor }};">{{ $healthLabel }}</span></span>
        </span>
    </div>
</div>

{{-- ═══ EXECUTIVE SUMMARY ═══ --}}
<div class="v4-grid-5">
    <div class="v4-exec-card v4-exec-revenue">
        <div class="v4-exec-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        <div class="v4-exec-body">
            <div class="v4-exec-num" data-count="{{ (int)$todayRevenue }}" data-prefix="Rp ">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</div>
            <div class="v4-exec-label">Pendapatan Hari Ini</div>
        </div>
        <div class="v4-exec-trend v4-trend-up"><i class="fa-solid fa-arrow-up"></i> {{ $revGrowth }}%</div>

    </div>
    <div class="v4-exec-card v4-exec-customers">
        <div class="v4-exec-icon"><i class="fa-solid fa-users"></i></div>
        <div class="v4-exec-body">
            <div class="v4-exec-num" data-count="{{ $activeCustomers }}">{{ $activeCustomers }}</div>
            <div class="v4-exec-label">Pelanggan Aktif</div>
        </div>
        <div class="v4-exec-trend v4-trend-up"><i class="fa-solid fa-arrow-up"></i> dari {{ $totalCustomers }}</div>

    </div>
    <div class="v4-exec-card v4-exec-incidents">
        <div class="v4-exec-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="v4-exec-body">
            <div class="v4-exec-num">{{ $activeIncidents->count() }}</div>
            <div class="v4-exec-label">Gangguan Aktif</div>
        </div>
        @if($activeIncidents->count() > 0)
        <div class="v4-exec-trend v4-trend-down"><i class="fa-solid fa-circle-exclamation"></i> perlu tindakan</div>
        @else
        <div class="v4-exec-trend v4-trend-up"><i class="fa-solid fa-check"></i> aman</div>
        @endif

    </div>
    <div class="v4-exec-card v4-exec-overdue">
        <div class="v4-exec-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="v4-exec-body">
            <div class="v4-exec-num" data-count="{{ $overdueCount }}">{{ $overdueCount }}</div>
            <div class="v4-exec-label">Invoice Overdue</div>
        </div>
        @if($overdueCount > 0)
        <div class="v4-exec-trend v4-trend-warn"><i class="fa-solid fa-circle-exclamation"></i> Rp {{ number_format($overdueTotal / 1000, 0, ',', '.') }}k</div>
        @else
        <div class="v4-exec-trend v4-trend-up"><i class="fa-solid fa-check"></i> bersih</div>
        @endif

    </div>
    <div class="v4-exec-card v4-exec-collection">
        <div class="v4-exec-icon"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="v4-exec-body">
            <div class="v4-exec-num">{{ $paymentRate }}%</div>
            <div class="v4-exec-label">Collection Rate</div>
        </div>
        <div class="v4-exec-trend {{ $collectionDelta >= 0 ? 'v4-trend-up' : 'v4-trend-down' }}">
            <i class="fa-solid fa-{{ $collectionDelta >= 0 ? 'arrow-up' : 'arrow-down' }}"></i> Target {{ $collectionTarget }}%
        </div>

    </div>
</div>

{{-- ═══ REVENUE ANALYTICS ═══ --}}
<div class="v4-card" style="margin-top:var(--space-lg);">
    <div class="v4-card-header">
        <div class="v4-card-title"><span class="v4-dot" style="background:var(--primary);"></span>Grafik Pemasukan <small>6 bulan terakhir</small></div>
        <div class="v4-card-actions">
            <button class="v4-icon-btn" title="Fullscreen" onclick="var el=document.getElementById('revenueChart');if(el.requestFullscreen)el.requestFullscreen();"><i class="fa-solid fa-expand"></i></button>
            <button class="v4-icon-btn" title="Export" onclick="window.print();"><i class="fa-solid fa-download"></i></button>
        </div>
    </div>
    <div class="v4-card-body-row">
        <div class="v4-revenue-main">
            <div class="v4-card-meta">
                Rp {{ number_format($summary['total_paid'] ?? 0, 0, ',', '.') }} <small>bulan ini</small>
                @if($revGrowth > 0)<span class="v4-trend-up"><i class="fa-solid fa-arrow-up"></i> {{ $revGrowth }}%</span>
                @elseif($revGrowth < 0)<span class="v4-trend-down"><i class="fa-solid fa-arrow-down"></i> {{ abs($revGrowth) }}%</span>@endif
            </div>
            <div class="v4-chart-wrap"><canvas id="revenueChart"></canvas></div>
            <div class="v4-summary-row">
                <div class="v4-summary-item"><div class="v4-summary-num v4-text-green">{{ $revGrowth > 0 ? '+' : '' }}{{ $revGrowth }}%</div><div class="v4-summary-label">Growth</div></div>
                <div class="v4-summary-item"><div class="v4-summary-num">Rp {{ number_format($revAvg / 1000, 0, ',', '.') }}k</div><div class="v4-summary-label">Rata-rata</div></div>
                <div class="v4-summary-item"><div class="v4-summary-num v4-text-green">Rp {{ number_format($revMax / 1000, 0, ',', '.') }}k</div><div class="v4-summary-label">Tertinggi</div></div>
                <div class="v4-summary-item"><div class="v4-summary-num v4-text-red">Rp {{ number_format($revMin / 1000, 0, ',', '.') }}k</div><div class="v4-summary-label">Terendah</div></div>
            </div>
        </div>
        <div class="v4-revenue-side">
            <div class="v4-side-donut">
                <div class="v4-donut-area"><div class="v4-donut-wrap"><div class="v4-donut-chart"><canvas id="statusChart"></canvas></div><div class="v4-donut-center"><div class="v4-donut-num">{{ $paymentRate }}%</div><div class="v4-donut-label">Lunas</div></div></div></div>
                <div class="v4-side-cap"><div class="v4-side-cat" style="color:#10b981;"><i class="fa-solid fa-circle-check"></i> Status Tagihan</div><div class="v4-donut-legend"><span><span class="v4-legend-dot" style="background:#10b981;"></span>{{ $paidCount }} Lunas</span><span><span class="v4-legend-dot" style="background:#f97316;"></span>{{ $unpaidCount }} Belum</span></div></div>
            </div>
            <div class="v4-side-donut">
                <div class="v4-donut-area"><div class="v4-donut-wrap"><div class="v4-donut-chart"><canvas id="paymentMethodChart"></canvas></div><div class="v4-donut-center"><div class="v4-donut-num">{{ $paymentMethods->sum('count') }}</div><div class="v4-donut-label">Transaksi</div></div></div></div>
                <div class="v4-side-cap"><div class="v4-side-cat" style="color:#8b5cf6;"><i class="fa-solid fa-wallet"></i> Metode Bayar</div><div class="v4-donut-legend" id="pmLegend"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ UNPAID INVOICES (full width) ═══ --}}
<div class="v4-card" style="margin-top:var(--space-lg);">
    <div class="v4-card-header">
        <div class="v4-card-title"><span class="v4-dot" style="background:#dc2626;"></span>Tagihan Belum Dibayar
            @if($overdueCount > 0)<span class="v4-badge v4-badge-red">{{ $overdueCount }} overdue</span>@endif
        </div>
        <a href="{{ route('invoices.index', ['status' => 'unpaid']) }}" class="v4-link">Lihat Semua <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="v4-invoice-list">
        @forelse($unpaidInvoices->take(5) as $inv)
        @php
            $dueDate = $inv->customer?->due_date ? \Carbon\Carbon::parse($inv->customer->due_date) : null;
            $isOverdue = $dueDate && $dueDate->isPast();
        @endphp
        <div class="v4-invoice-item {{ $isOverdue ? 'v4-invoice-overdue' : '' }}">
            <div class="v4-invoice-info">
                <div class="v4-invoice-name">{{ $inv->customer->name ?? '-' }}</div>
                <div class="v4-invoice-meta">{{ $inv->invoice_display }} · {{ $dueDate ? $dueDate->format('d/m') : '-' }}</div>
            </div>
            <div class="v4-invoice-amount">Rp{{ number_format($inv->amount, 0, ',', '.') }}</div>
            <div class="v4-invoice-actions">
                <a href="{{ route('invoice.reminder', $inv->id) }}" class="v4-btn-icon v4-btn-wa" title="WA Reminder" onclick="return confirm('Kirim reminder ke {{ $inv->customer->name ?? '?' }}?')"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="{{ route('invoice.paid', $inv->id) }}" class="v4-btn-icon v4-btn-check" title="Tandai Lunas" onclick="return confirm('Konfirmasi pembayaran?')"><i class="fa-solid fa-check"></i></a>
                <a href="{{ route('invoice.print', $inv->id) }}" class="v4-btn-icon v4-btn-print" title="Cetak" target="_blank"><i class="fa-solid fa-print"></i></a>
            </div>
        </div>
        @empty
        <div class="v4-alert-empty"><i class="fa-solid fa-circle-check"></i><span>Semua Tagihan Lunas</span></div>
        @endforelse
    </div>
</div>

{{-- ═══ NETWORK HEALTH (full width) ═══ --}}
<div class="v4-card" style="margin-top:var(--space-lg);">
    <div class="v4-card-header">
        <div class="v4-card-title"><span class="v4-dot" style="background:var(--success);"></span>Network Health</div>
    </div>
    <div class="v4-net-list" style="padding:0 20px 8px;">
                @php
                    $netItems = [
                        ['icon' => 'fa-solid fa-globe', 'color' => '#059669', 'name' => 'Internet', 'status' => 'online', 'label' => 'Connected'],
                        ['icon' => 'fa-solid fa-tower-broadcast', 'color' => '#2563eb', 'name' => 'OLT', 'status' => 'online', 'label' => $odpCount . ' ODP'],
                        ['icon' => 'fa-solid fa-plug', 'color' => $portUsage >= 80 ? '#dc2626' : '#059669', 'name' => 'ONU / Port', 'status' => $portUsage >= 80 ? 'warning' : 'online', 'label' => $totalUsed . '/' . $totalCapacity . ' (' . $portUsage . '%)', 'bar' => $portUsage],
                        ['icon' => 'fa-solid fa-wifi', 'color' => '#06b6d4', 'name' => 'Fiber', 'status' => 'online', 'label' => $routeCount . ' routes'],
                        ['icon' => 'fa-solid fa-server', 'color' => ($routerStatus['core'] === 'online' ? '#059669' : ($routerStatus['core'] === 'warning' ? '#d97706' : '#94a3b8')), 'name' => 'Core Router', 'status' => $routerStatus['core'], 'label' => ($routerStatus['core'] === 'online' ? 'Connected' : ($routerStatus['core'] === 'warning' ? 'Delayed' : 'N/A'))],
                        ['icon' => 'fa-solid fa-network-wired', 'color' => ($routerStatus['mikrotik'] === 'online' ? '#059669' : ($routerStatus['mikrotik'] === 'warning' ? '#d97706' : '#94a3b8')), 'name' => 'MikroTik', 'status' => $routerStatus['mikrotik'], 'label' => ($routerStatus['mikrotik'] === 'online' ? 'Connected' : ($routerStatus['mikrotik'] === 'warning' ? 'Delayed' : 'N/A'))],
                    ];
                @endphp
                @foreach($netItems as $ni)
                <div class="v4-net-item">
                    <div class="v4-net-icon" style="color:{{ $ni['color'] }};"><i class="{{ $ni['icon'] }}"></i></div>
                    <div class="v4-net-name">{{ $ni['name'] }}</div>
                    <span class="v4-net-badge v4-net-{{ $ni['status'] }}"><span class="v4-net-badge-dot"></span>{{ $ni['label'] }}</span>
                    @if(isset($ni['bar']))
                    <div class="v4-net-bar"><div class="v4-net-bar-fill" style="width:{{ $ni['bar'] }}%;background:{{ $ni['color'] }};"></div></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

{{-- ═══ INFRASTRUKTUR (full width) ═══ --}}
<div class="v4-card" style="margin-top:var(--space-lg);">
    <div class="v4-card-header">
        <div class="v4-card-title"><span class="v4-dot" style="background:var(--primary);"></span>Infrastruktur</div>
        <span class="v4-badge v4-badge-green"><span class="v4-strip-dot v4-strip-dot-on" style="margin-right:4px;"></span>{{ $odps->count() }} titik ODP</span>
    </div>
    <div class="v4-map-area" id="map"></div>
</div>

{{-- ═══ CUSTOMERS + PACKAGES + ACTIVITY (removed per request) ═══ --}}

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var primary = '#2563eb';
        var accent = '#6366f1';

        document.querySelectorAll('[data-count]').forEach(function(el) {
            var target = parseInt(el.dataset.count);
            var prefix = el.dataset.prefix || '';
            var suffix = el.dataset.suffix || '';
            if (isNaN(target) || target === 0) return;
            var duration = 1200, startTime = null;
            el.style.color = 'transparent';
            function animate(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = prefix + Math.floor(eased * target).toLocaleString('id-ID') + suffix;
                if (progress < 1) requestAnimationFrame(animate);
                else { el.style.color = ''; el.textContent = prefix + target.toLocaleString('id-ID') + suffix; }
            }
            requestAnimationFrame(animate);
        });

        var revCtx = document.getElementById('revenueChart').getContext('2d');
        var revGradient = revCtx.createLinearGradient(0, 0, 0, 260);
        revGradient.addColorStop(0, 'rgba(37,99,235,0.25)');
        revGradient.addColorStop(0.6, 'rgba(37,99,235,0.06)');
        revGradient.addColorStop(1, 'rgba(37,99,235,0)');
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: @json($months),
                datasets: [{
                    label: 'Pemasukan',
                    data: @json($monthlyRevenue),
                    borderColor: primary,
                    backgroundColor: revGradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: primary,
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: primary,
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: { duration: 1400, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0',
                        padding: 12, cornerRadius: 8, displayColors: false,
                        callbacks: { label: function(ctx) { return 'Rp ' + Number(ctx.raw).toLocaleString('id-ID'); } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { color: '#94a3b8', font: { size: 11 }, callback: function(v) { return 'Rp' + (v / 1000).toFixed(0) + 'k'; } } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } }
                }
            }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: { labels: ['Lunas', 'Belum'], datasets: [{ data: [{{ $paidCount }}, {{ $unpaidCount }}], backgroundColor: ['#10b981', '#f97316'], borderWidth: 0, hoverOffset: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', animation: { animateRotate: true, duration: 1200 },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 12, cornerRadius: 8 } }
            }
        });

        var pmLabels = [], pmData = [], pmColors = [];
        var colorMap = { cash: '#10b981', transfer: '#3b82f6', qris: '#8b5cf6', midtrans: '#f59e0b' };
        var pmLegendHtml = '';
        @foreach($paymentMethods as $pm)
        pmLabels.push('{{ ucfirst($pm->payment_method) }}'); pmData.push({{ $pm->count }}); pmColors.push(colorMap['{{ $pm->payment_method }}'] || '#94a3b8');
        pmLegendHtml += '<span><span class="v4-legend-dot" style="background:' + (colorMap['{{ $pm->payment_method }}'] || '#94a3b8') + ';"></span>{{ ucfirst($pm->payment_method) }} ({{ $pm->count }})</span>';
        @endforeach
        var pmLegendEl = document.getElementById('pmLegend');
        if (pmLegendEl && pmLegendHtml) pmLegendEl.innerHTML = pmLegendHtml;
        if (pmLabels.length === 0) { pmLabels = ['Belum ada data']; pmData = [1]; pmColors = ['#e2e8f0']; }
        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'doughnut',
            data: { labels: pmLabels, datasets: [{ data: pmData, backgroundColor: pmColors, borderWidth: 0, hoverOffset: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', animation: { animateRotate: true, duration: 1200 },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 } }
            }
        });

        function updateClock() {
            var now = new Date();
            var el = document.getElementById('clock');
            if (el) el.textContent = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ':' + now.getSeconds().toString().padStart(2,'0');
        }
        updateClock(); setInterval(updateClock, 1000);

        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
        var sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: '&copy; Esri' });
        var map = L.map('map', { layers: [sat] }).setView([-6.476, 106.014], 15);
        L.control.layers({ 'Satelit': sat, 'Street': osm }).addTo(map);
        var odpsData = @json($odps);
        var markerBounds = [], seenIds = new Set();
        var icons = {
            green: L.divIcon({ className: 'custom-marker', html: '<div style="width:18px;height:18px;background:#059669;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>', iconSize: [18,18], iconAnchor: [9,9] }),
            red: L.divIcon({ className: 'custom-marker', html: '<div style="width:18px;height:18px;background:#dc2626;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>', iconSize: [18,18], iconAnchor: [9,9] })
        };
        function addMarker(odp, terpakai) {
            if (!odp.latitude || !odp.longitude || seenIds.has(odp.id)) return;
            seenIds.add(odp.id);
            var totalCapacity = odp.port_capacity ?? 16, isFull = terpakai >= totalCapacity;
            var marker = L.marker([odp.latitude, odp.longitude], { icon: isFull ? icons.red : icons.green }).addTo(map);
            var pct = totalCapacity > 0 ? Math.round((terpakai / totalCapacity) * 100) : 0;
            var loc = odp.odc?.nama_odc ?? odp.address ?? '-';
            var popupContent = '<div style="font-family:Inter,sans-serif;min-width:170px;"><div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;"><div style="width:10px;height:10px;border-radius:50%;background:' + (isFull ? '#dc2626' : '#059669') + ';"></div><h6 style="margin:0;font-weight:700;font-size:14px;color:#0f172a;">' + odp.name + '</h6></div><small style="color:#64748b;"><i class="fa-solid fa-server"></i> ' + loc + '</small><div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;"><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;"><span style="color:#475569;">Terpakai</span><span style="font-weight:600;">' + terpakai + '/' + totalCapacity + '</span></div><div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;"><div style="height:100%;width:' + pct + '%;background:' + (isFull ? '#dc2626' : '#059669') + ';border-radius:2px;"></div></div></div></div>';
            marker.bindPopup(popupContent, { className: 'custom-popup' });
            markerBounds.push([odp.latitude, odp.longitude]);
        }
        odpsData.forEach(function(odp) { addMarker(odp, odp.port_used_actual ?? 0); });
        if (markerBounds.length > 0) { map.fitBounds(L.latLngBounds(markerBounds), { padding: [40, 40] }); }
    });
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush
