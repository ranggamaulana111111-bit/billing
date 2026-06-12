@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand text-center mb-4">
            <span class="brand-icon mx-auto mb-3"><i class="fa-solid fa-bolt"></i></span>
            <h4 class="fw-bold mb-1">Masuk ke RabegNet</h4>
            <p class="text-muted small mb-0">Akses panel billing, pelanggan, voucher, dan monitoring.</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold">Email</label>
                <input type="email" name="email" id="email" class="form-control form-control-lg @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="admin@rabegnet.id" required autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">Kata Sandi</label>
                <input type="password" name="password" id="password" class="form-control form-control-lg @error('password') is-invalid @enderror" placeholder="Masukkan kata sandi" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" class="form-check-label small">Ingat saya</label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">Masuk</button>
        </form>

        <div class="hr-text my-3">atau</div>

        <div class="d-grid gap-2">
            <a href="{{ route('auth.redirect', 'google') }}" class="btn btn-outline-danger btn-lg fw-semibold d-flex align-items-center justify-content-center gap-2">
                <i class="fa-brands fa-google"></i> Masuk dengan Google
            </a>
            <a href="{{ route('auth.redirect', 'github') }}" class="btn btn-outline-dark btn-lg fw-semibold d-flex align-items-center justify-content-center gap-2">
                <i class="fa-brands fa-github"></i> Masuk dengan GitHub
            </a>
        </div>

        <p class="text-center mt-4 mb-0 small">
            Belum punya akun?
            <a href="{{ route('register') }}">Daftar</a>
        </p>
    </div>
</div>
@endsection
