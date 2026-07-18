@extends('layouts.app')

@section('title', 'Masuk')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="auth-shell">
    {{-- Animated Background --}}
    <div class="auth-bg"></div>
    <div class="auth-grid"></div>
    <div class="auth-noise"></div>

    {{-- Floating Status Cards --}}
    <div class="auth-floating" style="top:18%;right:60%;z-index:3;">
        <div class="auth-float-card" style="animation-delay:0s;">
            <div class="dot" style="background:#34d399;"></div>
            <div>
                <span>System Online</span>
                <small>99.9% Uptime</small>
            </div>
        </div>
    </div>
    <div class="auth-floating" style="bottom:22%;right:59%;z-index:3;">
        <div class="auth-float-card" style="animation-delay:2s;">
            <i class="fa-solid fa-tower-broadcast" style="color:#60a5fa;font-size:0.85rem;"></i>
            <div>
                <span>OLT Active</span>
                <small>4/4 Ports Online</small>
            </div>
        </div>
    </div>

    {{-- LEFT: Hero Section --}}
    <div class="auth-hero">
        <div class="auth-hero-brand">
            <img src="{{ asset('images/logo.png') }}" alt="RabegNet" class="auth-hero-logo">
            <div class="auth-hero-brand-text">
                <h2>RabegNet</h2>
                <span>ISP Platform</span>
            </div>
        </div>

        <h1 class="auth-hero-title">
            <span class="highlight">RabegNet</span><br>
            ISP Platform
        </h1>
        <p class="auth-hero-desc">
            Platform terintegrasi untuk mengelola pelanggan, jaringan fiber optik, billing, voucher hotspot, monitoring OLT, MikroTik, pembayaran, dan operasional ISP.
        </p>

        {{-- Metric Cards --}}
        <div class="auth-metrics">
            <div class="auth-metric auth-metric-blue">
                <div class="auth-metric-icon"><i class="fa-solid fa-users"></i></div>
                <strong>1,247</strong>
                <span>Pelanggan Aktif</span>
            </div>
            <div class="auth-metric auth-metric-green">
                <div class="auth-metric-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                <strong>4</strong>
                <span>OLT Online</span>
            </div>
            <div class="auth-metric auth-metric-purple">
                <div class="auth-metric-icon"><i class="fa-solid fa-router"></i></div>
                <strong>12</strong>
                <span>Router Active</span>
            </div>
            <div class="auth-metric auth-metric-amber">
                <div class="auth-metric-icon"><i class="fa-solid fa-chart-line"></i></div>
                <strong>99.8%</strong>
                <span>Network Uptime</span>
            </div>
            <div class="auth-metric auth-metric-cyan">
                <div class="auth-metric-icon"><i class="fa-solid fa-gauge-high"></i></div>
                <strong>2.4 Gbps</strong>
                <span>Bandwidth Hari Ini</span>
            </div>
            <div class="auth-metric auth-metric-rose">
                <div class="auth-metric-icon"><i class="fa-solid fa-id-card"></i></div>
                <strong>892</strong>
                <span>Voucher Aktif</span>
            </div>
        </div>

        {{-- Network Visualization --}}
        <div class="auth-network">
            <div class="auth-network-title">Network Topology</div>
            <div class="auth-network-flow">
                <div class="auth-network-node">
                    <i class="fa-solid fa-globe"></i>
                    <span>Internet</span>
                </div>
                <div class="auth-network-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="auth-network-node">
                    <i class="fa-solid fa-server"></i>
                    <span>Core Router</span>
                </div>
                <div class="auth-network-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="auth-network-node">
                    <i class="fa-solid fa-tower-cell"></i>
                    <span>OLT</span>
                </div>
                <div class="auth-network-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="auth-network-node">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>ODC</span>
                </div>
                <div class="auth-network-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="auth-network-node">
                    <i class="fa-solid fa-circle-nodes"></i>
                    <span>ODP</span>
                </div>
                <div class="auth-network-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="auth-network-node">
                    <i class="fa-solid fa-house-signal"></i>
                    <span>Customer</span>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: Form Panel --}}
    <div class="auth-form-panel">
        <div class="auth-form-glass">
            <div class="auth-form-header">
                <h3>Masuk ke RabegNet</h3>
                <p>Akses panel billing, pelanggan, voucher, dan monitoring.</p>
            </div>

            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf

                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-envelope auth-input-icon"></i>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="auth-input @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            placeholder="admin@rabegnet.id"
                            required
                            autofocus
                            autocomplete="username"
                        >
                    </div>
                    @error('email')
                        <div class="auth-error"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-field">
                    <label for="password">Kata Sandi</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-lock auth-input-icon"></i>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="auth-input @error('password') is-invalid @enderror"
                            placeholder="Masukkan kata sandi"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="auth-input-toggle" id="togglePassword" tabindex="-1" aria-label="Tampilkan sandi">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="auth-error"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
                    @enderror
                    <div class="auth-capslock" id="capsLockIndicator">
                        <i class="fa-solid fa-arrow-up"></i> Caps Lock aktif
                    </div>
                </div>

                <div class="auth-remember">
                    <label>
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="auth-submit" id="loginBtn">
                    <span class="btn-text">Masuk</span>
                </button>
            </form>

            <div class="auth-divider"><span>atau</span></div>

            <div class="auth-social">
                <a href="{{ route('auth.redirect', 'google') }}" class="auth-social-btn google">
                    <i class="fa-brands fa-google"></i> Google
                </a>
                <a href="{{ route('auth.redirect', 'github') }}" class="auth-social-btn github">
                    <i class="fa-brands fa-github"></i> GitHub
                </a>
            </div>

            <div class="auth-footer-link">
                Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
            </div>
        </div>
    </div>

    {{-- Status Bar --}}
    <div class="auth-status-bar">
        <div class="auth-status-item"><div class="dot"></div> System Online</div>
        <div class="auth-status-item">Laravel {{ app()->version() }}</div>
        <div class="auth-status-item">PHP {{ phpversion() }}</div>
        <div class="auth-status-item">MySQL</div>
        <div class="auth-status-item">Fiber Network</div>
    </div>
    <div class="auth-copyright">&copy; 2026 RabegNet ISP Billing Platform &middot; Built with Laravel &middot; Powered by Rangga Dev access </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    // Show/Hide Password
    const toggleBtn = document.getElementById('togglePassword');
    const pwInput = document.getElementById('password');
    if (toggleBtn && pwInput) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            this.querySelector('i').className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // Caps Lock Indicator
    if (pwInput) {
        pwInput.addEventListener('keyup', function(e) {
            const indicator = document.getElementById('capsLockIndicator');
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                indicator.classList.add('show');
            } else {
                indicator.classList.remove('show');
            }
        });
    }

    // Loading State on Submit
    const form = document.getElementById('loginForm');
    const btn = document.getElementById('loginBtn');
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border" role="status"></span><span class="btn-text">Memproses...</span>';
        });
    }
})();
</script>
@endpush
