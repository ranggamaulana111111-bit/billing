<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        try {
            $metaCompany = \App\Models\Setting::get('company_name') ?: config('app.name', 'ALKONEKbill');
        } catch (\Exception $e) {
            $metaCompany = config('app.name', 'ALKONEKbill');
        }
        $metaBrand = $metaCompany . ' Billing';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', $metaBrand . ' — Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, monitoring MikroTik, dan manajemen OLT. Solusi operasional ISP yang rapi dan terintegrasi.')">
    <meta name="keywords" content="@yield('meta_keywords', 'billing ISP, ' . $metaCompany . ', tagihan internet, pembayaran online, voucher WiFi, MikroTik, OLT, ISP management, billing system')">
    <meta name="robots" content="index, follow">
    <meta name="google-site-verification" content="8psHkpnmvIBG7wwjyZBspYTvVtRchzNfJBSdwNSwCo0" />
    <meta name="language" content="Indonesian">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $metaBrand }}">
    <meta property="og:title" content="@yield('title', $metaCompany)">
    <meta property="og:description" content="@yield('meta_description', 'Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, dan monitoring jaringan.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.svg') }}">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $metaCompany)">
    <meta name="twitter:description" content="@yield('meta_description', 'Sistem billing ISP untuk manajemen pelanggan, tagihan, pembayaran online, voucher WiFi, dan monitoring jaringan.')">

    <!-- Canonical -->
    <link rel="canonical" href="{{ url()->current() }}">

    <title>@yield('title', $metaCompany) ~ {{ $metaBrand }}</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @if(!request()->routeIs('login') && !request()->routeIs('register') && !request()->routeIs('portal.*') && !request()->is('/'))
        <nav id="sidebar">
            @php
                try {
                    $sidebarLogo = \App\Models\Setting::get('company_logo');
                    $sidebarShortName = \App\Models\Setting::get('company_short_name');
                    $sidebarCompanyName = \App\Models\Setting::get('company_name');
                } catch (\Exception $e) {
                    $sidebarLogo = null;
                    $sidebarShortName = null;
                    $sidebarCompanyName = null;
                }
            @endphp
            <div class="sidebar-header d-flex align-items-center gap-3">
                <img src="{{ $sidebarLogo ? asset('storage/' . $sidebarLogo) : asset('images/logo.png') }}" alt="Logo" style="height:40px;width:auto;border-radius:8px;background:linear-gradient(135deg,#2563eb,#7c3aed);padding:2px;">
                <div style="min-width:0;overflow:hidden;">
                    <h4 class="mb-0" style="font-size:1.2rem;font-weight:800;color:#ffffff;letter-spacing:0;line-height:1.05;white-space:nowrap;">{{ auth()->user()->role === 'noc' ? 'PROVISION NOC' : ($sidebarShortName ?: 'ALKONEKbill') }}</h4>
                    <small style="font-size:8px;color:rgba(255,255,255,0.35);font-weight:500;letter-spacing:0.22em;display:block;margin-top:-1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ auth()->user()->role === 'noc' ? 'PT.Alkonek Network Access' : ($sidebarCompanyName ?: 'PT. ALKONEK NETWORK ACCESS') }}</small>
                </div>
            </div>

            <div class="sidebar-menu">
                @if(in_array(auth()->user()->role, ['admin', 'superadmin']))
                {{-- ADMIN SIDEBAR — full system --}}
                <ul class="list-unstyled components mt-2">
                    <p>Dashboard Utama</p>
                    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                    </li>
                    <li class="{{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
                        <a href="{{ route('monitoring.index') }}"><i class="fa-solid fa-signal"></i><span>Monitoring</span></a>
                    </li>
                    {{-- <li class="{{ request()->routeIs('onu-health.topology') ? 'active' : '' }}">
                        <a href="{{ route('onu-health.topology') }}"><i class="fa-solid fa-diagram-project"></i><span>Live Network Topology</span></a>
                    </li> --}}
                    <li class="{{ request()->routeIs('incidents.*') ? 'active' : '' }}">
                        @php
                            $navAlerts = \App\Models\Incident::active()->count()
                                + \App\Models\Invoice::where('payment_status','unpaid')
                                    ->whereHas('customer', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date','<',now()))
                                    ->count();
                        @endphp
                        <a href="{{ route('incidents.index') }}">
                            <i class="fa-solid fa-bell"></i><span>Alarm Center</span>
                        </a>
                    </li>

                    {{-- <p>👥 Customer Management</p> --}}
                    <p>👥 Customer</p>
                    <li class="{{ request()->routeIs('customers.*') || request()->routeIs('customer.create') || request()->routeIs('customer.edit') || request()->routeIs('customer.store') || request()->routeIs('customer.update') || request()->routeIs('customer.destroy') || request()->routeIs('customers.activation') || request()->routeIs('customers.suspended') || request()->routeIs('customers.history') || request()->routeIs('customer.create-existing') || request()->routeIs('customer.store-existing') ? 'active' : '' }}">
                        <a href="#customerMgmtMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-users"></i><span>Customer Management</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="customerMgmtMenu" class="collapse {{ request()->routeIs('customers.*') || request()->routeIs('customer.create') || request()->routeIs('customer.edit') || request()->routeIs('customer.store') || request()->routeIs('customer.update') || request()->routeIs('customer.destroy') || request()->routeIs('customers.activation') || request()->routeIs('customers.suspended') || request()->routeIs('customers.history') || request()->routeIs('customer.create-existing') || request()->routeIs('customer.store-existing') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customers.index') && !request()->routeIs('customers.activation') && !request()->routeIs('customers.suspended') && !request()->routeIs('customers.history') ? 'active py-1' : 'py-1' }}" href="{{ route('customers.index') }}">
                                        <i class="fa-solid fa-users me-1"></i> Pelanggan
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customer.create') ? 'active py-1' : 'py-1' }}" href="{{ route('customer.create') }}">
                                        <i class="fa-solid fa-user-plus me-1"></i> Pasang Baru
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customer.create-existing') || request()->routeIs('customer.store-existing') ? 'active py-1' : 'py-1' }}" href="{{ route('customer.create-existing') }}">
                                        <i class="fa-solid fa-user-pen me-1"></i> Pelanggan Existing
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('packages.*') ? 'active py-1' : 'py-1' }}" href="{{ route('packages.index') }}">
                                        <i class="fa-solid fa-wifi me-1"></i> Paket Internet
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customers.activation') ? 'active py-1' : 'py-1' }}" href="{{ route('customers.activation') }}">
                                        <i class="fa-solid fa-user-check me-1"></i> Aktivasi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customers.suspended') ? 'active py-1' : 'py-1' }}" href="{{ route('customers.suspended') }}">
                                        <i class="fa-solid fa-pause-circle me-1"></i> Suspend / Isolir
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('customers.history') ? 'active py-1' : 'py-1' }}" href="{{ route('customers.history') }}">
                                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Pelanggan
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- <p>💰 Billing</p> --}}
                    <li class="{{ request()->routeIs('invoices.*') || request()->routeIs('invoice.*') || request()->routeIs('payments.*') || request()->routeIs('payment.*') || request()->routeIs('payment-gateway.*') || request()->routeIs('reports.*') ? 'active' : '' }}">
                        <a href="#billingMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-coins"></i><span>Billing</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="billingMenu" class="collapse {{ request()->routeIs('invoices.*') || request()->routeIs('invoice.*') || request()->routeIs('payments.*') || request()->routeIs('payment.*') || request()->routeIs('payment-gateway.*') || request()->routeIs('reports.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('invoices.*') && request('status') !== 'unpaid' ? 'active py-1' : 'py-1' }}" href="{{ route('invoices.index') }}">
                                        <i class="fa-solid fa-file-invoice me-1"></i> Invoice
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('invoices.*') && request('status') === 'unpaid' ? 'active py-1' : 'py-1' }}" href="{{ route('invoices.index', ['status' => 'unpaid']) }}">
                                        <i class="fa-solid fa-file-circle-exclamation me-1"></i> Tagihan
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('payments.index') ? 'active py-1' : 'py-1' }}" href="{{ route('payments.index') }}">
                                        <i class="fa-solid fa-money-bill-wave me-1"></i> Pembayaran
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('payment-gateway.*') ? 'active py-1' : 'py-1' }}" href="{{ route('payment-gateway.index') }}">
                                        <i class="fa-solid fa-credit-card me-1"></i> Payment Gateway
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('reports.*') ? 'active py-1' : 'py-1' }}" href="{{ route('reports.index') }}">
                                        <i class="fa-solid fa-chart-line me-1"></i> Laporan Keuangan
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="{{ request()->routeIs('vouchers.*') || request()->routeIs('voucher-profiles.*') || request()->routeIs('voucher-templates.*') || request()->routeIs('onu-hotspot.*') || request()->routeIs('hotspot-customers.*') ? 'active' : '' }}">
                        <a href="#voucherMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-wifi"></i><span>Hotspot</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="voucherMenu" class="collapse {{ request()->routeIs('vouchers.*') || request()->routeIs('voucher-profiles.*') || request()->routeIs('voucher-templates.*') || request()->routeIs('onu-hotspot.*') || request()->routeIs('hotspot-customers.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('hotspot-customers.*') ? 'active py-1' : 'py-1' }}" href="{{ route('hotspot-customers.index') }}">
                                        <i class="fa-solid fa-user-plus me-1"></i> Pelanggan Hotspot
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('onu-hotspot.*') ? 'active py-1' : 'py-1' }}" href="{{ route('onu-hotspot.index') }}">
                                        <i class="fa-solid fa-tower-cell me-1"></i> ONU Hotspot
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('vouchers.index') ? 'active py-1' : 'py-1' }}" href="{{ route('vouchers.index') }}">
                                        <i class="fa-solid fa-ticket me-1"></i> Voucher WiFi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('voucher-profiles.index') ? 'active py-1' : 'py-1' }}" href="{{ route('voucher-profiles.index') }}">
                                        <i class="fa-solid fa-id-card me-1"></i> Voucher Profiles
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('vouchers.report') ? 'active py-1' : 'py-1' }}" href="{{ route('vouchers.report') }}">
                                        <i class="fa-solid fa-chart-bar me-1"></i> Laporan Voucher
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    {{-- PPPoE menu hidden (MikroTik) --
                    <li class="{{ request()->routeIs('noc.internet.pppsecret') || request()->routeIs('noc.internet.pppprofile') ? 'active' : '' }}">
                        <a href="#pppoeMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-plug"></i><span>PPPoE</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="pppoeMenu" class="collapse {{ request()->routeIs('noc.internet.pppsecret') || request()->routeIs('noc.internet.pppprofile') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.internet.pppsecret') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.internet.pppsecret') }}">
                                        <i class="fa-solid fa-user-check me-1"></i> PPPoE Secrets
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.internet.pppprofile') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.internet.pppprofile') }}">
                                        <i class="fa-solid fa-layer-group me-1"></i> PPPoE Profiles
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    --}}

                    <p>📡 Infrastruktur</p>
                    {{-- OLT menu hidden --
                    <li class="{{ request()->routeIs('olt.*') || request()->routeIs('distribution.*') || request()->routeIs('odp.show') || request()->routeIs('onu.*') || request()->routeIs('noc.pon-manager') || request()->routeIs('onu-health.*') ? 'active' : '' }}">
                        <a href="#oltMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-tower-cell"></i><span>OLT</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="oltMenu" class="collapse {{ request()->routeIs('olt.*') || request()->routeIs('distribution.*') || request()->routeIs('odp.show') || request()->routeIs('onu.*') || request()->routeIs('noc.pon-manager') || request()->routeIs('onu-health.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('olt.index') ? 'active py-1' : 'py-1' }}" href="{{ route('olt.index') }}">
                                        <i class="fa-solid fa-gauge-high me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('odp.show') ? 'active py-1' : 'py-1' }}" href="{{ route('distribution.index') }}">
                                        <i class="fa-solid fa-circle-dot me-1"></i> ODP
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('distribution.*') && !request()->routeIs('distribution.index') ? 'active py-1' : 'py-1' }}" href="{{ route('distribution.index') }}">
                                        <i class="fa-solid fa-map-location-dot me-1"></i> ODP Distribusi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('olt.map') ? 'active py-1' : 'py-1' }}" href="{{ route('olt.map') }}">
                                        <i class="fa-solid fa-map me-1"></i> ODP Map
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.pon-manager') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.pon-manager') }}">
                                        <i class="fa-solid fa-diagram-project me-1"></i> PON
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('onu.search') ? 'active py-1' : 'py-1' }}" href="{{ route('onu.search') }}">
                                        <i class="fa-solid fa-microchip me-1"></i> ONU
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('olt.monitoring') ? 'active py-1' : 'py-1' }}" href="{{ route('olt.monitoring') }}">
                                        <i class="fa-solid fa-chart-line me-1"></i> Optical Monitoring
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('onu-health.dashboard') || request()->routeIs('onu-health.detail') || request()->routeIs('onu-health.diagnosis') ? 'active py-1' : 'py-1' }}" href="{{ route('onu-health.dashboard') }}">
                                        <i class="fa-solid fa-heart-pulse me-1"></i> Health Score
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    --}}

                    {{-- MikroTik Center menu hidden --
                    <li class="{{ request()->routeIs('noc.interface-center.*') || request()->routeIs('noc.mikrotik.*') || request()->routeIs('noc.mikrotik-devices.*') || request()->routeIs('noc.sync.*') || request()->routeIs('noc.config.*') || request()->routeIs('noc.traffic-analyzer') || request()->routeIs('noc.internet.*') ? 'active' : '' }}">
                        <a href="#mikrotikCenterSubmenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-network-wired"></i><span>MikroTik Center</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="mikrotikCenterSubmenu" class="collapse {{ request()->routeIs('noc.interface-center.*') || request()->routeIs('noc.mikrotik.*') || request()->routeIs('noc.mikrotik-devices.*') || request()->routeIs('noc.sync.*') || request()->routeIs('noc.config.*') || request()->routeIs('noc.traffic-analyzer') || request()->routeIs('noc.internet.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.mikrotik.dashboard') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.mikrotik.dashboard') }}">
                                        <i class="fa-solid fa-chart-line me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.traffic-analyzer') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.traffic-analyzer') }}">
                                        <i class="fa-solid fa-magnifying-glass-chart me-1"></i> Traffic Analyzer
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.internet.active') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.internet.active') }}">
                                        <i class="fa-solid fa-plug me-1"></i> Sesi Aktif
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.internet.hotspot') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.internet.hotspot') }}">
                                        <i class="fa-solid fa-wifi me-1"></i> Hotspot Server
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.internet.monitoring') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.internet.monitoring') }}">
                                        <i class="fa-solid fa-signal me-1"></i> Monitoring Center
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.interface-center.*') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.interface-center.dashboard') }}">
                                        <i class="fa-solid fa-network-wired me-1"></i> Interface Center
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.mikrotik-devices.*') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.mikrotik-devices.index') }}">
                                        <i class="fa-solid fa-server me-1"></i> Device Manager
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.sync.*') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.sync.dashboard') }}">
                                        <i class="fa-solid fa-rotate me-1"></i> Config Sync
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.config.*') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.config.modules') }}">
                                        <i class="fa-solid fa-sliders me-1"></i> Config Center
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    --}}

                    {{-- GenieACS menu hidden --
                    <li class="{{ request()->routeIs('noc.genieacs*') ? 'active' : '' }}">
                        <a href="#genieacsMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-satellite-dish"></i><span>GenieACS</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="genieacsMenu" class="collapse {{ request()->routeIs('noc.genieacs*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.genieacs') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.genieacs') }}">
                                        <i class="fa-solid fa-gauge-high me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.genieacs.devices') || request()->routeIs('noc.genieacs.device-detail') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.genieacs.devices') }}">
                                        <i class="fa-solid fa-hard-drive me-1"></i> Devices
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.genieacs.presets') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.genieacs.presets') }}">
                                        <i class="fa-solid fa-file-code me-1"></i> Presets
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.genieacs.faults') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.genieacs.faults') }}">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Faults
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.genieacs.settings') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.genieacs.settings') }}">
                                        <i class="fa-solid fa-gear me-1"></i> Settings
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    --}}

                    {{-- Server menu hidden (NOC)
                    <li class="{{ request()->routeIs('noc.linux-server') || request()->routeIs('noc.dns') || request()->routeIs('noc.speedtest') ? 'active' : '' }}">
                        <a href="#serverMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-server"></i><span>Server</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="serverMenu" class="collapse {{ request()->routeIs('noc.linux-server') || request()->routeIs('noc.dns') || request()->routeIs('noc.speedtest') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.linux-server') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.linux-server') }}">
                                        <i class="fa-solid fa-server me-1"></i> Linux Server
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.dns') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.dns') }}">
                                        <i class="fa-solid fa-globe me-1"></i> DNS Server
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.speedtest') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.speedtest') }}">
                                        <i class="fa-solid fa-gauge-simple me-1"></i> Speedtest Server
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    --}}

                    <li class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                        <a href="#inventoryMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-boxes-stacked"></i><span>Laporan Aset</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="inventoryMenu" class="collapse {{ request()->routeIs('inventory.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('inventory.items*') ? 'active py-1' : 'py-1' }}" href="{{ route('inventory.items') }}">
                                        <i class="fa-solid fa-box me-1"></i> Barang
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('inventory.masuk*') ? 'active py-1' : 'py-1' }}" href="{{ route('inventory.masuk') }}">
                                        <i class="fa-solid fa-arrow-down me-1"></i> Barang Masuk
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('inventory.keluar*') ? 'active py-1' : 'py-1' }}" href="{{ route('inventory.keluar') }}">
                                        <i class="fa-solid fa-arrow-up me-1"></i> Barang Keluar
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('inventory.laporan-aset*') ? 'active py-1' : 'py-1' }}" href="{{ route('inventory.laporan-aset') }}">
                                        <i class="fa-solid fa-chart-pie me-1"></i> Laporan Aset
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- <p>📜 System</p> --}}
                    <li class="{{ request()->routeIs('logs.*') || request()->routeIs('settings.*') || request()->routeIs('mikrotik-routers.*') || request()->routeIs('qos.*') ? 'active' : '' }}">
                        <a href="#systemMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-gear"></i><span>System</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="systemMenu" class="collapse {{ request()->routeIs('logs.*') || request()->routeIs('settings.*') || request()->routeIs('mikrotik-routers.*') || request()->routeIs('qos.*') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                {{-- Smart QoS menu hidden (MikroTik)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('qos.*') ? 'active py-1' : 'py-1' }}" href="{{ route('qos.health') }}">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Smart QoS
                                    </a>
                                </li>
                                --}}
                                {{-- Kelola Router menu hidden (MikroTik)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('mikrotik-routers.*') ? 'active py-1' : 'py-1' }}" href="{{ route('mikrotik-routers.index') }}">
                                        <i class="fa-solid fa-server me-1"></i> Kelola Router
                                    </a>
                                </li>
                                --}}
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('settings.integrations*') ? 'active py-1' : 'py-1' }}" href="{{ route('settings.integrations') }}">
                                        <i class="fa-solid fa-plug-circle-plus me-1"></i> Integrasi MikroTik &amp; OLT
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('logs.*') ? 'active py-1' : 'py-1' }}" href="{{ route('logs.index') }}">
                                        <i class="fa-solid fa-terminal me-1"></i> Log System
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('settings.users*') ? 'active py-1' : 'py-1' }}" href="{{ route('settings.users') }}">
                                        <i class="fa-solid fa-users-gear me-1"></i> User
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('settings.users*') ? 'active py-1' : 'py-1' }}" href="{{ route('settings.users') }}">
                                        <i class="fa-solid fa-user-shield me-1"></i> Hak Akses
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('settings.index') ? 'active py-1' : 'py-1' }}" href="{{ route('settings.index') }}">
                                        <i class="fa-solid fa-gear me-1"></i> Pengaturan
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- NOC CONTROL CENTER — disabled, items moved to top-level --}}
                </ul>
                @elseif(auth()->user()->role === 'noc')
                {{-- NOC SIDEBAR --}}
                <ul class="list-unstyled components mt-2">
                    <p>NOC Control Center</p>
                    <li class="{{ request()->routeIs('noc.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('noc.dashboard') }}"><i class="fa-solid fa-satellite-dish"></i><span>NOC Dashboard</span></a>
                    </li>
                    <li class="{{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
                        <a href="{{ route('monitoring.index') }}"><i class="fa-solid fa-signal"></i><span>Monitoring</span></a>
                    </li>
                    <li class="{{ request()->routeIs('incidents.*') ? 'active' : '' }}">
                        <a href="{{ route('incidents.index') }}"><i class="fa-solid fa-triangle-exclamation"></i><span>Incident / Alarm</span></a>
                    </li>

                    <li class="{{ request()->routeIs('noc.features.*') ? 'active' : '' }}">
                        <a href="{{ route('noc.features.map') }}" target="_blank"><i class="fa-solid fa-earth-asia"></i><span>Panel FTTH</span></a>
                    </li>

                    <p>Manajemen Jaringan</p>
                    <li class="{{ request()->routeIs('noc.automation.*') ? 'active' : '' }}">
                        <a href="{{ route('noc.automation.index') }}"><i class="fa-solid fa-gears"></i><span>Automation</span></a>
                    </li>
                    <li class="{{ request()->routeIs('noc.netconfig.*') ? 'active' : '' }}">
                        <a href="{{ route('noc.netconfig.dashboard') }}"><i class="fa-solid fa-sliders"></i><span>Network Config</span></a>
                    </li>
                    <li class="{{ request()->routeIs('noc.security.*') ? 'active' : '' }}">
                        <a href="{{ route('noc.security.dashboard') }}"><i class="fa-solid fa-shield-halved"></i><span>Security Policy</span></a>
                    </li>
                    <li class="{{ request()->routeIs('noc.traffic_eng.*') ? 'active' : '' }}">
                        <a href="{{ route('noc.traffic_eng.dashboard') }}"><i class="fa-solid fa-chart-line"></i><span>Traffic Engineering</span></a>
                    </li>

                    <li class="{{ request()->routeIs('noc.linux-server') || request()->routeIs('noc.dns') || request()->routeIs('noc.speedtest') ? 'active' : '' }}">
                        <a href="#nocServerMenu" data-bs-toggle="collapse">
                            <i class="fa-solid fa-server"></i><span>Server</span>
                            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.6rem;"></i>
                        </a>
                        <div id="nocServerMenu" class="collapse {{ request()->routeIs('noc.linux-server') || request()->routeIs('noc.dns') || request()->routeIs('noc.speedtest') ? 'show' : '' }}">
                            <ul class="nav flex-column ms-3 mt-1" style="font-size:0.85rem;">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.linux-server') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.linux-server') }}">
                                        <i class="fa-solid fa-server me-1"></i> Linux Server
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.dns') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.dns') }}">
                                        <i class="fa-solid fa-globe me-1"></i> DNS Server
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('noc.speedtest') ? 'active py-1' : 'py-1' }}" href="{{ route('noc.speedtest') }}">
                                        <i class="fa-solid fa-gauge-simple me-1"></i> Speedtest Server
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
                @else
                {{-- TEKNISI SIDEBAR — empty --}}
                <ul class="list-unstyled components mt-2">
                </ul>
                @endif
            </div>

            <div class="sidebar-footer" style="display:flex;flex-direction:column;">
                <div class="dev-credit" style="text-align:center;font-size:9px;color:rgba(255,255,255,0.35);padding-top:8px;margin-top:auto;line-height:1.6;white-space:nowrap;overflow:hidden;text-overflow:clip;">
                    Developed by: <a href="https://www.instagram.com/rangga.mrw" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;text-decoration:none;">Rangga</a>
                    <span style="color:rgba(255,255,255,0.25);"> · Refactor: </span><a href="https://www.instagram.com/faisal_alqodar/" target="_blank" rel="noopener" style="color:var(--primary);font-weight:400;text-decoration:none;">Alko</a>
                </div>
            </div>
            <div class="sidebar-resizer" id="sidebarResizer" title="Geser untuk mengubah lebar sidebar"></div>
        </nav>
        @endif

        <div class="content-area">
            @auth
            <div class="top-navbar">
                <div class="top-navbar-right">
                    <div class="top-navbar-user">
                        <div class="top-navbar-avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="top-navbar-info">
                            <span class="top-navbar-name">{{ Auth::user()->name }}</span>
                            <span class="top-navbar-role">{{ ucfirst(Auth::user()->role) }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="top-navbar-logout" title="Keluar">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
            <div id="content">
                @yield('content')
            </div>
        </div>
    </div>

    <script>
    (function() {
        var sidebar = document.getElementById('sidebar');
        var resizer = document.getElementById('sidebarResizer');
        if (!sidebar || !resizer) return;

        var MIN = 70;
        var SNAP = 100;
        var MAX = 420;
        var DEFAULT = 270;

        function applyWidth(w) {
            sidebar.style.setProperty('--sidebar-width', w + 'px');
        }
        function setCollapsed(state) {
            sidebar.classList.toggle('sidebar-collapsed', !!state);
        }

        var saved = parseInt(localStorage.getItem('sidebar_width'), 10);
        if (!isNaN(saved) && saved >= MIN && saved <= MAX) {
            applyWidth(saved);
            setCollapsed(saved <= SNAP);
        } else {
            applyWidth(DEFAULT);
        }

        function onStart(e) {
            if (e.cancelable) e.preventDefault();
            var startX = e.touches ? e.touches[0].clientX : e.clientX;
            var startW = sidebar.getBoundingClientRect().width;
            sidebar.classList.add('sidebar-resizing');
            resizer.classList.add('dragging');
            document.body.style.cursor = 'ew-resize';
            document.body.style.userSelect = 'none';

            function onMove(ev) {
                var x = ev.touches ? ev.touches[0].clientX : ev.clientX;
                var w = Math.min(MAX, Math.max(MIN, startW + (x - startX)));
                if (w <= SNAP) {
                    setCollapsed(true);
                    applyWidth(MIN);
                } else {
                    setCollapsed(false);
                    applyWidth(w);
                }
            }
            function onEnd() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onEnd);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
                sidebar.classList.remove('sidebar-resizing');
                resizer.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem('sidebar_width', Math.round(sidebar.getBoundingClientRect().width));
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchmove', onMove, { passive: true });
            document.addEventListener('touchend', onEnd);
        }

        resizer.addEventListener('mousedown', onStart);
        resizer.addEventListener('touchstart', onStart, { passive: true });
    })();
    document.querySelectorAll('.mon-table-wrap .dropdown').forEach(function(dd) {
        dd.addEventListener('show.bs.dropdown', function() {
            var menu = this.querySelector('.dropdown-menu');
            if (!menu) return;
            this._menuParent = menu.parentNode;
            this._menuNext = menu.nextSibling;
            document.body.appendChild(menu);
        });
        dd.addEventListener('hidden.bs.dropdown', function() {
            var menu = this.querySelector('.dropdown-menu');
            if (!menu) return;
            if (this._menuNext && this._menuNext.parentNode) {
                this._menuParent.insertBefore(menu, this._menuNext);
            } else if (this._menuParent) {
                this._menuParent.appendChild(menu);
            }
            this._menuNext = null;
            this._menuParent = null;
        });
    });
    
    </script>
    @stack('scripts')
</body>
</html>
