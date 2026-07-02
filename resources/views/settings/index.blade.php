@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gear me-2" style="color:var(--primary);"></i>Pengaturan</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi umum sistem billing</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                        <span>Perusahaan</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Perusahaan</label>
                        <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror"
                               value="{{ old('company_name', $settings['company_name'] ?? '') }}" required>
                        @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Singkat (Sidebar)</label>
                        <input type="text" name="company_short_name" class="form-control @error('company_short_name') is-invalid @enderror"
                               value="{{ old('company_short_name', $settings['company_short_name'] ?? '') }}" placeholder="ALKONEK">
                        <div class="form-text">Nama pendek yang tampil di sidebar. Kosongkan untuk menggunakan default.</div>
                        @error('company_short_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Logo Perusahaan</label>
                        <input type="file" name="company_logo" class="form-control @error('company_logo') is-invalid @enderror"
                               accept="image/jpg,image/jpeg,image/png,image/webp">
                        @error('company_logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if(!empty($settings['company_logo']))
                            <div class="mt-2 d-flex align-items-center gap-3">
                                <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo Preview"
                                     style="height:40px;width:auto;border-radius:6px;border:1px solid #dee2e6;">
                                <small class="text-muted">Logo saat ini. Unggah file baru untuk mengganti.</small>
                            </div>
                        @else
                            <div class="form-text">Kosongkan jika tetap menggunakan logo default. Format: JPG, PNG, WEBP. Maks 2MB.</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="company_address" class="form-control @error('company_address') is-invalid @enderror"
                                  rows="2">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                        @error('company_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Telepon</label>
                        <input type="text" name="company_phone" class="form-control @error('company_phone') is-invalid @enderror"
                               value="{{ old('company_phone', $settings['company_phone'] ?? '') }}">
                        @error('company_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                        <span>Pembayaran</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Bank</label>
                        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                               value="{{ old('bank_name', $settings['bank_name'] ?? '') }}">
                        @error('bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Rekening</label>
                        <input type="text" name="bank_account" class="form-control @error('bank_account') is-invalid @enderror"
                               value="{{ old('bank_account', $settings['bank_account'] ?? '') }}">
                        @error('bank_account') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Atas Nama</label>
                        <input type="text" name="bank_holder" class="form-control @error('bank_holder') is-invalid @enderror"
                               value="{{ old('bank_holder', $settings['bank_holder'] ?? '') }}">
                        @error('bank_holder') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#64748b;"></div>
                        <span>Invoice</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Footer Faktur</label>
                        <textarea name="invoice_footer" class="form-control @error('invoice_footer') is-invalid @enderror"
                                  rows="2">{{ old('invoice_footer', $settings['invoice_footer'] ?? '') }}</textarea>
                        @error('invoice_footer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- WA NOTIFICATION --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#0ea5e9;"></div>
                        <span>Notifikasi WhatsApp</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fonnte Token</label>
                        <input type="password" name="fonnte_token" class="form-control @error('fonnte_token') is-invalid @enderror"
                               value="{{ old('fonnte_token', $settings['fonnte_token'] ?? '') }}" placeholder="Token API dari fonnte.com">
                        <div class="form-text">Diperlukan untuk kirim notifikasi WA otomatis. Daftar di <code>fonnte.com</code></div>
                        @error('fonnte_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#8b5cf6;"></div>
                        <span>Payment Gateway</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Midtrans Server Key</label>
                        <input type="password" name="midtrans_server_key" class="form-control @error('midtrans_server_key') is-invalid @enderror"
                               value="{{ old('midtrans_server_key', $settings['midtrans_server_key'] ?? '') }}" placeholder="Server Key dari Midtrans">
                        @error('midtrans_server_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Midtrans Client Key</label>
                        <input type="text" name="midtrans_client_key" class="form-control @error('midtrans_client_key') is-invalid @enderror"
                               value="{{ old('midtrans_client_key', $settings['midtrans_client_key'] ?? '') }}" placeholder="Client Key dari Midtrans">
                        @error('midtrans_client_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input type="hidden" name="midtrans_is_production" value="0">
                            <input type="checkbox" name="midtrans_is_production" value="1" class="form-check-input" id="midtransProduction"
                                   {{ old('midtrans_is_production', $settings['midtrans_is_production'] ?? '0') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="midtransProduction">Mode Production</label>
                        </div>
                        <div class="form-text">Aktifkan jika menggunakan akun Midtrans production (non-sandbox).</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- VOUCHER DEFAULTS --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#e11d48;"></div>
                        <span>Default Voucher Hotspot</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Panjang Username</label>
                            <input type="number" name="voucher_username_length" class="form-control @error('voucher_username_length') is-invalid @enderror"
                                   value="{{ old('voucher_username_length', $settings['voucher_username_length'] ?? '8') }}" min="4" max="20">
                            @error('voucher_username_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Panjang Password</label>
                            <input type="number" name="voucher_password_length" class="form-control @error('voucher_password_length') is-invalid @enderror"
                                   value="{{ old('voucher_password_length', $settings['voucher_password_length'] ?? '6') }}" min="4" max="20">
                            @error('voucher_password_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Server Hotspot</label>
                            <input type="text" name="mikrotik_hotspot_server" class="form-control @error('mikrotik_hotspot_server') is-invalid @enderror"
                                   value="{{ old('mikrotik_hotspot_server', $settings['mikrotik_hotspot_server'] ?? 'hotspot1') }}" placeholder="hotspot1">
                            <div class="form-text">Nama server hotspot di MikroTik (biasanya <code>hotspot1</code>).</div>
                            @error('mikrotik_hotspot_server') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LATE FEE & DUE DATE --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#dc2626;"></div>
                        <span>Denda & Jatuh Tempo</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Denda Keterlambatan (Rp)</label>
                            <input type="number" name="late_fee_amount" class="form-control @error('late_fee_amount') is-invalid @enderror"
                                   value="{{ old('late_fee_amount', $settings['late_fee_amount'] ?? '0') }}" min="0">
                            @error('late_fee_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Masa Tenggang (hari)</label>
                            <input type="number" name="late_fee_grace_days" class="form-control @error('late_fee_grace_days') is-invalid @enderror"
                                   value="{{ old('late_fee_grace_days', $settings['late_fee_grace_days'] ?? '0') }}" min="0">
                            <div class="form-text">Jumlah hari setelah jatuh tempo sebelum denda berlaku.</div>
                            @error('late_fee_grace_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Tanggal Jatuh Tempo Default</label>
                            <input type="number" name="default_due_date" class="form-control @error('default_due_date') is-invalid @enderror"
                                   value="{{ old('default_due_date', $settings['default_due_date'] ?? '5') }}" min="1" max="28">
                            <div class="form-text">Tanggal jatuh tempo default untuk pelanggan baru (1-28).</div>
                            @error('default_due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MIKROTIK --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;#d97706;"></div>
                        <span>Integrasi MikroTik</span>
                    </div>
                    <a href="{{ route('settings.test-mikrotik') }}" class="btn btn-sm btn-outline-premium" onclick="return confirm('Test koneksi ke MikroTik?')">
                        <i class="fa-solid fa-plug me-1"></i>Test Koneksi
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Host / IP</label>
                            <input type="text" name="mikrotik_host" class="form-control @error('mikrotik_host') is-invalid @enderror"
                                   value="{{ old('mikrotik_host', $settings['mikrotik_host'] ?? '') }}" placeholder="contoh: 192.168.1.1">
                            @error('mikrotik_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                             <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="mikrotik_port" class="form-control @error('mikrotik_port') is-invalid @enderror"
                                   value="{{ old('mikrotik_port', $settings['mikrotik_port'] ?? '80') }}" placeholder="80">
                            @error('mikrotik_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="mikrotik_user" class="form-control @error('mikrotik_user') is-invalid @enderror"
                                   value="{{ old('mikrotik_user', $settings['mikrotik_user'] ?? '') }}" placeholder="admin">
                            @error('mikrotik_user') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="mikrotik_password" class="form-control @error('mikrotik_password') is-invalid @enderror"
                                   value="{{ old('mikrotik_password', $settings['mikrotik_password'] ?? '') }}">
                            @error('mikrotik_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <p class="text-muted small mb-0">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                Setting ini sebagai default/fallback. Untuk multi-router, gunakan menu <a href="{{ route('mikrotik-routers.index') }}" class="text-primary">Kelola Router</a>.
                                Gunakan REST API MikroTik (RouterOS v7+). Pastikan REST API sudah diaktifkan di menu <code>IP → Services</code>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary px-5 py-2">
                <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Pengaturan
            </button>
        </div>
    </div>
</form>
@endsection
