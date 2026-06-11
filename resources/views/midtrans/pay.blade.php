@extends('layouts.app')

@section('title', 'Pembayaran Midtrans')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:var(--primary);border-radius:16px;">
                            <i class="fa-solid fa-credit-card" style="color:#fff;font-size:1.6rem;"></i>
                        </div>
                        <h4 class="fw-bold">Pembayaran Midtrans</h4>
                        <p class="text-muted small">Invoice: <strong>{{ $invoice->invoice_code }}</strong></p>
                        <p class="fw-bold" style="font-size:1.3rem;">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</p>
                    </div>

                    <div id="midtrans-payment">
                        <p class="text-muted mb-3">Mengarahkan ke halaman pembayaran...</p>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        <i class="fa-solid fa-lock me-1"></i>Pembayaran aman melalui Midtrans
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $isProduction = \App\Models\Setting::get('midtrans_is_production') === '1';
    $snapUrl = $isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
@endphp

<script src="{{ $snapUrl }}" data-client-key="{{ \App\Models\Setting::get('midtrans_client_key') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        snap.pay('{{ $snapToken }}', {
            onSuccess: function(result) {
                window.location.href = '{{ route("midtrans.finish", ["order_id" => $invoice->midtrans_order_id]) }}';
            },
            onPending: function(result) {
                window.location.href = '{{ route("midtrans.finish", ["order_id" => $invoice->midtrans_order_id]) }}';
            },
            onError: function(result) {
                alert('Pembayaran gagal atau dibatalkan.');
                window.location.href = '{{ route("invoices.index") }}';
            },
            onClose: function() {
                window.location.href = '{{ route("invoices.index") }}';
            }
        });
    });
</script>
@endpush
