@extends('layouts.app')
@section('title', 'Barang Masuk')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-arrow-down me-2" style="color:#059669;"></i>Barang Masuk</h2>
        <p class="section-subtitle mb-0 mt-1">Catatan penerimaan barang inventaris</p>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
<div class="row g-4">
    {{-- Form Input --}}
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span class="fw-semibold">Input Barang Masuk</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('inventory.masuk.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih Barang <span class="text-danger">*</span></label>
                        <select name="inventory_item_id" class="form-select @error('inventory_item_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ old('inventory_item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->category_label }}) — Stok: {{ $item->stock }} {{ $item->unit_label }}
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Jumlah <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity') }}" min="1" required placeholder="0">
                            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control @error('date') is-invalid @enderror"
                                   value="{{ old('date', date('Y-m-d')) }}" required>
                            @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Keterangan</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                  placeholder="Contoh: Pengadaan Q3 2026, dari supplier...">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fa-solid fa-check me-1"></i>Catat Barang Masuk
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- Riwayat --}}
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                <span class="fw-semibold">Riwayat Barang Masuk</span>
            </div>
            <div class="card-body">
                {{-- Filter --}}
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-5">
                        <select name="item_id" class="form-select form-select-sm">
                            <option value="">Semua Barang</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" placeholder="Dari">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" placeholder="Sampai">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter"></i></button>
                    </div>
                </form>
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                            <tr>
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th class="text-center">Jumlah</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>

                        <tbody>
                            @forelse($transactions as $trx)
                                <tr>
                                    <td>{{ $trx->date->format('d/m/Y') }}</td>
                                    <td class="fw-semibold">{{ $trx->item->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-success">+{{ $trx->quantity }}</span>
                                    </td>
                                    <td class="text-muted">{{ $trx->notes ?? '-' }}</td>
                                    <td>{{ $trx->creator->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat barang masuk.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($transactions->hasPages())
                    <div class="mt-3">{{ $transactions->withQueryString()->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
