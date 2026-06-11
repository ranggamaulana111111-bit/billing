@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand text-center mb-4">
            <span class="brand-icon mx-auto mb-3"><i class="fa-solid fa-user-plus"></i></span>
            <h4 class="fw-bold mb-1">Buat Akun Admin</h4>
            <p class="text-muted small mb-0">Daftarkan operator yang akan mengelola sistem billing.</p>
        </div>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label small fw-semibold">Nama</label>
                <input type="text" name="name" id="name" class="form-control form-control-lg @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Nama lengkap" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold">Email</label>
                <input type="email" name="email" id="email" class="form-control form-control-lg @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="admin@rabegnet.id" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">Kata Sandi</label>
                <input type="password" name="password" id="password" class="form-control form-control-lg @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password-confirm" class="form-label small fw-semibold">Konfirmasi Kata Sandi</label>
                <input type="password" name="password_confirmation" id="password-confirm" class="form-control form-control-lg" placeholder="Ulangi kata sandi" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">Daftar</button>
        </form>

        <p class="text-center mt-4 mb-0 small">
            Sudah punya akun?
            <a href="{{ route('login') }}">Masuk</a>
        </p>
    </div>
</div>
@endsection
