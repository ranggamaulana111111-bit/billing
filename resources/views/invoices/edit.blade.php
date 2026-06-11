@extends('layouts.app')

@section('title', 'Edit Tagihan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit Tagihan</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $invoice->invoice_code }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('invoice.update', $invoice->id) }}">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pelanggan</label>
                        <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ old('customer_id', $invoice->customer_id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} — {{ $c->package->name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Tagihan (Rp)</label>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount', $invoice->amount) }}" min="1" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary px-4">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
