@extends('layouts.app')

@section('title', 'Masuk')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
    .auth-hero-brand-text h2.auth-brand-name {
        font-family: 'Space Grotesk', 'Inter', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: 0.25em;
        line-height: 1.05;
        background: linear-gradient(135deg, #ffffff 20%, #93c5fd 90%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: #ffffff;
    }
    .auth-brand-tag {
        font-family: 'Space Grotesk', 'Inter', sans-serif;
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: 0.5em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.45);
    }
    .auth-form-header h3 {
        font-weight: 100;
        font-size: 1rem;
    }
    .auth-copyright {
        position: fixed;
        bottom: 20px;
        left: 0;
        right: 0;
        z-index: 20;
        text-align: center;
        padding: 8px 16px;
        font-size: 0.72rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.55);
        letter-spacing: 0.02em;
        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    }
    @media (max-height: 680px) {
        .auth-shell { overflow-y: auto; }
        .auth-form-panel { align-items: flex-start; }
    }
</style>
@endpush

@section('content')
<div class="auth-shell" style="flex-direction:column;">
    <div class="auth-bg"></div>
    <div class="auth-grid"></div>
    <div class="auth-noise"></div>

    {{-- Centered Login Card --}}
    <div class="auth-form-panel" style="flex:1 1 auto;width:100%;max-width:100%;padding:48px 24px;">
        <div class="auth-form-glass" style="text-align:center;">
            <div class="auth-hero-brand" style="justify-content:center;margin-bottom:28px;">
                <span class="logo-chip" style="width:58px;height:58px;border-radius:14px;">
                    <img src="{{ asset('images/logo-alkonek.gif') }}" alt="ALKONEK">
                </span>
                <div class="auth-hero-brand-text" style="text-align:left;">
                    <h2 class="auth-brand-name">ALKONEK</h2>
                    <span class="auth-brand-tag">PROVISION AND BILL SYSTEM</span>
                </div>
            </div>

            <div class="auth-form-header">
                <h3>Silahkan Login Menggunakan Akun Yang Anda Miliki</h3>
                <p>Akses Panel for ALKONEK Group</p>
            </div>

            <form method="POST" action="{{ route('login') }}" id="loginForm" style="text-align:left;">
                @csrf

                <div class="auth-field">
                    <label for="username">Username</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-user auth-input-icon"></i>
                        <input
                            type="text"
                            name="username"
                            id="username"
                            class="auth-input @error('username') is-invalid @enderror"
                            value="{{ old('username') }}"
                            placeholder="admin"
                            required
                            autofocus
                            autocomplete="username"
                        >
                    </div>
                    @error('username')
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
        </div>
    </div>

    <div class="auth-copyright">&copy; 2026 PT. Alkonek Network Access. All rights reserved.</div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    // Align "ISP Billing Platform" subtitle to the width of "ALKONEKbill"
    (function alignBrandTag() {
        var title = document.querySelector('.auth-brand-name');
        var tag = document.querySelector('.auth-brand-tag');
        if (!title || !tag) return;
        function apply() {
            var target = title.getBoundingClientRect().width;
            if (!target) return;
            tag.style.letterSpacing = '0px';
            var base = tag.getBoundingClientRect().width;
            var gaps = tag.textContent.trim().length - 1;
            if (gaps > 0 && base < target) {
                tag.style.letterSpacing = ((target - base) / gaps) + 'px';
            }
        }
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(apply);
        } else {
            window.addEventListener('load', apply);
        }
    })();

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
