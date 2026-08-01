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
    html { font-size: 14px; scrollbar-width: none; -ms-overflow-style: none; }
    html::-webkit-scrollbar { display: none; width: 0; height: 0; }

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
        padding: 40px 48px; position: relative; overflow: hidden;
        animation: heroSlideLeft 0.7s var(--ease) both;
    }
    @keyframes heroSlideLeft { from{opacity:0;transform:translateX(-32px)} to{opacity:1;transform:translateX(0)} }

    .portal-form-side {
        flex: 0 0 45%; display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 32px 40px 48px; position: relative; z-index: 1;
        animation: formSlideRight 0.7s var(--ease) 0.12s both;
    }
    @keyframes formSlideRight { from{opacity:0;transform:translateX(32px)} to{opacity:1;transform:translateX(0)} }

    /* ═══════════ HERO ═══════════ */
    .logo-chip {
        display: inline-flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #bfdbfe, #c4b5fd, #fbcfe8);
        border-radius: 14px; padding: 3px;
        box-shadow: 0 10px 26px rgba(129,140,248,0.35);
        overflow: hidden; isolation: isolate; flex-shrink: 0;
    }
    .logo-chip img {
        display: block; width: 100%; height: 100%;
        object-fit: contain; mix-blend-mode: multiply;
    }
    .portal-brand {
        display: flex; align-items: center; gap: 10px; margin-bottom: 28px;
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
        font-size: clamp(1.5rem, 2.6vw, 2.2rem); font-weight: 900; line-height: 1.08;
        letter-spacing: -0.05em; margin-bottom: 12px;
    }
    .portal-hero-title .hl {
        background: linear-gradient(135deg, #60a5fa, #a78bfa, #60a5fa);
        background-size: 200% auto;
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        animation: shimmer 4s ease-in-out infinite;
    }
    @keyframes shimmer { 0%,100%{background-position:0% center} 50%{background-position:200% center} }

    .portal-hero-desc {
        font-size: 0.86rem; line-height: 1.7; color: var(--p-text-dim);
        max-width: 460px; margin-bottom: 22px;
    }

    /* ─── Floating Cards ─── */
    .portal-float {
        position: absolute; display: flex; align-items: center; gap: 8px;
        padding: 9px 12px; border-radius: 12px; border: 1px solid var(--p-border);
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
        width: 100%; max-width: 340px; padding: 20px 18px;
        border-radius: 16px; border: 1px solid var(--p-border);
        background: rgba(255,255,255,0.04); backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        box-shadow: 0 1px 0 rgba(255,255,255,0.06) inset, 0 32px 80px rgba(0,0,0,0.35);
        position: relative; overflow: hidden; margin: auto 0;
    }
    .portal-glass::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    }

    .portal-form-header { text-align: center; margin-bottom: 14px; }
    .portal-form-header .icon-circle {
        width: 34px; height: 34px; border-radius: 10px; display: inline-flex;
        align-items: center; justify-content: center; margin-bottom: 8px;
        background: linear-gradient(135deg, var(--p-blue), var(--p-accent));
        box-shadow: 0 12px 30px rgba(37,99,235,0.25); color: #fff; font-size: 0.85rem;
    }
    .portal-form-header h3 {
        font-size: 0.98rem; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 4px;
    }
    .portal-form-header p { font-size: 0.72rem; color: var(--p-text-dim); margin: 0; }

    /* ─── Inputs ─── */
    .pi-group { margin-bottom: 12px; animation: fadeUp 0.4s var(--ease) 0.3s both; }
    .pi-label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--p-text-dim); margin-bottom: 5px; }
    .pi-wrap { position: relative; display: flex; align-items: center; }
    .pi-wrap .pi-icon {
        position: absolute; left: 12px; color: var(--p-text-muted); font-size: 0.8rem;
        pointer-events: none; transition: color 0.25s; z-index: 2;
    }
    .pi-wrap:focus-within .pi-icon { color: #60a5fa; }
    .pi-input {
        width: 100%; padding: 11px 12px 11px 38px; border: 1.5px solid var(--p-border);
        border-radius: 10px; background: rgba(255,255,255,0.04); color: #fff;
        font-size: 0.82rem; font-family: inherit; outline: none; transition: all 0.25s var(--ease);
    }
    .pi-input::placeholder { color: var(--p-text-muted); }
    .pi-input:focus { border-color: rgba(96,165,250,0.5); background: rgba(255,255,255,0.06); box-shadow: 0 0 0 3px rgba(96,165,250,0.1), 0 0 20px rgba(96,165,250,0.06); }
    .pi-input.is-invalid { border-color: rgba(239,68,68,0.5) !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.08) !important; }
    .pi-error { display: flex; align-items: center; gap: 6px; margin-top: 5px; font-size: 0.72rem; color: #fca5a5; }
    .pi-error i { font-size: 0.68rem; }

    /* ─── Alert ─── */
    .pi-alert {
        display: flex; align-items: center; gap: 8px; padding: 10px 12px;
        border-radius: 10px; font-size: 0.76rem; font-weight: 500; margin-bottom: 12px;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5;
        animation: fadeUp 0.3s var(--ease);
    }
    .pi-alert i { font-size: 0.75rem; }

    /* ─── Success Alert ─── */
    .pi-success {
        position: relative; display: flex; align-items: center; gap: 12px;
        padding: 14px 40px 14px 14px; border-radius: 14px; margin-bottom: 14px;
        background: linear-gradient(135deg, rgba(34,197,94,0.16), rgba(16,185,129,0.10));
        border: 1px solid rgba(34,197,94,0.45);
        animation: successPulse 2.4s ease-in-out infinite;
    }
    .pi-success-icon {
        width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #22c55e, #10b981); color: #fff; font-size: 1rem;
        box-shadow: 0 0 16px rgba(34,197,94,0.6);
    }
    .pi-success-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .pi-success-text strong { color: #4ade80; font-size: 0.85rem; font-weight: 800; letter-spacing: -0.01em; }
    .pi-success-text span { color: rgba(255,255,255,0.85); font-size: 0.76rem; line-height: 1.5; }
    .pi-success-close {
        position: absolute; top: 8px; right: 8px; width: 24px; height: 24px;
        border: none; border-radius: 8px; background: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.6); font-size: 0.7rem; cursor: pointer;
    }
    .pi-success-close:hover { background: rgba(255,255,255,0.16); color: #fff; }
    @keyframes successPulse {
        0%, 100% { box-shadow: 0 0 20px rgba(34,197,94,0.30), inset 0 0 12px rgba(34,197,94,0.06); }
        50% { box-shadow: 0 0 36px rgba(34,197,94,0.55), inset 0 0 16px rgba(34,197,94,0.12); }
    }

    /* ─── Submit Button ─── */
    .pi-submit {
        width: 100%; padding: 9px 16px; border: none; border-radius: 10px;
        background: linear-gradient(135deg, var(--p-blue), var(--p-accent));
        color: #fff; font-size: 0.8rem; font-weight: 700; font-family: inherit;
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
    .pi-section { margin-top: 10px; animation: fadeUp 0.4s var(--ease) 0.5s both; }
    .pi-section-title {
        font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--p-text-muted); margin-bottom: 8px;
    }

    /* Payment Methods */
    .pi-payments { display: flex; flex-wrap: wrap; gap: 8px; }
    .pi-pay-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 10px; border-radius: 8px; border: 1px solid var(--p-border);
        background: rgba(255,255,255,0.03); font-size: 0.64rem; font-weight: 600;
        color: var(--p-text-dim); transition: all 0.25s var(--ease);
    }
    .pi-pay-chip i { font-size: 0.72rem; }
    .pi-pay-chip:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.15); color: var(--p-text); }

    /* Contact */
    .pi-contact { display: flex; flex-direction: column; gap: 4px; }
    .pi-contact-row {
        display: flex; flex-direction: column; gap: 1px;
        background: rgba(255,255,255,0.04); border: 1px solid var(--p-border);
        border-radius: 6px; padding: 4px 8px;
    }
    .pi-contact-head {
        display: flex; align-items: center; gap: 5px;
        color: rgba(255,255,255,0.7); font-weight: 700; font-size: 0.58rem;
        letter-spacing: 0.03em; text-transform: uppercase;
    }
    .pi-contact-head i { width: 12px; text-align: center; color: #60a5fa; font-size: 0.64rem; flex-shrink: 0; }
    .pi-contact-value { color: #fff; font-size: 0.66rem; line-height: 1.45; padding-left: 17px; word-break: break-word; }

    /* Divider */
    .pi-divider {
        height: 1px; background: linear-gradient(90deg, transparent, var(--p-border), transparent);
        margin: 8px 0;
    }

    /* ═══════════ STATUS BAR ═══════════ */
    .portal-status-bar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 10;
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
        padding: 7px 18px; background: rgba(6,11,24,0.92);
        border-top: 1px solid var(--p-border); backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .portal-copyright {
        font-size: 0.64rem; color: rgba(255,255,255,0.4); font-weight: 500;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: 45vw;
    }
    .portal-sb-items { display: flex; align-items: center; gap: 18px; }
    .portal-sb-item {
        display: flex; align-items: center; gap: 5px;
        font-size: 0.62rem; font-weight: 500; color: var(--p-text-muted);
    }
    .portal-sb-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--p-green); box-shadow: 0 0 5px rgba(34,197,94,0.5); }

    /* ═══════════ INVOICE MODAL ═══════════ */
    .portal-modal-overlay {
        position: fixed; inset: 0; z-index: 1080;
        display: flex; align-items: center; justify-content: center; padding: 20px;
        background: rgba(2, 6, 17, 0.62);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        opacity: 0; visibility: hidden;
        transition: opacity 0.35s var(--ease), visibility 0.35s var(--ease);
    }
    .portal-modal-overlay.show { opacity: 1; visibility: visible; }
    .portal-modal {
        width: 100%; max-width: 460px; max-height: 88vh; overflow-y: auto;
        background: linear-gradient(180deg, #0d1a33, #0a1628);
        border: 1px solid rgba(255,255,255,0.1); border-radius: 18px;
        box-shadow: 0 30px 80px rgba(0,0,0,0.55);
        transform: translateY(24px) scale(0.97);
        transition: transform 0.4s var(--ease);
    }
    .portal-modal-overlay.show .portal-modal { transform: translateY(0) scale(1); }
    .portal-modal::-webkit-scrollbar { width: 6px; }
    .portal-modal::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.14); border-radius: 99px; }
    body.pm-lock { overflow: hidden; }

    .pm-body { padding: 18px; }
    .pm-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; }
    .pm-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .pm-brand-name { font-weight: 800; font-size: 0.95rem; color: #fff; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pm-brand-sub { font-size: 0.7rem; color: var(--p-text-dim); margin-top: 1px; }
    .pm-close {
        width: 28px; height: 28px; flex-shrink: 0; border: none; border-radius: 8px;
        background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.7);
        font-size: 0.85rem; cursor: pointer; transition: background 0.2s, color 0.2s;
    }
    .pm-close:hover { background: rgba(255,255,255,0.14); color: #fff; }
    .pm-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
    .pm-stat { background: var(--p-surface); border: 1px solid var(--p-border); border-radius: 12px; padding: 10px 6px; text-align: center; }
    .pm-stat-num { font-weight: 800; font-size: 1.05rem; color: #fff; }
    .pm-stat-money { font-size: 0.8rem; color: var(--p-blue-light); }
    .pm-stat-label { font-size: 0.6rem; color: var(--p-text-muted); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 3px; }
    .pm-section { margin-bottom: 4px; }
    .pm-section-title { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--p-text-dim); margin-bottom: 10px; }
    .pm-section-title i { margin-right: 6px; color: var(--p-blue-light); }
    .pm-invoice {
        display: flex; justify-content: space-between; align-items: center; gap: 10px;
        background: var(--p-surface); border: 1px solid var(--p-border);
        border-radius: 12px; padding: 10px 12px; margin-bottom: 8px;
    }
    .pm-inv-id { font-weight: 700; font-size: 0.85rem; color: rgba(255,255,255,0.9); }
    .pm-inv-date { font-size: 0.7rem; color: var(--p-text-dim); margin-top: 3px; }
    .pm-inv-date i { margin-right: 4px; }
    .pm-inv-right { text-align: right; flex-shrink: 0; }
    .pm-inv-amount { font-weight: 800; font-size: 0.95rem; color: #fff; margin-bottom: 6px; }
    .pm-badge { display: inline-block; font-size: 0.64rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
    .pm-paid { background: rgba(34,197,94,0.12); color: #4ade80; }
    .pm-unpaid { background: rgba(244,63,94,0.12); color: #fb7185; }
    .pm-pay-btn {
        display: inline-block; margin-top: 6px; background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff; font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
        border-radius: 10px; text-decoration: none;
    }
    .pm-pay-btn:hover { color: #fff; opacity: 0.9; }
    .pm-incident { background: var(--p-surface); border: 1px solid var(--p-border); border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; }
    .pm-incident-title { font-weight: 700; font-size: 0.85rem; color: #fbbf24; }
    .pm-incident-meta { font-size: 0.7rem; color: var(--p-text-dim); margin-top: 4px; }
    .pm-empty { text-align: center; padding: 28px 0; color: var(--p-text-dim); }
    .pm-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; color: var(--p-text-muted); }
    .pm-empty p { font-size: 0.8rem; margin: 0; }
    .pm-foot { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--p-border); text-align: center; font-size: 0.7rem; color: var(--p-text-muted); }

    /* ─── Riwayat Pembayaran toggle ─── */
    .pm-history { margin-bottom: 4px; }
    .pm-history-toggle {
        width: 100%; display: flex; align-items: center; gap: 10px;
        background: rgba(255,255,255,0.05); border: 1px solid var(--p-border);
        border-radius: 10px; padding: 9px 12px; cursor: pointer;
        color: rgba(255,255,255,0.85); font-size: 0.8rem; font-weight: 700;
        transition: background 0.2s, border-color 0.2s;
    }
    .pm-history-toggle:hover { background: rgba(255,255,255,0.09); border-color: rgba(255,255,255,0.16); }
    .pm-history-toggle > i.fa-clock-rotate-left { color: var(--p-blue-light); }
    .pm-history-count { margin-left: auto; font-size: 0.7rem; font-weight: 600; color: var(--p-text-dim); }
    .pm-caret { font-size: 0.7rem; color: var(--p-text-dim); transition: transform 0.3s var(--ease); }
    .pm-history.collapsed .pm-caret { transform: rotate(-90deg); }
    .pm-history-body { margin-top: 10px; }
    .pm-history.collapsed .pm-history-body { display: none; }
    .pm-month { margin-bottom: 12px; }
    .pm-month-head {
        display: flex; justify-content: space-between; align-items: center; gap: 8px;
        margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--p-border);
    }
    .pm-month-head > span:first-child {
        font-weight: 800; font-size: 0.75rem; color: rgba(255,255,255,0.85);
        text-transform: uppercase; letter-spacing: 0.06em;
    }
    .pm-month-sub { font-size: 0.66rem; color: var(--p-text-dim); font-weight: 600; white-space: nowrap; }

    /* ═══════════ ANIMATIONS ═══════════ */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* ═══════════ RESPONSIVE ═══════════ */
    @media (max-width: 1024px) {
        .portal-hero { flex: 0 0 50%; padding: 32px 28px; }
        .portal-form-side { flex: 0 0 50%; padding: 28px 24px; }
        .portal-hero-title { font-size: 1.6rem; }
    }
    @media (max-width: 768px) {
        .portal-wrapper { flex-direction: column; }
        .portal-hero { flex: 0 0 auto; padding: 16px 14px 6px; }
        .portal-hero-title { font-size: 1.2rem; }
        .portal-hero-desc { font-size: 0.8rem; margin-bottom: 0; }
        .portal-brand { margin-bottom: 10px; }
        .portal-float { display: none; }
        .portal-form-side { flex: 0 0 auto; padding: 10px 14px 10px; align-items: flex-start; }
        .portal-glass { padding: 18px 16px; border-radius: 14px; }
        .portal-form-header h3 { font-size: 0.95rem; }
        .portal-status-bar { gap: 10px; flex-wrap: wrap; justify-content: space-between; }
        .portal-sb-items { gap: 12px; }
        .portal-copyright { max-width: 38vw; }
    }
    @media (max-width: 480px) {
        .portal-hero { padding: 16px 12px 10px; }
        .portal-brand h2 { font-size: 1.05rem; }
        .portal-hero-title { font-size: 1.15rem; }
        .portal-glass { padding: 18px 14px; }
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
                <span class="logo-chip" style="width:48px;height:48px;border-radius:12px;">
                    <img src="{{ asset('images/logo-alkonek.gif') }}" alt="Logo ALKONEK">
                </span>
                <div>
                    <h2>ALKONEK</h2>
                    <small>PT. Alkonek Network Access</small>
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
        </div>

        {{-- ═══════ RIGHT: FORM PORTAL ═══════ --}}
        <div class="portal-form-side">
            <div class="portal-glass">
                <div class="portal-form-header">
                    <div class="icon-circle"><i class="fa-solid fa-wallet"></i></div>
                    <h3>Portal Pelanggan</h3>
                    <p>Cek tagihan, riwayat pembayaran, dan lakukan pembayaran online dengan mudah.</p>
                </div>

                {{-- Success --}}
                @if(session('success'))
                    <div class="pi-success">
                        <div class="pi-success-icon"><i class="fa-solid fa-check"></i></div>
                        <div class="pi-success-text">
                            <strong>Terima Kasih, Pembayaran Berhasil!</strong>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button type="button" class="pi-success-close" data-close-success aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @endif

                {{-- Error --}}
                @if(session('error'))
                    <div class="pi-alert"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
                @endif
                <div id="portalErr"></div>

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

                {{-- Contact --}}
                <div class="pi-section">
                    <div class="pi-section-title">Hubungi Kami</div>
                    <div class="pi-contact">
                        <div class="pi-contact-row">
                            <div class="pi-contact-head"><i class="fa-solid fa-location-dot"></i> Alamat:</div>
                            <div class="pi-contact-value">Kp. Malangnengah RT/RW 004/001 Desa Bendungan Kec. Banjarsari Kab. Lebak-Banten</div>
                        </div>
                        <div class="pi-contact-row">
                            <div class="pi-contact-head"><i class="fa-brands fa-whatsapp"></i> No. WA:</div>
                            <div class="pi-contact-value">089531559066</div>
                        </div>
                        <div class="pi-contact-row">
                            <div class="pi-contact-head"><i class="fa-solid fa-envelope"></i> Email:</div>
                            <div class="pi-contact-value">alkoneknetworkaccess@gmail.com</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ═══════ INVOICE MODAL ═══════ --}}
    <div class="portal-modal-overlay" id="portalModal" aria-hidden="true">
        <div class="portal-modal" role="dialog" aria-modal="true">
            <div class="pm-body" id="portalModalBody"></div>
        </div>
    </div>

    {{-- ═══════ STATUS BAR ═══════ --}}
    <div class="portal-status-bar">
        <div class="portal-copyright">&copy; 2026 {{ $company['name'] }} &middot; Powered by Fiber Network Technology</div>
        <div class="portal-sb-items">
            <div class="portal-sb-item"><div class="portal-sb-dot"></div> System Online</div>
            <div class="portal-sb-item"><div class="portal-sb-dot"></div> Laravel</div>
            <div class="portal-sb-item"><div class="portal-sb-dot"></div> Fiber Network</div>
            <div class="portal-sb-item"><div class="portal-sb-dot"></div> Secure Connection</div>
        </div>
    </div>

    <script>
    (function() {
        var form = document.getElementById('portalForm');
        var overlay = document.getElementById('portalModal');
        var body = document.getElementById('portalModalBody');
        var btn = document.getElementById('portalBtn');
        var errWrap = document.getElementById('portalErr');
        var pending = false;

        function setLoading(on) {
            if (!btn) return;
            btn.disabled = on;
            btn.innerHTML = on
                ? '<span class="spinner-border spinner-border-sm" role="status"></span><span class="btn-text">Mencari...</span>'
                : '<i class="fa-solid fa-search me-2"></i><span class="btn-text">Cari Tagihan</span>';
        }

        function showError(msg) {
            if (!errWrap) return;
            errWrap.innerHTML = '<div class="pi-alert"><i class="fa-solid fa-circle-exclamation"></i> ' + msg + '</div>';
            errWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function clearError() {
            if (errWrap) errWrap.innerHTML = '';
        }

        function openModal(html) {
            body.innerHTML = html;
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pm-lock');
        }

        function closeModal() {
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pm-lock');
        }

        if (form && overlay && btn) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (pending) return;
                pending = true;
                clearError();
                setLoading(true);

                var fd = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd
                })
                .then(function(res) {
                    return res.json().then(function(data) {
                        if (!res.ok) {
                            if (data && data.message) throw new Error(data.message);
                            throw new Error('Terjadi kesalahan. Silakan coba lagi.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    if (data && data.found) {
                        openModal(data.html);
                    } else {
                        showError((data && data.message) || 'Nomor telepon tidak ditemukan.');
                    }
                })
                .catch(function(err) {
                    showError(err.message || 'Terjadi kesalahan. Silakan coba lagi.');
                })
                .finally(function() {
                    pending = false;
                    setLoading(false);
                });
            });

            overlay.addEventListener('click', function(e) {
                var toggle = e.target.closest('[data-history-toggle]');
                if (toggle) {
                    var history = toggle.closest('.pm-history');
                    if (history) {
                        var collapsed = history.classList.toggle('collapsed');
                        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    }
                    return;
                }

                if (e.target === overlay || e.target.closest('[data-close]')) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });

            document.addEventListener('click', function(e) {
                if (e.target.closest('[data-close-success]')) {
                    var alert = e.target.closest('.pi-success');
                    if (alert) alert.remove();
                }
            });
        }
    })();
    </script>
</body>
</html>
