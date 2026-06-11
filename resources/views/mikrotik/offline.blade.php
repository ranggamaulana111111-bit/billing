@extends('layouts.app')

@section('title', 'MikroTik Offline')

@section('content')
<div class="row justify-content-center" style="margin-top:80px;">
    <div class="col-lg-6 text-center">
        <div style="font-size:4rem;color:#94a3b8;margin-bottom:20px;">
            <i class="fa-solid fa-router"></i>
        </div>
        <h3 class="fw-bold">MikroTik Belum Dikonfigurasi</h3>
        <p class="text-muted mb-4">Konfigurasi koneksi MikroTik terlebih dahulu di menu Pengaturan.</p>
        <a href="{{ route('settings.index') }}" class="btn btn-primary px-5 py-2">
            <i class="fa-solid fa-gear me-2"></i>Pengaturan MikroTik
        </a>
    </div>
</div>
@endsection
