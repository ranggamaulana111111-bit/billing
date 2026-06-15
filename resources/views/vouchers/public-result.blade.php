@extends('layouts.app')

@section('title', 'Voucher WiFi - ' . $company)

@push('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .voucher-card { break-inside: avoid; page-break-inside: avoid; }
    }
    .voucher-card {
        border: 1px dashed #ccc;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        background: #fff;
    }
</style>
@endpush

@section('content')
<div class="container py-4">
    <div class="no-print mb-4">
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle me-1"></i>
            <strong>Berhasil!</strong> {{ count($vouchers) }} voucher {{ $profile->name }} telah dibuat.
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Cetak Semua
            </button>
            <a href="{{ route('vouchers.public.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    @if($template && $template->content)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3"><i class="fa-solid fa-palette me-2" style="color:var(--primary);"></i>Landing Page: {{ $template->name }}</h5>
                <div class="border rounded p-3 bg-light" style="max-height:400px;overflow-y:auto;">
                    {!! $template->content !!}
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        @foreach($vouchers as $voucher)
            <div class="col-md-6 col-lg-4">
                <div class="voucher-card">
                    @php
                        $qrData = urlencode("{$voucher->username}|{$voucher->password}");
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={$qrData}";
                    @endphp
                    <div class="mb-2">
                        <img src="{{ $qrUrl }}" alt="QR" style="width:100px;height:100px;" class="img-fluid">
                    </div>
                    <h5 class="fw-bold mb-1">{{ $company }}</h5>
                    <p class="text-muted small mb-2">{{ $profile->name }}</p>
                    <hr>
                    <div class="mb-2">
                        <strong>Username:</strong>
                        <div style="font-size:1.2rem;font-weight:800;letter-spacing:1px;background:#f1f5f9;padding:4px 8px;border-radius:6px;">{{ $voucher->username }}</div>
                    </div>
                    <div class="mb-2">
                        <strong>Password:</strong>
                        <div style="font-size:1rem;font-weight:600;background:#f1f5f9;padding:4px 8px;border-radius:6px;">{{ $voucher->password }}</div>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        @if($profile->speed)<span class="d-block"><i class="fa-solid fa-gauge-high me-1"></i>Speed: {{ $profile->speed }}</span>@endif
                        @if($profile->time_limit)<span class="d-block"><i class="fa-solid fa-clock me-1"></i>Masa Aktif: {{ $profile->time_limit }} Jam</span>@endif
                        @if($profile->quota_limit)<span class="d-block"><i class="fa-solid fa-database me-1"></i>Kuota: {{ number_format($profile->quota_limit) }} MB</span>@endif
                        @if($profile->validity_days)<span class="d-block"><i class="fa-solid fa-calendar-day me-1"></i>Masa Berlaku: {{ $profile->validity_days }} Hari</span>@endif
                        <span class="d-block"><i class="fa-solid fa-users me-1"></i>Shared: {{ $profile->shared_users }} Device</span>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        <i class="fa-solid fa-clock me-1"></i>Exp: {{ $voucher->expires_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
