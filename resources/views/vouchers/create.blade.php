@extends('layouts.app')

@section('title', 'Buat Voucher WiFi')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plus me-2" style="color:var(--primary);"></i>Buat Voucher WiFi</h2>
        <p class="section-subtitle mb-0 mt-1">Generate voucher hotspot baru</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('vouchers.index') }}" class="btn btn-outline-premium px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('vouchers.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Durasi</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" name="duration" class="form-control form-control-lg" placeholder="contoh: 1" min="1" max="720" value="{{ old('duration') }}" required>
                            </div>
                            <div class="col-6">
                                <select name="duration_unit" class="form-select form-select-lg" required>
                                    <option value="hours">Jam</option>
                                    <option value="days" {{ old('duration_unit') == 'days' ? 'selected' : '' }}>Hari</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text mt-1">Maks 720 jam (30 hari).</div>
                        @error('duration')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('duration_unit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Jumlah Voucher</label>
                        <input type="number" name="count" class="form-control form-control-lg" placeholder="contoh: 10" min="1" max="100" value="{{ old('count', 1) }}" required>
                        <div class="form-text mt-1">Maks 100 voucher per generate.</div>
                        @error('count')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg py-3">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generate Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4 bg-light">
            <div class="card-body p-4">
                <h6 class="fw-semibold"><i class="fa-solid fa-circle-info me-2" style="color:var(--primary);"></i>Informasi</h6>
                <ul class="mb-0 small text-secondary" style="line-height:2;">
                    <li>Username terdiri dari 8 karakter acak <code>(contoh: A3XK9M2Q)</code></li>
                    <li>Password terdiri dari 6 karakter acak <code>(contoh: x7k2m9)</code></li>
                    <li>Voucher akan kadaluarsa otomatis sesuai durasi yang dipilih</li>
                    <li>Cetak voucher setelah dibuat untuk diberikan ke pelanggan</li>
                    @if($mikrotikConnected)
                        <li><i class="fa-solid fa-wifi text-success me-1"></i>Voucher akan otomatis di-push ke MikroTik</li>
                    @else
                        <li><i class="fa-solid fa-plug text-muted me-1"></i>Integrasi MikroTik tidak aktif. <a href="{{ route('settings.index') }}" style="color:var(--primary);">Atur di Pengaturan</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
