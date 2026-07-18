<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal pelanggan {{ $company['name'] }} — cek tagihan internet dan bayar online. Mudah, cepat, dan aman.">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Portal Pelanggan ~ {{ $company['name'] }}">
    <meta property="og:description" content="Cek tagihan internet dan bayar online.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.svg') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>Portal Pelanggan ~ {{ $company['name'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --p-navy: #060b18;
        --p-deep: #0a1628;
        --p-blue: #2563eb;
        --p-blue-light: #60a5fa;
        --p-cyan: #22d3ee;
        --p-accent: #6366f1;
        --p-surface: rgba(255,255,255,0.04);
        --p-border: rgba(255,255,255,0.08);
        --p-text: rgba(255,255,255,0.88);
        --p-text-dim: rgba(255,255,255,0.45);
        --p-text-muted: rgba(255,255,255,0.25);
        --p-green: #22c55e;
        --p-amber: #f59e0b;
        --p-rose: #f43f5e;
        --ease: cubic-bezier(0.22, 1, 0.36, 1);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--p-navy);
        color: var(--p-text);
        min-height: 100vh;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
    }

    /* ═══════════ BACKGROUND ═══════════ */
    .portal-bg {
        position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
    }
    .portal-bg-orb1 {
        position: absolute; width: 700px; height: 700px; border-radius: 50%;
        background: radial-gradient(circle, rgba(37,99,235,0.2) 0%, transparent 70%);
        top: -200px; left: -150px;
        animation: orbFloat1 14s ease-in-out infinite alternate;
    }
    .portal-bg-orb2 {
        position: absolute; width: 500px; height: 500px; border-radius: 50%;
        background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
        bottom: -100px; right: -50px;
        animation: orbFloat2 11s ease-in-out infinite alternate;
    }
    .portal-bg-orb3 {
        position: absolute; width: 350px; height: 350px; border-radius: 50%;
        background: radial-gradient(circle, rgba(34,211,238,0.1) 0%, transparent 70%);
        top: 40%; left: 30%;
        animation: orbFloat3 16s ease-in-out infinite alternate;
    }
    @keyframes orbFloat1 { 0%{transform:translate(0,0) scale(1)} 100%{transform:translate(60px,50px) scale(1.1)} }
    @keyframes orbFloat2 { 0%{transform:translate(0,0) scale(1)} 100%{transform:translate(-50px,-40px) scale(1.08)} }
    @keyframes orbFloat3 { 0%{transform:translate(0,0) scale(1)} 100%{transform:translate(30px,-20px) scale(1.12)} }

    .portal-grid-overlay {
        position: fixed; inset: 0; z-index: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
        background-size: 64px 64px;
        mask-image: radial-gradient(ellipse at 35% 45%, black 15%, transparent 65%);
        -webkit-mask-image: radial-gradient(ellipse at 35% 45%, black 15%, transparent 65%);
    }
    .portal-noise {
        position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: 0.03;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }

    /* ═══════════ LAYOUT ═══════════ */
    .portal-wrapper {
        position: relative; z-index: 1; min-height: 100vh;
        display: flex; align-items: stretch;
    }
    .portal-hero {
        flex: 0 0 55%; display: flex; flex-direction: column; justify-content: center;
        padding: 56px 64px; position: relative; overflow: hidden;
        animation: heroSlideLeft 0.7s var(--ease) both;
    }
    @keyframes heroSlideLeft { from{opacity:0;transform:translateX(-32px)} to{opacity:1;transform:translateX(0)} }

    .portal-form-side {
        flex: 0 0 45%; display: flex; align-items: center; justify-content: center;
        padding: 48px 48px; position: relative; z-index: 1;
        animation: formSlideRight 0.7s var(--ease) 0.12s both;
    }
    @keyframes formSlideRight { from{opacity:0;transform:translateX(32px)} to{opacity:1;transform:translateX(0)} }

    /* ═══════════ HERO ═══════════ */
    .portal-brand {
        display: flex; align-items: center; gap: 14px; margin-bottom: 40px;
    }
    .portal-brand-logo {
        width: 50px; height: 50px; border-radius: 14px; object-fit: contain;
        background: rgba(255,255,255,0.06); padding: 5px;
        box-shadow: 0 8px 24px rgba(37,99,235,0.2);
    }
    .portal-brand h2 {
        font-size: 1.4rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1.1;
        background: linear-gradient(135deg, #fff, #93c5fd);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .portal-brand small {
        display: block; font-size: 0.68rem; font-weight: 600; letter-spacing: 0.12em;
        text-transform: uppercase; color: var(--p-text-muted); margin-top: 1px;
    }

    .portal-hero-title {
        font-size: clamp(1.8rem, 3.2vw, 2.8rem); font-weight: 900; line-height: 1.08;
        letter-spacing: -0.05em; margin-bottom: 16px;
    }
    .portal-hero-title .hl {
        background: linear-gradient(135deg, #60a5fa, #a78bfa, #60a5fa);
        background-size: 200% auto;
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        animation: shimmer 4s ease-in-out infinite;
    }
    @keyframes shimmer { 0%,100%{background-position:0% center} 50%{background-position:200% center} }

    .portal-hero-desc {
        font-size: 0.95rem; line-height: 1.7; color: var(--p-text-dim);
        max-width: 480px; margin-bottom: 32px;
    }

    /* ─── Network Topology ─── */
    .portal-net {
        padding: 20px; border-radius: 18px; border: 1px solid var(--p-border);
        background: var(--p-surface); backdrop-filter: blur(6px);
        animation: fadeUp 0.5s var(--ease) 0.4s both;
    }
    .portal-net-title {
        font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--p-text-muted); margin-bottom: 14px;
    }
    .portal-net-flow { display: flex; align-items: center; gap: 0; flex-wrap: wrap; }
    .portal-net-node {
        display: flex; flex-direction: column; align-items: center; gap: 5px;
        padding: 8px 10px; border-radius: 10px; background: rgba(255,255,255,0.04);
        border: 1px solid var(--p-border); transition: all 0.3s var(--ease); min-width: 62px;
    }
    .portal-net-node:hover { background: rgba(37,99,235,0.12); border-color: rgba(37,99,235,0.25); transform: translateY(-2px); }
    .portal-net-node i { font-size: 0.85rem; color: #60a5fa; }
    .portal-net-node span { font-size: 0.58rem; font-weight: 600; color: var(--p-text-dim); text-align: center; line-height: 1.2; }
    .portal-net-arrow { padding: 0 3px; color: var(--p-text-muted); font-size: 0.55rem; flex-shrink: 0; }

    /* ─── Floating Cards ─── */
    .portal-float {
        position: absolute; display: flex; align-items: center; gap: 10px;
        padding: 11px 15px; border-radius: 14px; border: 1px solid var(--p-border);
        background: rgba(10,15,30,0.8); backdrop-filter: blur(16px);
        box-shadow: 0 8px 32px rgba(0,0,0,0.3); white-space: nowrap;
        animation: floatCard 7s ease-in-out infinite;
    }
    .portal-float .fdot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .portal-float .fdot.green { background: var(--p-green); box-shadow: 0 0 6px rgba(34,197,94,0.5); }
    .portal-float .fdot.blue { background: var(--p-blue-light); box-shadow: 0 0 6px rgba(96,165,250,0.5); }
    .portal-float .fdot.amber { background: var(--p-amber); box-shadow: 0 0 6px rgba(245,158,11,0.5); }
    .portal-float span { font-size: 0.72rem; font-weight: 600; color: var(--p-text); }
    .portal-float small { font-size: 0.6rem; color: var(--p-text-muted); display: block; }
    @keyframes floatCard { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-7px)} }

    /* ═══════════ FORM PANEL ═══════════ */
    .portal-glass {
        width: 100%; max-width: 480px; padding: 38px 34px;
        border-radius: 24px; border: 1px solid var(--p-border);
        background: rgba(255,255,255,0.04); backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        box-shadow: 0 1px 0 rgba(255,255,255,0.06) inset, 0 32px 80px rgba(0,0,0,0.35);
        position: relative; overflow: hidden;
    }
    .portal-glass::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    }

    .portal-form-header { text-align: center; margin-bottom: 28px; }
    .portal-form-header .icon-circle {
        width: 52px; height: 52px; border-radius: 16px; display: inline-flex;
        align-items: center; justify-content: center; margin-bottom: 14px;
        background: linear-gradient(135deg, var(--p-blue), var(--p-accent));
        box-shadow: 0 12px 30px rgba(37,99,235,0.25); color: #fff; font-size: 1.1rem;
    }
    .portal-form-header h3 {
        font-size: 1.35rem; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 4px;
    }
    .portal-form-header p { font-size: 0.82rem; color: var(--p-text-dim); margin: 0; }

    /* ─── Inputs ─── */
    .pi-group { margin-bottom: 18px; animation: fadeUp 0.4s var(--ease) 0.3s both; }
    .pi-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--p-text-dim); margin-bottom: 7px; }
    .pi-wrap { position: relative; display: flex; align-items: center; }
    .pi-wrap .pi-icon {
        position: absolute; left: 14px; color: var(--p-text-muted); font-size: 0.85rem;
        pointer-events: none; transition: color 0.25s; z-index: 2;
    }
    .pi-wrap:focus-within .pi-icon { color: #60a5fa; }
    .pi-input {
        width: 100%; padding: 13px 14px 13px 42px; border: 1.5px solid var(--p-border);
        border-radius: 12px; background: rgba(255,255,255,0.04); color: #fff;
        font-size: 0.88rem; font-family: inherit; outline: none; transition: all 0.25s var(--ease);
    }
    .pi-input::placeholder { color: var(--p-text-muted); }
    .pi-input:focus { border-color: rgba(96,165,250,0.5); background: rgba(255,255,255,0.06); box-shadow: 0 0 0 3px rgba(96,165,250,0.1), 0 0 20px rgba(96,165,250,0.06); }
    .pi-input.is-invalid { border-color: rgba(239,68,68,0.5) !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.08) !important; }
    .pi-error { display: flex; align-items: center; gap: 6px; margin-top: 7px; font-size: 0.76rem; color: #fca5a5; }
    .pi-error i { font-size: 0.68rem; }

    /* ─── Alert ─── */
    .pi-alert {
        display: flex; align-items: center; gap: 8px; padding: 12px 14px;
        border-radius: 10px; font-size: 0.8rem; font-weight: 500; margin-bottom: 18px;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5;
        animation: fadeUp 0.3s var(--ease);
    }
    .pi-alert i { font-size: 0.75rem; }

    /* ─── Submit Button ─── */
    .pi-submit {
        width: 100%; padding: 14px 24px; border: none; border-radius: 12px;
        background: linear-gradient(135deg, var(--p-blue), var(--p-accent));
        color: #fff; font-size: 0.92rem; font-weight: 700; font-family: inherit;
        cursor: pointer; transition: all 0.3s var(--ease); position: relative; overflow: hidden;
    }
    .pi-submit::before {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%);
        opacity: 0; transition: opacity 0.3s;
    }
    .pi-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.4); }
    .pi-submit:hover::before { opacity: 1; }
    .pi-submit:active { transform: translateY(0) scale(0.98); }
    .pi-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    .pi-submit .spinner-border { width: 16px; height: 16px; border-width: 2px; margin-right: 8px; }

    /* ═══════════ SECTIONS ═══════════ */
    .pi-section { margin-top: 24px; animation: fadeUp 0.4s var(--ease) 0.5s both; }
    .pi-section-title {
        font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--p-text-muted); margin-bottom: 10px;
    }

    /* Payment Methods */
    .pi-payments { display: flex; flex-wrap: wrap; gap: 8px; }
    .pi-pay-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 8px; border: 1px solid var(--p-border);
        background: rgba(255,255,255,0.03); font-size: 0.68rem; font-weight: 600;
        color: var(--p-text-dim); transition: all 0.25s var(--ease);
    }
    .pi-pay-chip i { font-size: 0.75rem; }
    .pi-pay-chip:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.15); color: var(--p-text); }

    /* Services */
    .pi-services { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .pi-svc {
        display: flex; align-items: center; gap: 8px; padding: 8px 10px;
        border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--p-border);
        font-size: 0.68rem; font-weight: 500; color: var(--p-text-dim);
        transition: all 0.25s var(--ease);
    }
    .pi-svc i { color: #60a5fa; font-size: 0.72rem; flex-shrink: 0; }
    .pi-svc:hover { background: rgba(255,255,255,0.06); color: var(--p-text); }

    /* Status */
    .pi-status { display: flex; flex-wrap: wrap; gap: 8px; }
    .pi-st-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 10px; border-radius: 999px; font-size: 0.65rem; font-weight: 600;
        background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.15); color: #86efac;
    }
    .pi-st-dot { width: 5px; height: 5px; border-radius: 50%; background: #22c55e; animation: pulseDot 2s ease-in-out infinite; }
    @keyframes pulseDot { 0%,100%{opacity:0.5} 50%{opacity:1} }

    /* Contact */
    .pi-contact { display: flex; flex-direction: column; gap: 8px; }
    .pi-contact-row {
        display: flex; align-items: center; gap: 8px; font-size: 0.72rem; color: var(--p-text-dim);
    }
    .pi-contact-row i { width: 16px; text-align: center; color: #60a5fa; font-size: 0.72rem; flex-shrink: 0; }

    /* Social */
    .pi-social { display: flex; gap: 8px; margin-top: 16px; }
    .pi-social a {
        width: 34px; height: 34px; border-radius: 10px; display: flex;
        align-items: center; justify-content: center; border: 1px solid var(--p-border);
        background: rgba(255,255,255,0.03); color: var(--p-text-dim); font-size: 0.82rem;
        transition: all 0.3s var(--ease); text-decoration: none;
    }
    .pi-social a:hover { background: rgba(37,99,235,0.15); border-color: rgba(37,99,235,0.3); color: #60a5fa; transform: translateY(-2px); }

    /* Divider */
    .pi-divider {
        height: 1px; background: linear-gradient(90deg, transparent, var(--p-border), transparent);
        margin: 20px 0;
    }

    /* ═══════════ STATUS BAR ═══════════ */
    .portal-status-bar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 10;
        display: flex; align-items: center; justify-content: center; gap: 24px;
        padding: 9px 20px; background: rgba(6,11,24,0.92);
        border-top: 1px solid var(--p-border); backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .portal-sb-item {
        display: flex; align-items: center; gap: 5px;
        font-size: 0.62rem; font-weight: 500; color: var(--p-text-muted);
    }
    .portal-sb-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--p-green); box-shadow: 0 0 5px rgba(34,197,94,0.5); }
    .portal-copyright {
        position: fixed; bottom: 34px; left: 0; right: 0; z-index: 10;
        text-align: center; padding: 6px 20px;
        font-size: 0.6rem; color: rgba(255,255,255,0.12); font-weight: 500;
    }

    /* ═══════════ ANIMATIONS ═══════════ */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* ═══════════ RESPONSIVE ═══════════ */
    @media (max-width: 1024px) {
        .portal-hero { flex: 0 0 50%; padding: 40px 36px; }
        .portal-form-side { flex: 0 0 50%; padding: 36px 28px; }
        .portal-hero-title { font-size: 1.8rem; }
    }
    @media (max-width: 768px) {
        .portal-wrapper { flex-direction: column; }
        .portal-hero { flex: 0 0 auto; padding: 28px 20px 16px; }
        .portal-hero-title { font-size: 1.5rem; }
        .portal-hero-desc { font-size: 0.85rem; margin-bottom: 20px; }
        .portal-net { display: none; }
        .portal-float { display: none; }
        .portal-form-side { flex: 0 0 auto; padding: 16px 16px 80px; }
        .portal-glass { padding: 28px 22px; border-radius: 20px; }
        .portal-form-header h3 { font-size: 1.15rem; }
        .pi-services { grid-template-columns: 1fr; }
        .portal-status-bar { gap: 14px; flex-wrap: wrap; }
        .portal-copyright { display: none; }
    }
    @media (max-width: 480px) {
        .portal-hero { padding: 20px 14px 12px; }
        .portal-brand h2 { font-size: 1.15rem; }
        .portal-hero-title { font-size: 1.3rem; }
        .portal-glass { padding: 22px 16px; }
        .pi-services { grid-template-columns: 1fr; }
        .pi-payments { gap: 6px; }
    }
    </style>
</head>
<body>
    {{-- ═══════ BACKGROUND ═══════ --}}
    <div class="portal-bg">
        <div class="portal-bg-orb1"></div>
        <div class="portal-bg-orb2"></div>
        <div class="portal-bg-orb3"></div>
    </div>
    <div class="portal-grid-overlay"></div>
    <div class="portal-noise"></div>

    {{-- ═══════ MAIN LAYOUT ═══════ --}}
    <div class="portal-wrapper">

        {{-- ═══════ LEFT: HERO ═══════ --}}
        <div class="portal-hero">
            <div class="portal-brand">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="portal-brand-logo">
                <div>
                    <h2>{{ $company['name'] }}</h2>
                    <small>Internet Service Provider</small>
                </div>
            </div>

            <h1 class="portal-hero-title">
                Menghubungkan<br>
                Masa Depan dengan<br>
                <span class="hl">Fiber Optik</span>
            </h1>
            <p class="portal-hero-desc">
                Cek tagihan, riwayat pembayaran, dan lakukan pembayaran online dengan mudah. Layanan internet berkecepatan tinggi untuk rumah dan bisnis Anda.
            </p>

            {{-- Network Topology --}}
            <div class="portal-net">
                <div class="portal-net-title">Network Topology</div>
                <div class="portal-net-flow">
                    <div class="portal-net-node"><i class="fa-solid fa-globe"></i><span>Internet</span></div>
                    <div class="portal-net-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="portal-net-node"><i class="fa-solid fa-server"></i><span>Core Router</span></div>
                    <div class="portal-net-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="portal-net-node"><i class="fa-solid fa-tower-cell"></i><span>OLT</span></div>
                    <div class="portal-net-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="portal-net-node"><i class="fa-solid fa-diagram-project"></i><span>ODC</span></div>
                    <div class="portal-net-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="portal-net-node"><i class="fa-solid fa-circle-nodes"></i><span>ODP</span></div>
                    <div class="portal-net-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="portal-net-node"><i class="fa-solid fa-house-signal"></i><span>Pelanggan</span></div>
                </div>
            </div>
        </div>

        {{-- ═══════ RIGHT: FORM PORTAL ═══════ --}}
        <div class="portal-form-side">
            <div class="portal-glass">
                <div class="portal-form-header">
                    <div class="icon-circle"><i class="fa-solid fa-wallet"></i></div>
                    <h3>Portal Pelanggan</h3>
                    <p>Cek tagihan, riwayat pembayaran, dan lakukan pembayaran online dengan mudah.</p>
                </div>

                {{-- Error --}}
                @if(session('error'))
                    <div class="pi-alert"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
                @endif

                {{-- Form --}}
                <form method="POST" action="{{ route('portal.lookup') }}" id="portalForm">
                    @csrf
                    <div class="pi-group">
                        <label class="pi-label">Nomor Telepon / ID Pelanggan</label>
                        <div class="pi-wrap">
                            <i class="fa-solid fa-user pi-icon"></i>
                            <input
                                type="text"
                                name="phone"
                                class="pi-input @error('phone') is-invalid @enderror"
                                value="{{ old('phone') }}"
                                placeholder="08xxxxxxxxxx atau ALK0000001"
                                required
                                autofocus
                            >
                        </div>
                        @error('phone')
                            <div class="pi-error"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="pi-submit" id="portalBtn">
                        <i class="fa-solid fa-search me-2"></i><span class="btn-text">Cari Tagihan</span>
                    </button>
                </form>

                <div class="pi-divider"></div>

                {{-- Payment Methods --}}
                <div class="pi-section">
                    <div class="pi-section-title">Metode Pembayaran</div>
                    <div class="pi-payments">
                        <div class="pi-pay-chip"><i class="fa-solid fa-qrcode"></i> QRIS</div>
                        <div class="pi-pay-chip"><i class="fa-solid fa-building-columns"></i> Transfer Bank</div>
                        <div class="pi-pay-chip"><i class="fa-solid fa-credit-card"></i> Virtual Account</div>
                        <div class="pi-pay-chip"><i class="fa-solid fa-mobile-screen"></i> E-Wallet</div>
                        <div class="pi-pay-chip"><i class="fa-solid fa-globe"></i> Payment Gateway</div>
                    </div>
                </div>

                <div class="pi-divider"></div>

                {{-- Services --}}
                <div class="pi-section">
                    <div class="pi-section-title">Layanan Kami</div>
                    <div class="pi-services">
                        <div class="pi-svc"><i class="fa-solid fa-wifi"></i> Fiber Optik</div>
                        <div class="pi-svc"><i class="fa-solid fa-infinity"></i> Internet Unlimited</div>
                        <div class="pi-svc"><i class="fa-solid fa-headset"></i> Customer Support</div>
                        <div class="pi-svc"><i class="fa-solid fa-chart-line"></i> Monitoring 24 Jam</div>
                        <div class="pi-svc"><i class="fa-solid fa-credit-card"></i> Pembayaran Online</div>
                        <div class="pi-svc"><i class="fa-solid fa-bolt"></i> Instalasi Cepat</div>
                    </div>
                </div>

                <div class="pi-divider"></div>

                {{-- Status --}}
                <div class="pi-section">
                    <div class="pi-section-title">Status Sistem</div>
                    <div class="pi-status">
                        <div class="pi-st-chip"><div class="pi-st-dot"></div> System Online</div>
                        <div class="pi-st-chip"><div class="pi-st-dot"></div> Network Stable</div>
                        <div class="pi-st-chip"><div class="pi-st-dot"></div> Payment Active</div>
                        <div class="pi-st-chip"><div class="pi-st-dot"></div> Server Normal</div>
                    </div>
                </div>

                <div class="pi-divider"></div>

                {{-- Contact --}}
                <div class="pi-section">
                    <div class="pi-section-title">Hubungi Kami</div>
                    <div class="pi-contact">
                        @if($company['phone'])
                            <div class="pi-contact-row"><i class="fa-brands fa-whatsapp"></i> {{ $company['phone'] }}</div>
                        @endif
                        <div class="pi-contact-row"><i class="fa-solid fa-clock"></i> Senin – Sabtu, 08:00 – 17:00 WIB</div>
                        @if($company['address'])
                            <div class="pi-contact-row"><i class="fa-solid fa-location-dot"></i> {{ $company['address'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Social --}}
                <div class="pi-social">
                    <a href="#" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" title="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#" title="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>

            </div>
        </div>
    </div>

    {{-- ═══════ STATUS BAR ═══════ --}}
    <div class="portal-status-bar">
        <div class="portal-sb-item"><div class="portal-sb-dot"></div> System Online</div>
        <div class="portal-sb-item">Laravel</div>
        <div class="portal-sb-item">Fiber Network</div>
        <div class="portal-sb-item">Secure Connection</div>
    </div>
    <div class="portal-copyright">&copy; 2026 {{ $company['name'] }} &middot; Powered by Fiber Network Technology</div>

    <script>
    (function() {
        var btn = document.getElementById('portalBtn');
        var form = document.getElementById('portalForm');
        if (form && btn) {
            form.addEventListener('submit', function() {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border" role="status"></span><span class="btn-text">Mencari...</span>';
            });
        }
    })();
    </script>
</body>
</html>
