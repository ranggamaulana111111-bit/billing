@extends('layouts.app')

@section('title', 'ALKONEK — PT. Alkonek Network Access, Internet RT/RW Net & Reseller Telekomunikasi')

@section('meta_description', 'PT. Alkonek Network Access — penyedia jasa internet RT/RW Net berbasis fiber optik dan reseller jasa telekomunikasi. Internet cepat, stabil, dan terjangkau untuk rumah, perumahan, dan kawasan RT/RW.')
@section('meta_keywords', 'internet RT RW net, reseller telekomunikasi, PT Alkonek Network Access, internet fiber optik, wifi perumahan, billing ISP')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<style>
    html { scroll-behavior: smooth; }

    /* ── Landing sections ── */
    .lp-section { padding: 48px 0; }
    .lp-eyebrow {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 0.6rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase;
        color: #93c5fd;
    }
    .lp-title {
        max-width: 760px;
        font-size: clamp(1.3rem, 2.2vw, 1.7rem);
        font-weight: 900; letter-spacing: -0.03em; line-height: 1.12;
        color: #fff;
    }
    .lp-lead {
        max-width: 640px; color: rgba(255,255,255,0.62); font-size: 0.88rem; line-height: 1.7;
    }
    .lp-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.14), transparent); border: 0; opacity: 1; }

    .stat-box {
        padding: 16px 14px; border: 1px solid rgba(255,255,255,0.10); border-radius: 16px;
        background: rgba(255,255,255,0.06); text-align: center;
    }
    .stat-box strong { display: block; font-size: 1.15rem; font-weight: 900; letter-spacing: -0.02em; background: linear-gradient(135deg,#fff,#93c5fd); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .stat-box span { color: rgba(255,255,255,0.55); font-size: 0.72rem; }

    .about-card {
        padding: 24px; border: 1px solid rgba(255,255,255,0.10); border-radius: 18px;
        background: rgba(255,255,255,0.06); backdrop-filter: blur(14px);
    }
    .about-card p { color: rgba(255,255,255,0.66); line-height: 1.65; font-size: 0.9rem; }
    .about-point { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px; }
    .about-point i { margin-top: 2px; color: #60a5fa; font-size: 0.9rem; }
    .about-point span { color: rgba(255,255,255,0.72); font-size: 0.84rem; line-height: 1.5; }

    .service-card {
        height: 100%; padding: 22px 20px; border: 1px solid rgba(255,255,255,0.10);
        border-radius: 18px; background: rgba(255,255,255,0.07); backdrop-filter: blur(14px);
        transition: transform 0.25s var(--ease-out), border-color 0.25s var(--ease-out), background 0.25s var(--ease-out);
    }
    .service-card:hover { transform: translateY(-6px); border-color: rgba(96,165,250,0.45); background: rgba(255,255,255,0.09); }
    .service-icon {
        width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.1rem; color: #fff; margin-bottom: 14px;
        background: linear-gradient(135deg, #2563eb, #6366f1);
        box-shadow: 0 12px 28px rgba(37,99,235,0.30);
    }
    .service-card h5 { font-weight: 800; letter-spacing: -0.02em; font-size: 1rem; }
    .service-card p { margin: 0; color: rgba(255,255,255,0.6); line-height: 1.6; font-size: 0.82rem; }

    .coverage-box {
        padding: 24px; border: 1px solid rgba(255,255,255,0.10); border-radius: 18px;
        background: radial-gradient(circle at 80% 10%, rgba(37,99,235,0.25), transparent 16rem), rgba(255,255,255,0.05);
    }
    .coverage-tag {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 11px; border: 1px solid rgba(255,255,255,0.12); border-radius: 999px;
        background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.8);
        font-size: 0.78rem; font-weight: 600; margin: 0 8px 8px 0;
    }

    .contact-item { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
    .contact-icon {
        flex: 0 0 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 11px; font-size: 0.9rem; color: #fff;
        background: linear-gradient(135deg, #2563eb, #6366f1);
    }
    .contact-item strong { display: block; color: #fff; font-size: 0.85rem; }
    .contact-item span { color: rgba(255,255,255,0.6); font-size: 0.8rem; }

    .lp-footer { border-top: 1px solid rgba(255,255,255,0.10); padding: 28px 0 22px; }
    .lp-footer a { color: rgba(255,255,255,0.62); text-decoration: none; font-size: 0.8rem; }
    .lp-footer a:hover { color: #93c5fd; }

    /* ── Scoped overrides: shared classes from app.css, landing page only ── */
    .landing-shell .landing-hero { min-height: 420px; }
    .landing-shell .hero-badge { padding: 6px 10px; font-size: 0.72rem; }
    .landing-shell .hero-title { font-size: clamp(1.6rem, 3vw, 2.4rem); line-height: 1.1; letter-spacing: -0.04em; }
    .landing-shell .hero-copy { font-size: 0.9rem; line-height: 1.7; }
    .landing-shell .hero-metrics div { min-width: 128px; padding: 10px 12px; }
    .landing-shell .hero-metrics strong { font-size: 0.9rem; }
    .landing-shell .hero-metrics span { font-size: 0.7rem; }
    .landing-shell .hero-panel { max-width: 460px; border-radius: 20px; }
    .landing-shell .hero-panel-header { padding: 14px 18px; }
    .landing-shell .hero-panel-body { padding: 18px; }
    .landing-shell .hero-stat { padding: 12px; margin-bottom: 10px; gap: 12px; }
    .landing-shell .hero-stat i { width: 34px; height: 34px; font-size: 0.9rem; }
    .landing-shell .hero-stat strong { font-size: 1.1rem; }
    .landing-shell .hero-stat span { font-size: 0.72rem; }
    .landing-shell .brand-mark strong {
        font-family: 'Space Grotesk', sans-serif; font-size: 1.35rem; font-weight: 700; letter-spacing: 0.4em;
        background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 45%, #f472b6 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .landing-shell .brand-mark small { font-family: 'Space Grotesk', sans-serif; font-size: 0.5rem; letter-spacing: 0.12em; }
    .landing-shell .feature-card { padding: 22px 18px; }
    .landing-shell .feature-card h5 { font-size: 1.05rem; }
    .landing-shell .feature-card p { font-size: 0.8rem; line-height: 1.6; }

    /* ── Package cards with layered background ── */
    .pkg-card { position: relative; overflow: hidden; border-radius: 20px; }
    .pkg-lite { background: linear-gradient(165deg, rgba(37,99,235,0.55) 0%, rgba(15,23,42,0.90) 55%, rgba(7,17,31,0.95) 100%); border-color: rgba(96,165,250,0.40); }
    .pkg-pro  { background: linear-gradient(165deg, rgba(5,150,105,0.55) 0%, rgba(15,23,42,0.90) 55%, rgba(7,17,31,0.95) 100%); border-color: rgba(52,211,153,0.40); }
    .pkg-vip  { background: linear-gradient(165deg, rgba(168,85,247,0.55) 0%, rgba(15,23,42,0.90) 55%, rgba(7,17,31,0.95) 100%); border-color: rgba(196,132,252,0.40); }
    .pkg-card::before {
        content: ''; position: absolute; inset: 0; opacity: 0.06; pointer-events: none;
        background-image: radial-gradient(rgba(255,255,255,0.9) 1px, transparent 1px);
        background-size: 16px 16px;
    }
    .pkg-card::after {
        content: ''; position: absolute; top: 0; left: 10%; right: 10%; height: 2px; pointer-events: none;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    }
    .pkg-watermark {
        position: absolute; right: -14px; bottom: -14px; font-size: 5rem;
        line-height: 1; opacity: 0.09; transform: rotate(-12deg); pointer-events: none;
    }
    .pkg-body { position: relative; }
    .pkg-badge {
        display: inline-block; padding: 4px 12px; border-radius: 999px;
        font-size: 0.66rem; font-weight: 800; letter-spacing: 0.14em; margin-bottom: 14px;
        color: #fff; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
    }
    .pkg-icon {
        width: 48px; height: 48px; margin: 0 auto 12px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 14px; font-size: 1.3rem; line-height: 1; color: #fff;
    }
    .pkg-icon i { display: block; line-height: 1; }
    .pkg-lite .pkg-icon { background: linear-gradient(135deg,#2563eb,#60a5fa); box-shadow: 0 10px 24px rgba(37,99,235,0.40); }
    .pkg-pro  .pkg-icon { background: linear-gradient(135deg,#059669,#34d399); box-shadow: 0 10px 24px rgba(5,150,105,0.40); }
    .pkg-vip  .pkg-icon { background: linear-gradient(135deg,#7c3aed,#c084fc); box-shadow: 0 10px 24px rgba(168,85,247,0.40); }
</style>
@endpush

@section('content')
<div class="landing-shell">

    {{-- ══ NAVBAR ══ --}}
    <div class="container py-4">
        <nav class="landing-nav mb-4">
            <a href="{{ url('/') }}" class="brand-mark text-decoration-none">
                        <span class="brand-icon"><img src="{{ asset('images/logo-alkonek.gif') }}" alt="ALKONEK" style="width:42px;height:42px;border-radius:10px;object-fit:contain;"></span>
                <span>
                            <strong>ALKONEK</strong>
                    <small>PT. Alkonek Network Access</small>
                </span>
            </a>
            <div class="d-none d-lg-flex gap-4 align-items-center" style="color:rgba(255,255,255,0.72);font-size:0.85rem;">
                <a href="#tentang" style="color:inherit;text-decoration:none;">Tentang</a>
                <a href="#layanan" style="color:inherit;text-decoration:none;">Layanan</a>
                <a href="#paket" style="color:inherit;text-decoration:none;">Paket</a>
                <a href="#area" style="color:inherit;text-decoration:none;">Area</a>
                <a href="#kontak" style="color:inherit;text-decoration:none;">Kontak</a>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('portal.index') }}" class="btn btn-outline-light btn-sm px-3">
                    <i class="fa-solid fa-receipt me-1"></i>Cek Tagihan
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm px-3">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-3">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>Masuk
                    </a>
                @endauth
            </div>
        </nav>
    </div>

    {{-- ══ HERO ══ --}}
    <div class="container">
        <div class="row align-items-center g-5 landing-hero">
            <div class="col-lg-6">
                <div class="hero-badge mb-3">
                    <i class="fa-solid fa-signal me-2"></i>Reseller Telekomunikasi &amp; Internet RT/RW Net
                </div>
                <h1 class="hero-title mb-3">Internet Cepat &amp; Stabil untuk Rumah, RT/RW Net, dan Bisnis.</h1>
                <p class="hero-copy mb-4">
                    <strong>PT. Alkonek Network Access</strong> menghadirkan layanan internet fiber optik untuk
                    perumahan dan kawasan RT/RW, sekaligus sebagai reseller jasa telekomunikasi. Cepat, stabil,
                    dan terjangkau — dikelola dengan sistem billing yang rapi dan pembayaran online.
                </p>
                <div class="hero-metrics">
                    <div><strong>Fiber Optik</strong><span>Koneksi super cepat</span></div>
                    <div><strong>Billing Online</strong><span>Tagihan & bayar mudah</span></div>
                    <div><strong>Support 24/7</strong><span>Layanan cepat tanggap</span></div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-panel">
                    <div class="hero-panel-header">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="hero-panel-body">
                        <div class="hero-stat hero-stat-blue">
                            <i class="fa-solid fa-tower-broadcast"></i>
                            <div><strong>100+ Mbps</strong><span>Kecepatan hingga</span></div>
                        </div>
                        <div class="hero-stat hero-stat-green">
                            <i class="fa-solid fa-house-signal"></i>
                            <div><strong>RT/RW Net</strong><span>Perumahan terlayani</span></div>
                        </div>
                        <div class="hero-stat hero-stat-orange">
                            <i class="fa-solid fa-headset"></i>
                            <div><strong>24/7</strong><span>Monitoring & support</span></div>
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
    </div>

    {{-- ══ STATS BAND ══ --}}
    <div class="container mt-5">
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="stat-box"><strong>5+</strong><span>Area RT/RW Terlayani</span></div></div>
            <div class="col-6 col-md-3"><div class="stat-box"><strong>1000+</strong><span>Pelanggan Aktif</span></div></div>
            <div class="col-6 col-md-3"><div class="stat-box"><strong>99.9%</strong><span>Uptime Jaringan</span></div></div>
            <div class="col-6 col-md-3"><div class="stat-box"><strong>24/7</strong><span>Dukungan Teknis</span></div></div>
        </div>
    </div>

    {{-- ══ TENTANG ══ --}}
    <section class="lp-section" id="tentang">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <div class="lp-eyebrow mb-2"><i class="fa-solid fa-building"></i> Tentang Kami</div>
                    <h2 class="lp-title mb-3">Perusahaan Jasa Reseller Telekomunikasi &amp; Internet RT/RW Net</h2>
                    <div class="lp-divider mb-4"></div>
                    <p class="lp-lead">
                        {{ $company['name'] }} adalah perusahaan jasa yang bergerak di bidang reseller jasa
                        telekomunikasi dan penyedia layanan internet RT/RW Net. Kami membangun dan mengelola jaringan
                        fiber optik untuk menghubungkan rumah, perumahan, dan kawasan RT/RW dengan internet super cepat.
                    </p>
                </div>
                <div class="col-lg-7">
                    <div class="about-card">
                        <p>
                            Dengan infrastruktur modern — OLT, ODP, dan router MikroTik — serta sistem billing terpadu,
                            kami memastikan layanan internet yang stabil, transparan, dan mudah dikelola. Setiap titik
                            terhubung diawasi dan dirawat secara rutin agar kualitas layanan selalu terjaga.
                        </p>
                        <div class="row g-2 mt-2">
                            <div class="col-md-6"><div class="about-point"><i class="fa-solid fa-circle-check"></i><span>Jaringan fiber optik point-to-point ke rumah</span></div></div>
                            <div class="col-md-6"><div class="about-point"><i class="fa-solid fa-circle-check"></i><span>Manajemen bandwidth yang transparan</span></div></div>
                            <div class="col-md-6"><div class="about-point"><i class="fa-solid fa-circle-check"></i><span>Tagihan online dengan pembayaran digital</span></div></div>
                            <div class="col-md-6"><div class="about-point"><i class="fa-solid fa-circle-check"></i><span>Tim teknis siap 24 jam di setiap area</span></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══ LAYANAN ══ --}}
    <section class="lp-section" id="layanan" style="padding-top:0;">
        <div class="container">
            <div class="text-center mb-5">
                <div class="lp-eyebrow mb-2 justify-content-center"><i class="fa-solid fa-layer-group"></i> Layanan Kami</div>
                <h2 class="lp-title mx-auto mb-3">Solusi Internet &amp; Telekomunikasi Lengkap</h2>
                <p class="lp-lead mx-auto">Satu pintu untuk kebutuhan internet rumah, perumahan, hingga pengelolaan RT/RW Net.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-house-signal"></i></div>
                        <h5>Internet RT/RW Net</h5>
                        <p>Layanan internet fiber optik untuk perumahan dan kawasan RT/RW dengan harga hemat dan koneksi stabil.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                        <h5>Reseller Telekomunikasi</h5>
                        <p>Penyediaan dan pengelolaan produk jasa telekomunikasi untuk kebutuhan residensial maupun komersial.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-network-wired"></i></div>
                        <h5>Pemasangan Fiber Optik</h5>
                        <p>Instalasi jaringan fiber optik ke rumah, kantor, dan area perumahan oleh teknisi berpengalaman.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <h5>Billing &amp; Pembayaran Online</h5>
                        <p>Tagihan bulanan yang rapi dengan pembayaran online yang mudah, aman, dan tercatat otomatis.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-wifi"></i></div>
                        <h5>Voucher WiFi &amp; Hotspot</h5>
                        <p>Solusi hotspot dengan voucher untuk pengunjung, warung, dan area publik yang ramai.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card">
                        <div class="service-icon"><i class="fa-solid fa-headset"></i></div>
                        <h5>Monitoring &amp; Support 24/7</h5>
                        <p>Pemantauan jaringan terus-menerus dengan respons cepat untuk gangguan dan pemeliharaan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══ PAKET ══ --}}
    @if($packages->isNotEmpty())
    <section class="lp-section" id="paket" style="padding-top:0;">
        <div class="container">
            <div class="text-center mb-5">
                <div class="lp-eyebrow mb-2 justify-content-center"><i class="fa-solid fa-wifi"></i> Pilihan Paket Internet</div>
                <h2 class="lp-title mx-auto mb-3">Nikmati <span style="color:#60a5fa;">Akses Cepat</span> Tanpa Batas</h2>
                <p class="lp-lead mx-auto">Pilih paket yang sesuai dengan kebutuhan internet Anda. Transparan, tanpa biaya tersembunyi.</p>
            </div>
            <div class="row g-4 justify-content-center">
                @foreach($packages as $package)
                @php
                    $tier = $loop->first ? 'lite' : ($loop->last ? 'vip' : 'pro');
                    $icon = $loop->first ? 'fa-wifi' : ($loop->last ? 'fa-tower-broadcast' : 'fa-signal');
                @endphp
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card pkg-card pkg-{{ $tier }} h-100 text-center">
                        <i class="fa-solid {{ $icon }} pkg-watermark"></i>
                        <div class="pkg-body">
                            @if($package->description && strlen($package->description) <= 14)
                            <span class="pkg-badge">{{ $package->description }}</span>
                            @endif
                            <div class="pkg-icon"><i class="fa-solid {{ $icon }}"></i></div>
                            <h5 style="font-weight:800;font-size:1.05rem;">{{ $package->name }}</h5>
                            @if($package->speed)
                            <div style="color:rgba(255,255,255,0.6);font-size:0.78rem;margin-bottom:10px;">
                                <i class="fa-solid fa-arrow-down me-1"></i>{{ $package->speed }} Mbps
                            </div>
                            @endif
                            <div style="font-size:1.5rem;font-weight:900;letter-spacing:-0.03em;margin:12px 0 6px;">
                                Rp{{ number_format($package->price, 0, ',', '.') }}
                            </div>
                            @if($package->billing_cycle)
                            <div style="color:rgba(255,255,255,0.45);font-size:0.75rem;margin-bottom:12px;">
                                /{{ $package->billing_cycle === 'monthly' ? 'bulan' : $package->billing_cycle }}
                            </div>
                            @endif
                            @if($package->description && strlen($package->description) > 14)
                            <p style="color:rgba(255,255,255,0.55);font-size:0.78rem;line-height:1.6;margin-bottom:0;">
                                {{ $package->description }}
                            </p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ══ AREA / COVERAGE ══ --}}
    <section class="lp-section" id="area" style="padding-top:0;">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="lp-eyebrow mb-2"><i class="fa-solid fa-map-location-dot"></i> Area Layanan</div>
                    <h2 class="lp-title mb-3">Jangkauan Jaringan yang Terus Bertumbuh</h2>
                    <p class="lp-lead mb-4">
                        Kami melayani perumahan dan kawasan RT/RW di sekitar wilayah operasional. Jaringan fiber optik
                        kami terus diperluas — pastikan area Anda terhubung dengan internet super cepat.
                    </p>
                    <a href="{{ route('portal.index') }}" class="btn btn-outline-light">
                        <i class="fa-solid fa-receipt me-2"></i>Cek Tagihan &amp; Layanan
                    </a>
                </div>
                <div class="col-lg-6">
                    <div class="coverage-box">
                        <div class="mb-3" style="color:rgba(255,255,255,0.8);font-weight:700;font-size:0.92rem;">
                            <i class="fa-solid fa-tower-broadcast me-2" style="color:#60a5fa;"></i>Area yang kami layani
                        </div>
                        <div>
                            <span class="coverage-tag"><i class="fa-solid fa-circle-check" style="color:#34d399;"></i>RT/RW Net perumahan</span>
                            <span class="coverage-tag"><i class="fa-solid fa-circle-check" style="color:#34d399;"></i>Komplek &amp; cluster</span>
                            <span class="coverage-tag"><i class="fa-solid fa-circle-check" style="color:#34d399;"></i>Rumah &amp; kantor</span>
                            <span class="coverage-tag"><i class="fa-solid fa-circle-check" style="color:#34d399;"></i>Area usaha &amp; warung</span>
                            <span class="coverage-tag"><i class="fa-solid fa-circle-check" style="color:#34d399;"></i>Kawasan yang sedang dibangun</span>
                        </div>
                        <div class="mt-4" style="color:rgba(255,255,255,0.55);font-size:0.78rem;">
                            Belum tercover? Hubungi tim kami untuk penjadwalan survei &amp; pemasangan.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══ KONTAK ══ --}}
    <section class="lp-section" id="kontak" style="padding-top:0;">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="lp-eyebrow mb-2"><i class="fa-solid fa-phone"></i> Hubungi Kami</div>
                    <h2 class="lp-title mb-4">Siap Membantu Kebutuhan Internet Anda</h2>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <strong>Alamat</strong>
                            <span>Kp. Malangnengah RT/RW 004/001 Desa Bendungan Kec. Banjarsari Kab. Lebak-Banten</span>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <strong>Telepon / WhatsApp</strong>
                            <span>089531559066</span>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <strong>Email</strong>
                            <span>alkoneknetworkaccess@gmail.com</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="about-card h-100 d-flex flex-column justify-content-center">
                        <h5 class="mb-3" style="font-weight:800;font-size:1.05rem;">Cek Tagihan, Bayar Online, dan Kelola Layanan Anda</h5>
                        <p class="mb-4" style="color:rgba(255,255,255,0.6);font-size:0.88rem;">
                            Pelanggan dapat melihat tagihan, riwayat pembayaran, dan membayar langsung secara online
                            melalui portal pelanggan. Tim administrasi dapat masuk ke panel billing untuk pengelolaan penuh.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══ FOOTER ══ --}}
    <footer class="lp-footer">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <a href="{{ url('/') }}" class="brand-mark text-decoration-none mb-3">
                <span class="brand-icon"><img src="{{ asset('images/logo-alkonek.gif') }}" alt="ALKONEK" style="width:42px;height:42px;border-radius:10px;object-fit:contain;"></span>
                        <span>
                    <strong>ALKONEK</strong>
                            <small>PT. Alkonek Network Access</small>
                        </span>
                    </a>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;line-height:1.7;margin-top:12px;max-width:340px;">
                        Perusahaan jasa reseller telekomunikasi &amp; internet RT/RW Net berbasis fiber optik dengan sistem billing dan monitoring terpadu.
                    </p>
                </div>
                <div class="col-6 col-lg-2">
                    <div style="color:#fff;font-weight:700;font-size:0.85rem;margin-bottom:10px;">Navigasi</div>
                    <div class="d-flex flex-column gap-2">
                        <a href="#tentang">Tentang</a>
                        <a href="#layanan">Layanan</a>
                        <a href="#paket">Paket</a>
                        <a href="#area">Area</a>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div style="color:#fff;font-weight:700;font-size:0.85rem;margin-bottom:10px;">Akses</div>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('portal.index') }}">Cek Tagihan</a>
                        @auth
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}">Masuk Admin</a>
                        @endauth
                    </div>
                </div>
                <div class="col-lg-3">
                    <div style="color:#fff;font-weight:700;font-size:0.85rem;margin-bottom:10px;">Kontak</div>
                    <div style="color:rgba(255,255,255,0.55);font-size:0.8rem;line-height:1.9;">
                        <i class="fa-solid fa-phone me-2" style="color:#60a5fa;"></i>089531559066<br>
                        <i class="fa-solid fa-envelope me-2" style="color:#60a5fa;"></i>alkoneknetworkaccess@gmail.com<br>
                        <i class="fa-solid fa-location-dot me-2" style="color:#60a5fa;"></i>Kp. Malangnengah RT/RW 004/001 Desa Bendungan Kec. Banjarsari Kab. Lebak-Banten
                    </div>
                </div>
            </div>
            <hr class="lp-divider my-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2" style="color:rgba(255,255,255,0.35);font-size:0.7rem;">
                <span>&copy; {{ date('Y') }} PT. Alkonek Network Access. All rights reserved.</span>
                <span>ALKONEK &middot; {{ app()->version() }} &middot; {{ phpversion() }}</span>
            </div>
        </div>
    </footer>

</div>
@endsection
