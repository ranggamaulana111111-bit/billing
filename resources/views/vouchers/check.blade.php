@extends('layouts.app')

@section('title', 'Cek Voucher WiFi')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <span style="font-size:3rem;">🔍</span>
                        <h4 class="mt-2">Cek Status Voucher</h4>
                        <p class="text-muted">Masukkan username dan password voucher</p>
                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('vouchers.check-status') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control form-control-lg" placeholder="Masukkan username voucher" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" placeholder="Masukkan password voucher" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fa-solid fa-search me-1"></i>Cek Status
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('vouchers.public.index') }}" class="text-muted">
                            <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke pembelian
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
