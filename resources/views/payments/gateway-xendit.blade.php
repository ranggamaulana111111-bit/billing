@extends('layouts.app')

@section('title', 'Payment Gateway Xendit')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-credit-card me-2" style="color:var(--primary);"></i>Payment Gateway Xendit</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi Xendit Payment Gateway untuk pembayaran online</p>
    </div>
    <a href="{{ route('payment-gateway.index') }}" class="btn btn-outline-secondary px-3">
        <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke Midtrans
    </a>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Konfigurasi Xendit</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('xendit.gateway.update') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status Gateway</label>
                            <select name="xendit_enabled" class="form-select @error('xendit_enabled') is-invalid @enderror">
                                <option value="true" {{ $xendit_enabled === 'true' ? 'selected' : '' }}>Aktif</option>
                                <option value="false" {{ $xendit_enabled === 'false' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mode</label>
                            <select name="xendit_is_production" class="form-select @error('xendit_is_production') is-invalid @enderror">
                                <option value="1" {{ $xendit_is_production === '1' ? 'selected' : '' }}>Production (Live)</option>
                                <option value="0" {{ $xendit_is_production === '0' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Secret Key (API Key)</label>
                            <input type="password" name="xendit_secret_key" class="form-control @error('xendit_secret_key') is-invalid @enderror"
                                   value="{{ old('xendit_secret_key', $xendit_secret_key) }}" placeholder="xnd_development_xxxx / xnd_production_xxxx">
                            @error('xendit_secret_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Webhook Callback Token</label>
                            <input type="password" name="xendit_webhook_token" class="form-control @error('xendit_webhook_token') is-invalid @enderror"
                                   value="{{ old('xendit_webhook_token', $xendit_webhook_token) }}" placeholder="Callback token dari dashboard Xendit">
                            @error('xendit_webhook_token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Konfigurasi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span>Informasi</span>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Status Saat Ini</small>
                    @if($xendit_enabled === 'true')
                        <span class="badge" style="background:#f0fdf4;color:#059669;"><i class="fa-solid fa-circle-check me-1"></i>Aktif</span>
                    @else
                        <span class="badge" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-circle-xmark me-1"></i>Nonaktif</span>
                    @endif
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Mode</small>
                    @if($xendit_is_production === '1')
                        <span class="badge" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-server me-1"></i>Production</span>
                    @else
                        <span class="badge" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-flask me-1"></i>Sandbox</span>
                    @endif
                </div>
                <hr>
                <p style="font-size:0.82rem;color:#64748b;">
                    Pelanggan dapat membayar invoice melalui Xendit (Virtual Account, QRIS, e-wallet, retail outlet).
                    Pelanggan dialihkan ke halaman checkout Xendit dan webhook otomatis memproses pembayaran yang berhasil.
                </p>
                <p style="font-size:0.82rem;color:#64748b;">
                    <strong>Webhook URL:</strong> Set webhook ke
                    <code>{{ route('xendit.notification') }}</code> di dashboard Xendit (Settings → Webhooks → Invoices).
                </p>
                <p style="font-size:0.82rem;color:#64748b;">
                    <strong>Sandbox:</strong> Gunakan API key testing (prefix <code>xnd_development_</code>).<br>
                    <strong>Production:</strong> Gunakan API key asli (prefix <code>xnd_production_</code>).
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
