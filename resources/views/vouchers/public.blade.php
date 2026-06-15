@extends('layouts.app')

@section('title', 'Beli Voucher WiFi')

@section('content')
<div class="container py-5">
    <div class="text-center mb-5">
        <h2><i class="fa-solid fa-wifi me-2"></i>Beli Voucher WiFi</h2>
        <p class="text-muted">Pilih paket dan dapatkan voucher WiFi instan</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4 justify-content-center">
        @foreach($profiles as $profile)
            <div class="col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0" style="border-radius:16px;">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span style="font-size:2.5rem;">🌐</span>
                        </div>
                        <h5 class="fw-bold">{{ $profile->name }}</h5>
                        @if($profile->speed)
                            <p class="mb-1"><i class="fa-solid fa-gauge-high me-1"></i>{{ $profile->speed }}</p>
                        @endif
                        @if($profile->quota_limit)
                            <p class="mb-1"><i class="fa-solid fa-database me-1"></i>{{ number_format($profile->quota_limit) }} MB</p>
                        @endif
                        @if($profile->validity_days)
                            <p class="mb-1"><i class="fa-solid fa-calendar-day me-1"></i>{{ $profile->validity_days }} Hari</p>
                        @endif
                        @if($profile->time_limit)
                            <p class="mb-1"><i class="fa-solid fa-clock me-1"></i>{{ $profile->time_limit }} Jam</p>
                        @endif
                        <p class="mb-1"><i class="fa-solid fa-users me-1"></i>{{ $profile->shared_users }} Device</p>
                        <h4 class="fw-bold mt-3" style="color:var(--primary);">Rp {{ number_format($profile->price, 0, ',', '.') }}</h4>
                    </div>
                    <div class="card-footer bg-white border-0 pb-4 pt-0 text-center">
                        <button type="button" class="btn btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#orderModal{{ $profile->id }}">
                            <i class="fa-solid fa-cart-shopping me-1"></i>Beli
                        </button>
                    </div>
                </div>
            </div>

            {{-- Order Modal --}}
            <div class="modal fade" id="orderModal{{ $profile->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('vouchers.public.generate') }}">
                            @csrf
                            <input type="hidden" name="profile_id" value="{{ $profile->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Beli {{ $profile->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Harga</label>
                                    <p class="fs-5 fw-bold" style="color:var(--primary);">Rp {{ number_format($profile->price, 0, ',', '.') }}</p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Jumlah Voucher</label>
                                    <input type="number" name="count" class="form-control" value="1" min="1" max="50" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Prefix Username (opsional)</label>
                                    <input type="text" name="prefix" class="form-control" placeholder="Contoh: WiFi" maxlength="10">
                                    <small class="text-muted">Huruf/angka tanpa spasi. Contoh: WiFi → WiFiA1B2C3</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Template Landing Page (opsional)</label>
                                    <select name="template_id" class="form-select">
                                        <option value="">Tanpa Template</option>
                                        @foreach($templates as $tpl)
                                            <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Tampilan halaman login WiFi setelah terhubung</small>
                                </div>
                                <div class="alert alert-info mb-0">
                                    <i class="fa-solid fa-circle-info me-1"></i>Voucher akan langsung muncul setelah pembelian.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary px-4">Beli Sekarang</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-center mt-4">
        <a href="{{ route('vouchers.public.check') }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-search me-1"></i>Cek Status Voucher
        </a>
    </div>
</div>
@endsection
