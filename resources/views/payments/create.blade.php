@extends('layouts.app')

@section('title', 'Catat Pembayaran')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-money-bill-wave me-2" style="color:#059669;"></i>Catat Pembayaran</h2>
        <p class="section-subtitle mb-0 mt-1">Invoice: {{ $invoice->invoice_code }} — {{ $invoice->customer->name }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Jumlah tagihan: <strong>Rp{{ number_format($invoice->amount, 0, ',', '.') }}</strong></span>
                </div>

                <form method="POST" action="{{ route('payments.store') }}">
                    @csrf
                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Dibayar (Rp)</label>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount', $invoice->amount) }}" min="1" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Metode Pembayaran</label>
                        <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Tunai</option>
                            <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer Bank</option>
                            <option value="qris" {{ old('payment_method') === 'qris' ? 'selected' : '' }}>QRIS</option>
                            <option value="midtrans" {{ old('payment_method') === 'midtrans' ? 'selected' : '' }}>Midtrans</option>
                        </select>
                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Pembayaran</label>
                        <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                               value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                        @error('payment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary px-4">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-success px-5">
                            <i class="fa-solid fa-check me-2"></i>Konfirmasi Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
