@extends('layouts.app')

@section('title', 'Daftar')

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
            <i class="fa-solid fa-shield-halved" style="background:linear-gradient(135deg,#22c55e,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:0.85rem;"></i>
            <div>
                <span>Enterprise Security</span>
                <small>End-to-End Encrypted</small>
            </div>
        </div>
    </div>

    {{-- LEFT: Hero Section --}}
    <div class="auth-hero">
        <div class="auth-hero-brand">
            <img src="{{ asset('images/logo.png') }}" alt="ALKONEKbill" class="auth-hero-logo">
            <div class="auth-hero-brand-text">
                <h2>ALKONEKbill</h2>
                <span>ISP Platform</span>
            </div>
        </div>

        <h1 class="auth-hero-title">
            Bangun <span class="highlight">Infrastruktur</span><br>
            ISP Terbaik
        </h1>
        <p class="auth-hero-desc">
            Daftarkan operator untuk mengelola sistem billing, monitoring jaringan, dan operasional ISP dari satu platform terintegrasi.
        </p>

        {{-- Metric Cards --}}
        <div class="auth-metrics">
            <div class="auth-metric auth-metric-blue">
                <div class="auth-metric-icon"><i class="fa-solid fa-file-invoice"></i></div>
                <strong>487</strong>
                <span>Invoice Bulan Ini</span>
            </div>
            <div class="auth-metric auth-metric-green">
                <div class="auth-metric-icon"><i class="fa-solid fa-circle-check"></i></div>
                <strong>99.9%</strong>
                <span>SLA Terpenuhi</span>
            </div>
            <div class="auth-metric auth-metric-purple">
                <div class="auth-metric-icon"><i class="fa-solid fa-ticket"></i></div>
                <strong>892</strong>
                <span>Voucher Aktif</span>
            </div>
            <div class="auth-metric auth-metric-amber">
                <div class="auth-metric-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <strong>Rp 186jt</strong>
                <span>Revenue Bulan Ini</span>
            </div>
            <div class="auth-metric auth-metric-cyan">
                <div class="auth-metric-icon"><i class="fa-solid fa-headset"></i></div>
                <strong>3</strong>
                <span>Ticket Aktif</span>
            </div>
            <div class="auth-metric auth-metric-rose">
                <div class="auth-metric-icon"><i class="fa-solid fa-network-wired"></i></div>
                <strong>2.4 Tbps</strong>
                <span>Total Traffic</span>
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
                <h3>Buat Akun Admin</h3>
                <p>Daftarkan operator yang akan mengelola sistem billing.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" id="registerForm">
                @csrf

                <div class="auth-field">
                    <label for="name">Nama Lengkap</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-user auth-input-icon"></i>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="auth-input @error('name') is-invalid @enderror"
                            value="{{ old('name') }}"
                            placeholder="Nama lengkap operator"
                            required
                            autofocus
                            autocomplete="name"
                        >
                    </div>
                    @error('name')
                        <div class="auth-error"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
                    @enderror
                </div>

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
                            placeholder="admin@alkonek.net"
                            required
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
                            placeholder="Minimal 8 karakter"
                            required
                            autocomplete="new-password"
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

                    {{-- Password Strength --}}
                    <div class="auth-pw-strength" id="pwStrength" style="display:none;">
                        <div class="auth-pw-bar"><div class="auth-pw-bar-fill" id="pwBar"></div></div>
                        <div class="auth-pw-label" id="pwLabel"></div>
                        <div class="auth-pw-checks">
                            <div class="auth-pw-check" id="pwCheck8"><i class="fa-solid fa-check"></i> 8+ karakter</div>
                            <div class="auth-pw-check" id="pwCheckUpper"><i class="fa-solid fa-check"></i> Huruf besar</div>
                            <div class="auth-pw-check" id="pwCheckLower"><i class="fa-solid fa-check"></i> Huruf kecil</div>
                            <div class="auth-pw-check" id="pwCheckNumber"><i class="fa-solid fa-check"></i> Angka</div>
                            <div class="auth-pw-check" id="pwCheckSymbol"><i class="fa-solid fa-check"></i> Simbol</div>
                        </div>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password-confirm">Konfirmasi Kata Sandi</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-shield-halved auth-input-icon"></i>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password-confirm"
                            class="auth-input"
                            placeholder="Ulangi kata sandi"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                    <div class="auth-pw-match no-match" id="pwMatch">
                        <i class="fa-solid fa-circle-xmark"></i> <span>Kata sandi belum cocok</span>
                    </div>
                </div>

                <button type="submit" class="auth-submit" id="registerBtn" disabled>
                    <span class="btn-text">Daftar</span>
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
                Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
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
    <div class="auth-copyright">&copy; 2026 PT. Alkonek Network Access. All rights reserved.</div>
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

    // Password Strength
    const checks = {
        eight:    document.getElementById('pwCheck8'),
        upper:    document.getElementById('pwCheckUpper'),
        lower:    document.getElementById('pwCheckLower'),
        number:   document.getElementById('pwCheckNumber'),
        symbol:   document.getElementById('pwCheckSymbol'),
    };
    const pwBar    = document.getElementById('pwBar');
    const pwLabel  = document.getElementById('pwLabel');
    const pwStrength = document.getElementById('pwStrength');
    const pwMatchEl  = document.getElementById('pwMatch');
    const pwConfirm  = document.getElementById('password-confirm');
    const registerBtn = document.getElementById('registerBtn');

    const strengthLevels = [
        { label: 'Sangat Lemah', cls: 'weak' },
        { label: 'Lemah', cls: 'weak' },
        { label: 'Sedang', cls: 'medium' },
        { label: 'Kuat', cls: 'strong' },
        { label: 'Sangat Kuat', cls: 'very-strong' },
    ];

    function evaluatePassword(pw) {
        let score = 0;
        const tests = {
            eight:  pw.length >= 8,
            upper:  /[A-Z]/.test(pw),
            lower:  /[a-z]/.test(pw),
            number: /[0-9]/.test(pw),
            symbol: /[^A-Za-z0-9]/.test(pw),
        };
        Object.keys(tests).forEach(k => {
            if (tests[k]) {
                score++;
                checks[k].classList.add('passed');
            } else {
                checks[k].classList.remove('passed');
            }
        });
        return score;
    }

    function updateStrength(pw) {
        if (!pw) {
            pwStrength.style.display = 'none';
            return;
        }
        pwStrength.style.display = 'block';
        const score = evaluatePassword(pw);
        const level = strengthLevels[Math.min(score, 4)];
        pwBar.className = 'auth-pw-bar-fill ' + (score > 0 ? level.cls : '');
        pwLabel.className = 'auth-pw-label ' + (score > 0 ? level.cls : '');
        pwLabel.textContent = score > 0 ? level.label : '';
    }

    function checkMatch() {
        const pw = pwInput ? pwInput.value : '';
        const confirm = pwConfirm ? pwConfirm.value : '';
        if (!confirm) {
            pwMatchEl.className = 'auth-pw-match no-match';
            pwMatchEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> <span>Kata sandi belum cocok</span>';
            registerBtn.disabled = true;
            return false;
        }
        if (pw === confirm) {
            pwMatchEl.className = 'auth-pw-match match';
            pwMatchEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Kata sandi cocok</span>';
            registerBtn.disabled = false;
            return true;
        } else {
            pwMatchEl.className = 'auth-pw-match no-match';
            pwMatchEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> <span>Kata sandi tidak cocok</span>';
            registerBtn.disabled = true;
            return false;
        }
    }

    if (pwInput) {
        pwInput.addEventListener('input', function() {
            updateStrength(this.value);
            checkMatch();
        });
    }
    if (pwConfirm) {
        pwConfirm.addEventListener('input', checkMatch);
    }

    // Loading State on Submit
    const form = document.getElementById('registerForm');
    const btn = document.getElementById('registerBtn');
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border" role="status"></span><span class="btn-text">Memproses...</span>';
        });
    }
})();
</script>
@endpush
