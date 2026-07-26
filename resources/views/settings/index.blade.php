@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gear me-2" style="color:var(--primary);"></i>Pengaturan</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi umum sistem billing — atur profil perusahaan, pembayaran, integrasi & lainnya</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" id="settingsForm">
    @csrf

    <div class="settings-layout">

        {{-- VERTICAL TABS --}}
        <div class="settings-tabs" id="settingsTabs">
            <button type="button" class="settings-tab active" data-tab="tab-company">
                <i class="fa-solid fa-building"></i>
                <span>Perusahaan</span>
            </button>
            <button type="button" class="settings-tab" data-tab="tab-integration">
                <i class="fa-solid fa-code-branch"></i>
                <span>Integrasi & Payment</span>
            </button>
            <button type="button" class="settings-tab" data-tab="tab-finance">
                <i class="fa-solid fa-coins"></i>
                <span>Keuangan & Tagihan</span>
            </button>
            <button type="button" class="settings-tab" data-tab="tab-notification">
                <i class="fa-solid fa-bell"></i>
                <span>Notifikasi & Voucher</span>
            </button>
        </div>

        {{-- TAB CONTENT --}}
        <div class="settings-panels">

            {{-- TAB 1: PERUSAHAAN --}}
            <div class="settings-panel active" id="tab-company">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card settings-card stagger-card" data-accent="#2563eb">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:rgba(255,255,255,.22);">
                                        <i class="fa-solid fa-building"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 text-white">Profil Perusahaan</h5>
                                        <small class="text-white-50">Informasi identitas perusahaan Anda</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Nama Perusahaan</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                                            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror"
                                                   value="{{ old('company_name', $settings['company_name'] ?? '') }}" required placeholder="PT. Contoh">
                                            @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nama Singkat (Sidebar)</label>
                                        <input type="text" name="company_short_name" class="form-control @error('company_short_name') is-invalid @enderror"
                                               value="{{ old('company_short_name', $settings['company_short_name'] ?? '') }}" placeholder="ALKONEK">
                                        <div class="form-text">Nama pendek yang tampil di sidebar. Kosongkan untuk menggunakan default.</div>
                                        @error('company_short_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telepon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                            <input type="text" name="company_phone" class="form-control @error('company_phone') is-invalid @enderror"
                                                   value="{{ old('company_phone', $settings['company_phone'] ?? '') }}" placeholder="021-12345678">
                                            @error('company_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Logo Perusahaan</label>
                                        <div class="settings-dropzone" onclick="document.getElementById('logoInput').click()">
                                            <input type="file" id="logoInput" name="company_logo" class="d-none"
                                                   accept="image/jpg,image/jpeg,image/png,image/webp,image/svg+xml,image/gif,.svg,.gif" onchange="previewLogo(this)">
                                            @error('company_logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            @if(!empty($settings['company_logo']))
                                                <div class="settings-dropzone-preview">
                                                    <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo">
                                                    <div class="settings-dropzone-overlay"><i class="fa-solid fa-camera"></i><span>Ganti</span></div>
                                                </div>
                                            @else
                                                <div class="settings-dropzone-content">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                                    <span>Klik untuk unggah logo</span>
                                                    <small>PNG, JPG, WEBP — Maks 2MB</small>
                                                </div>
                                            @endif
                                        </div>
                                        <div id="logoPreview" class="d-none mt-2"></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Alamat</label>
                                        <textarea name="company_address" class="form-control form-control--textarea @error('company_address') is-invalid @enderror"
                                                  rows="3" placeholder="Jl. Contoh No. 123">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                                        @error('company_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 2: INTEGRASI & PAYMENT --}}
            <div class="settings-panel" id="tab-integration">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#8b5cf6">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:rgba(255,255,255,.22);">
                                        <i class="fa-solid fa-circle-dollar"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 text-white">Payment Gateway</h5>
                                        <small class="text-white-50">Integrasi Midtrans untuk pembayaran online</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Midtrans Server Key</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="midtrans_server_key" class="form-control @error('midtrans_server_key') is-invalid @enderror"
                                               value="{{ old('midtrans_server_key', $settings['midtrans_server_key'] ?? '') }}" placeholder="Server Key dari Midtrans" id="midtransServerKey">
                                        <button type="button" class="settings-eye-btn" onclick="togglePassword('midtransServerKey', this)"><i class="fa-regular fa-eye"></i></button>
                                        @error('midtrans_server_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Midtrans Client Key</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock-open"></i></span>
                                        <input type="text" name="midtrans_client_key" class="form-control @error('midtrans_client_key') is-invalid @enderror"
                                               value="{{ old('midtrans_client_key', $settings['midtrans_client_key'] ?? '') }}" placeholder="Client Key dari Midtrans">
                                        @error('midtrans_client_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <div class="settings-toggle-area">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="midtrans_is_production" value="0">
                                                <input type="checkbox" name="midtrans_is_production" value="1" class="form-check-input" id="midtransProduction"
                                                       {{ old('midtrans_is_production', $settings['midtrans_is_production'] ?? '0') === '1' ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="midtransProduction">Mode Production</label>
                                            </div>
                                            <span class="settings-mode-badge" id="modeBadge" style="background:{{ old('midtrans_is_production', $settings['midtrans_is_production'] ?? '0') === '1' ? '#fef3c7' : '#f0fdf4' }};color:{{ old('midtrans_is_production', $settings['midtrans_is_production'] ?? '0') === '1' ? '#d97706' : '#059669' }};">
                                                <span class="settings-mode-dot"></span>
                                                {{ old('midtrans_is_production', $settings['midtrans_is_production'] ?? '0') === '1' ? 'Production' : 'Sandbox' }}
                                            </span>
                                        </div>
                                        <div class="form-text mt-2">Aktifkan jika menggunakan akun Midtrans production (non-sandbox).</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#d97706">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
                                        <i class="fa-solid fa-router"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Integrasi MikroTik</h5>
                                        <small class="text-muted">Koneksi REST API untuk manajemen router</small>
                                    </div>
                                </div>
                                <a href="{{ route('settings.test-mikrotik') }}" class="btn btn-sm btn-gradient-orange px-3" onclick="return confirm('Test koneksi ke MikroTik?')">
                                    <i class="fa-solid fa-plug me-1"></i>Test
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Host / IP Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-globe"></i></span>
                                            <input type="text" name="mikrotik_host" class="form-control @error('mikrotik_host') is-invalid @enderror"
                                                   value="{{ old('mikrotik_host', $settings['mikrotik_host'] ?? '') }}" placeholder="192.168.1.1">
                                            @error('mikrotik_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Port</label>
                                        <input type="number" name="mikrotik_port" class="form-control @error('mikrotik_port') is-invalid @enderror"
                                               value="{{ old('mikrotik_port', $settings['mikrotik_port'] ?? '80') }}" placeholder="80">
                                        @error('mikrotik_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                            <input type="text" name="mikrotik_user" class="form-control @error('mikrotik_user') is-invalid @enderror"
                                                   value="{{ old('mikrotik_user', $settings['mikrotik_user'] ?? '') }}" placeholder="admin">
                                            @error('mikrotik_user') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                            <input type="password" name="mikrotik_password" class="form-control @error('mikrotik_password') is-invalid @enderror"
                                                   value="{{ old('mikrotik_password', $settings['mikrotik_password'] ?? '') }}" id="mikrotikPassword">
                                            <button type="button" class="settings-eye-btn" onclick="togglePassword('mikrotikPassword', this)"><i class="fa-regular fa-eye"></i></button>
                                            @error('mikrotik_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="settings-info-note">
                                            <i class="fa-solid fa-info-circle"></i>
                                            <span>Setting ini sebagai default/fallback. Untuk multi-router, gunakan <a href="{{ route('mikrotik-routers.index') }}">Kelola Router</a>. REST API RouterOS v7+.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 3: KEUANGAN & TAGIHAN --}}
            <div class="settings-panel" id="tab-finance">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#059669">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#059669,#10b981);">
                                        <i class="fa-solid fa-credit-card"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Rekening Bank</h5>
                                        <small class="text-muted">Informasi rekening untuk pembayaran tagihan</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama Bank</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-university"></i></span>
                                        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                                               value="{{ old('bank_name', $settings['bank_name'] ?? '') }}" placeholder="Bank Mandiri">
                                        @error('bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nomor Rekening</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                        <input type="text" name="bank_account" class="form-control @error('bank_account') is-invalid @enderror"
                                               value="{{ old('bank_account', $settings['bank_account'] ?? '') }}" placeholder="123-00-4567890">
                                        @error('bank_account') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Atas Nama</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                        <input type="text" name="bank_holder" class="form-control @error('bank_holder') is-invalid @enderror"
                                               value="{{ old('bank_holder', $settings['bank_holder'] ?? '') }}" placeholder="PT Alkonek Network Access">
                                        @error('bank_holder') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#dc2626">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#dc2626,#f87171);">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Denda & Jatuh Tempo</h5>
                                        <small class="text-muted">Aturan denda keterlambatan & tanggal jatuh tempo</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Denda Keterlambatan (Rp)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-money-bill-wave"></i></span>
                                            <input type="number" name="late_fee_amount" class="form-control @error('late_fee_amount') is-invalid @enderror"
                                                   value="{{ old('late_fee_amount', $settings['late_fee_amount'] ?? '0') }}" min="0">
                                            @error('late_fee_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Masa Tenggang (hari)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                                            <input type="number" name="late_fee_grace_days" class="form-control @error('late_fee_grace_days') is-invalid @enderror"
                                                   value="{{ old('late_fee_grace_days', $settings['late_fee_grace_days'] ?? '0') }}" min="0">
                                            @error('late_fee_grace_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="form-text">Jumlah hari setelah jatuh tempo sebelum denda berlaku.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Tanggal Jatuh Tempo Default</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-regular fa-calendar-check"></i></span>
                                            <input type="number" name="default_due_date" class="form-control @error('default_due_date') is-invalid @enderror"
                                                   value="{{ old('default_due_date', $settings['default_due_date'] ?? '5') }}" min="1" max="28">
                                            @error('default_due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="form-text">Tanggal jatuh tempo default untuk pelanggan baru (1-28).</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card settings-card stagger-card" data-accent="#64748b">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#64748b,#94a3b8);">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Invoice</h5>
                                        <small class="text-muted">Pengaturan tampilan faktur tagihan</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Footer Faktur</label>
                                <textarea name="invoice_footer" class="form-control form-control--textarea @error('invoice_footer') is-invalid @enderror"
                                          rows="3" placeholder="Terima kasih telah menggunakan layanan kami">{{ old('invoice_footer', $settings['invoice_footer'] ?? '') }}</textarea>
                                <div class="form-text">Teks yang muncul di bagian bawah setiap faktur.</div>
                                @error('invoice_footer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 4: NOTIFIKASI & VOUCHER --}}
            <div class="settings-panel" id="tab-notification">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#0ea5e9">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#0ea5e9,#06b6d4);">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Notifikasi WhatsApp</h5>
                                        <small class="text-muted">Gateway WA untuk pengiriman notifikasi otomatis</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Fonnte Token</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                    <input type="password" name="fonnte_token" class="form-control @error('fonnte_token') is-invalid @enderror"
                                           value="{{ old('fonnte_token', $settings['fonnte_token'] ?? '') }}" placeholder="Token API dari fonnte.com" id="fonnteToken">
                                    <button type="button" class="settings-eye-btn" onclick="togglePassword('fonnteToken', this)"><i class="fa-regular fa-eye"></i></button>
                                    @error('fonnte_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">Diperlukan untuk kirim notifikasi WA otomatis. Daftar di <code>fonnte.com</code></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card settings-card h-100 stagger-card" data-accent="#e11d48">
                            <div class="card-header mon-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="settings-icon-badge" style="background:linear-gradient(135deg,#e11d48,#fb7185);">
                                        <i class="fa-solid fa-ticket"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Default Voucher Hotspot</h5>
                                        <small class="text-muted">Konfigurasi default untuk pembuatan voucher baru</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Panjang Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                            <input type="number" name="voucher_username_length" class="form-control @error('voucher_username_length') is-invalid @enderror"
                                                   value="{{ old('voucher_username_length', $settings['voucher_username_length'] ?? '8') }}" min="4" max="20">
                                            @error('voucher_username_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Panjang Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                            <input type="number" name="voucher_password_length" class="form-control @error('voucher_password_length') is-invalid @enderror"
                                                   value="{{ old('voucher_password_length', $settings['voucher_password_length'] ?? '6') }}" min="4" max="20">
                                            @error('voucher_password_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Nama Server Hotspot</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-wifi"></i></span>
                                            <input type="text" name="mikrotik_hotspot_server" class="form-control @error('mikrotik_hotspot_server') is-invalid @enderror"
                                                   value="{{ old('mikrotik_hotspot_server', $settings['mikrotik_hotspot_server'] ?? 'hotspot1') }}" placeholder="hotspot1">
                                            @error('mikrotik_hotspot_server') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="form-text">Nama server hotspot di MikroTik (biasanya <code>hotspot1</code>).</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



        </div>
    </div>

    {{-- STICKY SAVE BAR --}}
    <div class="settings-sticky-bar">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="settings-sticky-icon"><i class="fa-regular fa-circle-check"></i></span>
                <span class="text-muted">Pastikan semua pengaturan sudah sesuai sebelum menyimpan</span>
            </div>
            <button type="submit" class="btn btn-primary px-5 py-2">
                <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Pengaturan
            </button>
        </div>
    </div>

</form>
@endsection

@push('scripts')
<script>
function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    const icon = btn.querySelector('i');
    icon.className = type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
}

function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const dropzone = input.closest('.settings-dropzone');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.className = 'mt-2';
            preview.innerHTML = '<div class="settings-dropzone-preview" style="width:fit-content;"><img src="' + e.target.result + '" alt="Preview"><div class="settings-dropzone-overlay" style="opacity:1;background:rgba(0,0,0,0.4);"><i class="fa-solid fa-check"></i><span>Siap diupload</span></div></div>';
            if (dropzone) {
                const content = dropzone.querySelector('.settings-dropzone-content');
                if (content) content.style.display = 'none';
                const existingPrev = dropzone.querySelector('.settings-dropzone-preview');
                if (existingPrev) existingPrev.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

(function() {
    const tabs = document.querySelectorAll('.settings-tab');
    const panels = document.querySelectorAll('.settings-panel');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            panels.forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        });
    });

    // transfer data-accent to CSS custom property
    document.querySelectorAll('.settings-card[data-accent]').forEach(function(card) {
        card.style.setProperty('--card-accent', card.getAttribute('data-accent'));
    });
})();

(function() {
    const productionCheck = document.getElementById('midtransProduction');
    const modeBadge = document.getElementById('modeBadge');
    if (productionCheck && modeBadge) {
        productionCheck.addEventListener('change', function() {
            if (this.checked) {
                modeBadge.style.background = '#fef3c7';
                modeBadge.style.color = '#d97706';
                modeBadge.innerHTML = '<span class="settings-mode-dot"></span> Production';
            } else {
                modeBadge.style.background = '#f0fdf4';
                modeBadge.style.color = '#059669';
                modeBadge.innerHTML = '<span class="settings-mode-dot"></span> Sandbox';
            }
        });
    }
})();
</script>
@endpush
