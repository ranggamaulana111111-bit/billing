@extends('layouts.app')

@section('title', 'Status Voucher')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        @php
                            $statusIcon = match($voucher->status) {
                                'active' => '✅',
                                'used' => '🔴',
                                'expired' => '⏰',
                                default => '❓',
                            };
                            $statusLabel = match($voucher->status) {
                                'active' => 'Aktif',
                                'used' => 'Terpakai',
                                'expired' => 'Kadaluarsa',
                                default => $voucher->status,
                            };
                            $statusClass = match($voucher->status) {
                                'active' => 'success',
                                'used' => 'danger',
                                'expired' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <span style="font-size:3rem;">{{ $statusIcon }}</span>
                        <h4 class="mt-2">Status Voucher</h4>
                        <span class="badge bg-{{ $statusClass }} fs-6">{{ $statusLabel }}</span>
                    </div>

                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted">Username</td>
                            <td class="fw-bold">{{ $voucher->username }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Password</td>
                            <td class="fw-bold">{{ $voucher->password }}</td>
                        </tr>
                        @if($voucher->profile)
                        <tr>
                            <td class="text-muted">Paket</td>
                            <td class="fw-bold">{{ $voucher->profile->name }}</td>
                        </tr>
                        @endif
                        @if($voucher->speed)
                        <tr>
                            <td class="text-muted">Speed</td>
                            <td class="fw-bold">{{ $voucher->speed }}</td>
                        </tr>
                        @endif
                        @if($voucher->quota_limit)
                        <tr>
                            <td class="text-muted">Kuota</td>
                            <td class="fw-bold">{{ number_format($voucher->quota_limit) }} MB</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td class="fw-bold">{{ $voucher->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kadaluarsa</td>
                            <td class="fw-bold">{{ $voucher->expires_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @if($voucher->used_at)
                        <tr>
                            <td class="text-muted">Terpakai</td>
                            <td class="fw-bold">{{ $voucher->used_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @endif
                        @if($voucher->downloaded > 0 || $voucher->uploaded > 0)
                        <tr>
                            <td class="text-muted">Download</td>
                            <td class="fw-bold">{{ number_format($voucher->downloaded) }} MB</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Upload</td>
                            <td class="fw-bold">{{ number_format($voucher->uploaded) }} MB</td>
                        </tr>
                        @endif
                        @if($voucher->ip_address)
                        <tr>
                            <td class="text-muted">IP Address</td>
                            <td class="fw-bold">{{ $voucher->ip_address }}</td>
                        </tr>
                        @endif
                        @if($voucher->mac_address)
                        <tr>
                            <td class="text-muted">MAC Address</td>
                            <td class="fw-bold">{{ $voucher->mac_address }}</td>
                        </tr>
                        @endif
                    </table>

                    <div class="text-center mt-3">
                        <a href="{{ route('vouchers.public.check') }}" class="btn btn-outline-primary">
                            <i class="fa-solid fa-search me-1"></i>Cek Lain
                        </a>
                        <a href="{{ route('vouchers.public.index') }}" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-cart-shopping me-1"></i>Beli Voucher
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
