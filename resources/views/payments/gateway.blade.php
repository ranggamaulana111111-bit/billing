@extends('layouts.app')

@section('title', 'Payment Gateway')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-credit-card me-2" style="color:var(--primary);"></i>Payment Gateway</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi Midtrans Payment Gateway untuk pembayaran online</p>
    </div>
    <a href="{{ route('xendit.gateway') }}" class="btn btn-outline-primary px-3">
        <i class="fa-solid fa-wallet me-1"></i>Konfigurasi Xendit
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
                    <span>Konfigurasi Midtrans</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('payment-gateway.update') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status Gateway</label>
                            <select name="midtrans_enabled" class="form-select @error('midtrans_enabled') is-invalid @enderror">
                                <option value="true" {{ $midtrans_enabled === 'true' ? 'selected' : '' }}>Aktif</option>
                                <option value="false" {{ $midtrans_enabled === 'false' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mode</label>
                            <select name="midtrans_production" class="form-select @error('midtrans_production') is-invalid @enderror">
                                <option value="false" {{ $midtrans_production === 'false' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                <option value="true" {{ $midtrans_production === 'true' ? 'selected' : '' }}>Production (Live)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Merchant ID</label>
                            <input type="text" name="midtrans_merchant_id" class="form-control @error('midtrans_merchant_id') is-invalid @enderror"
                                   value="{{ old('midtrans_merchant_id', $midtrans_merchant_id) }}" placeholder="G0000000000000">
                            @error('midtrans_merchant_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Server Key</label>
                            <input type="password" name="midtrans_server_key" class="form-control @error('midtrans_server_key') is-invalid @enderror"
                                   value="{{ old('midtrans_server_key', $midtrans_server_key) }}" placeholder="SB-Mid-server-XXXXXX">
                            @error('midtrans_server_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Client Key</label>
                            <input type="password" name="midtrans_client_key" class="form-control @error('midtrans_client_key') is-invalid @enderror"
                                   value="{{ old('midtrans_client_key', $midtrans_client_key) }}" placeholder="SB-Mid-client-XXXXXX">
                            @error('midtrans_client_key')
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
                    @if($midtrans_enabled === 'true')
                        <span class="badge" style="background:#f0fdf4;color:#059669;"><i class="fa-solid fa-circle-check me-1"></i>Aktif</span>
                    @else
                        <span class="badge" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-circle-xmark me-1"></i>Nonaktif</span>
                    @endif
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Mode</small>
                    @if($midtrans_production === 'true')
                        <span class="badge" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-server me-1"></i>Production</span>
                    @else
                        <span class="badge" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-flask me-1"></i>Sandbox</span>
                    @endif
                </div>
                <hr>
                <p style="font-size:0.82rem;color:#64748b;">
                    Pelanggan dapat membayar invoice melalui Midtrans (VA, QRIS, kartu kredit, e-wallet).
                    Webhook otomatis memproses pembayaran yang berhasil.
                </p>
                <p style="font-size:0.82rem;color:#64748b;">
                    <strong>Sandbox:</strong> Gunakan kredensial testing dari Midtrans.<br>
                    <strong>Production:</strong> Gunakan kredensial asli dari dashboard Midtrans.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
