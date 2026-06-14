<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'RabegNet Billing — Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, monitoring MikroTik, dan manajemen OLT. Solusi operasional ISP yang rapi dan terintegrasi.')">
    <meta name="keywords" content="@yield('meta_keywords', 'billing ISP, RabegNet, tagihan internet, pembayaran online, voucher WiFi, MikroTik, OLT, ISP management,印尼, billing system')">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Indonesian">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="RabegNet Billing">
    <meta property="og:title" content="@yield('title', config('app.name', 'RabegNet'))">
    <meta property="og:description" content="@yield('meta_description', 'Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, dan monitoring jaringan.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.svg') }}">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', config('app.name', 'RabegNet'))">
    <meta name="twitter:description" content="@yield('meta_description', 'Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, dan monitoring jaringan.')">

    <!-- Canonical -->
    <link rel="canonical" href="{{ url()->current() }}">

    <title>@yield('title', config('app.name', 'RabegNet')) ~ RabegNet Billing</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif

    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @if(!request()->routeIs('login') && !request()->routeIs('register') && !request()->routeIs('portal.*') && !request()->is('/'))
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center gap-3">
                <div class="d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(255,255,255,0.1);border-radius:10px;">
                    <i class="fa-solid fa-bolt" style="color:#60a5fa;font-size:1.1rem;"></i>
                </div>
                <div>
                    <h4 class="mb-0">RabegNet</h4>
                    <small style="font-size:10px;color:rgba(255,255,255,0.35);font-weight:500;letter-spacing:0.05em;display:block;margin-top:-2px;">BILLING SYSTEM</small>
                </div>
            </div>

            <div class="sidebar-menu">
                <ul class="list-unstyled components mt-2">
                    <p>Dasbor Utama</p>
                    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-gauge-high"></i><span>Dasbor</span></a>
                    </li>
                    <li class="{{ request()->routeIs('mikrotik.*') ? 'active' : '' }}">
                        <a href="{{ route('mikrotik.dashboard') }}"><i class="fa-solid fa-router"></i><span>Monitor MikroTik</span></a>
                    </li>
                    <li class="{{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
                        <a href="{{ route('monitoring.index') }}"><i class="fa-solid fa-chart-line"></i><span>Monitoring BW</span></a>
                    </li>
                    <li class="{{ request()->routeIs('olt.monitoring') ? 'active' : '' }}">
                        <a href="{{ route('olt.monitoring') }}"><i class="fa-solid fa-tower-broadcast"></i><span>Monitor Gangguan</span></a>
                    </li>

                    <p>Layanan</p>
                    <li class="{{ request()->routeIs('vouchers.*') ? 'active' : '' }}">
                        <a href="{{ route('vouchers.index') }}"><i class="fa-solid fa-ticket"></i><span>Voucher WiFi</span></a>
                    </li>
                    <li class="{{ request()->routeIs('invoices.*') || request()->routeIs('invoice.*') ? 'active' : '' }}">
                        <a href="{{ route('invoices.index') }}"><i class="fa-solid fa-file-invoice"></i><span>Tagihan</span></a>
                    </li>
                    <li class="{{ request()->routeIs('packages.*') ? 'active' : '' }}">
                        <a href="{{ route('packages.index') }}"><i class="fa-solid fa-wifi"></i><span>Paket Internet</span></a>
                    </li>
                    <li class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <a href="{{ route('reports.index') }}"><i class="fa-solid fa-chart-pie"></i><span>Laporan</span></a>
                    </li>
                    <li class="{{ request()->routeIs('backups.*') ? 'active' : '' }}">
                        <a href="{{ route('backups.index') }}"><i class="fa-solid fa-floppy-disk"></i><span>Backup</span></a>
                    </li>
                    <li class="{{ request()->routeIs('logs.*') ? 'active' : '' }}">
                        <a href="{{ route('logs.index') }}"><i class="fa-solid fa-terminal"></i><span>Log Sistem</span></a>
                    </li>

                    <p>Infrastruktur & Billing</p>
                    <li class="{{ request()->routeIs('customers.*') || request()->routeIs('customer.edit', 'customer.update', 'customer.destroy', 'customer.suspend', 'customer.activate') ? 'active' : '' }}">
                        <a href="{{ route('customers.index') }}"><i class="fa-solid fa-users"></i><span>Pelanggan</span></a>
                    </li>
                    <li class="{{ request()->routeIs('customer.create') ? 'active' : '' }}">
                        <a href="{{ route('customer.create') }}"><i class="fa-solid fa-user-plus"></i><span>Pasang Baru</span></a>
                    </li>
                    <li class="{{ request()->routeIs('distribution.*') ? 'active' : '' }}">
                        <a href="{{ route('distribution.index') }}"><i class="fa-solid fa-map-location-dot"></i><span>Distribusi ODP</span></a>
                    </li>
                    <li class="{{ request()->routeIs('olt.*') ? 'active' : '' }}">
                        <a href="{{ route('olt.index') }}"><i class="fa-solid fa-tower-cell"></i><span>OLT</span></a>
                    </li>
                    <li class="{{ request()->routeIs('olt.map') ? 'active' : '' }}">
                        <a href="{{ route('olt.map') }}"><i class="fa-solid fa-map-location-dot"></i><span>Map OLT</span></a>
                    </li>
                    <li class="{{ request()->routeIs('onu.search') ? 'active' : '' }}">
                        <a href="{{ route('onu.search') }}"><i class="fa-solid fa-search"></i><span>Cari ONU</span></a>
                    </li>
                    <li class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i><span>Pengaturan</span></a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-footer">
                @auth
                <div style="display:flex;align-items:center;gap:10px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:12px;padding:8px 0;">
                    <div style="width:28px;height:28px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-user" style="color:#fff;font-size:11px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;color:rgba(255,255,255,0.85);">{{ Auth::user()->name }}</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.35);">{{ Auth::user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:rgba(255,255,255,0.35);padding:4px;" title="Keluar">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
                @else
                <div style="display:flex;gap:8px;">
                    <a href="{{ route('login') }}" style="flex:1;text-align:center;padding:8px;border-radius:8px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);text-decoration:none;font-size:12px;font-weight:500;">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>Masuk
                    </a>
                    <a href="{{ route('register') }}" style="flex:1;text-align:center;padding:8px;border-radius:8px;background:var(--primary);color:#fff;text-decoration:none;font-size:12px;font-weight:500;">
                        <i class="fa-solid fa-user-plus me-1"></i>Daftar
                    </a>
                </div>
                @endauth
            </div>
        </nav>
        @endif

        <div id="content">
            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('scripts')
</body>
</html>
