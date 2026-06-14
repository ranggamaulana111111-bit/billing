@extends('layouts.app')

@section('title', 'RabegNet — Billing ISP, Tagihan Online, Manajemen Jaringan')

@section('meta_description', 'RabegNet Billing — sistem billing ISP all-in-one. Kelola pelanggan, tagihan, pembayaran online via Midtrans, voucher WiFi, monitoring MikroTik, dan manajemen OLT Huawei/ZTE/FiberHome. Solusi tepat untuk operasional ISP Anda.')
@section('meta_keywords', 'billing ISP, RabegNet, tagihan internet, pembayaran online ISP, manajemen pelanggan, voucher WiFi, monitoring MikroTik, manajemen OLT, sistem billing Indonesia, software ISP')

@section('content')
<div class="landing-shell">
    <div class="container py-4 py-lg-5">
        <nav class="landing-nav mb-5">
            <a href="{{ url('/') }}" class="brand-mark text-decoration-none">
                <span class="brand-icon"><i class="fa-solid fa-bolt"></i></span>
                <span>
                    <strong>RabegNet</strong>
                    <small>Billing ISP</small>
                </span>
            </a>
            <div class="d-flex gap-2">
                <a href="{{ route('portal.index') }}" class="btn btn-light btn-sm px-3">Portal Pelanggan</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm px-3">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-3">Masuk</a>
                @endauth
            </div>
        </nav>

        <div class="row align-items-center g-5 landing-hero">
            <div class="col-lg-6">
                <div class="hero-badge mb-3">
                    <i class="fa-solid fa-signal me-2"></i>Satu panel untuk billing, voucher, dan MikroTik
                </div>
                <h1 class="hero-title mb-3">Operasional ISP lebih rapi dari pelanggan sampai pembayaran.</h1>
                <p class="hero-copy mb-4">
                    Kelola paket internet, generate tagihan, catat pembayaran, cetak voucher WiFi, pantau ODP, dan hubungkan pembayaran online dalam satu sistem RabegNet Billing.
                </p>

                <div class="d-flex flex-wrap gap-3 mb-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-4">
                            <i class="fa-solid fa-gauge-high me-2"></i>Buka Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Masuk Admin
                        </a>
                        <a href="{{ route('portal.index') }}" class="btn btn-outline-light btn-lg px-4">
                            <i class="fa-solid fa-receipt me-2"></i>Cek Tagihan
                        </a>
                    @endauth
                </div>

                <div class="hero-metrics">
                    <div><strong>Midtrans</strong><span>Pembayaran online</span></div>
                    <div><strong>MikroTik</strong><span>Hotspot & PPPoE</span></div>
                    <div><strong>ODP</strong><span>Pemetaan distribusi</span></div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-panel">
                    <div class="hero-panel-header">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="hero-panel-body">
                        <div class="hero-stat hero-stat-blue">
                            <i class="fa-solid fa-users"></i>
                            <div><strong>128</strong><span>Pelanggan aktif</span></div>
                        </div>
                        <div class="hero-stat hero-stat-green">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <div><strong>Rp 18,4 jt</strong><span>Pemasukan bulan ini</span></div>
                        </div>
                        <div class="hero-stat hero-stat-orange">
                            <i class="fa-solid fa-ticket"></i>
                            <div><strong>320</strong><span>Voucher siap cetak</span></div>
                        </div>
                        <div class="hero-chart">
                            <div style="height:42%"></div>
                            <div style="height:70%"></div>
                            <div style="height:54%"></div>
                            <div style="height:86%"></div>
                            <div style="height:64%"></div>
                            <div style="height:92%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 py-5">
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <i class="fa-solid fa-users"></i>
                    <h5>Data Pelanggan Terpusat</h5>
                    <p>Profil pelanggan, paket, status layanan, nomor WA, email, dan titik ODP dalam satu halaman kerja.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <h5>Billing Lebih Cepat</h5>
                    <p>Buat tagihan manual atau massal, tandai lunas, kirim reminder, dan cetak invoice PDF.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <i class="fa-solid fa-router"></i>
                    <h5>Siap untuk Jaringan</h5>
                    <p>Monitoring MikroTik, PPPoE, queue, voucher hotspot, backup, dan distribusi ODP.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
