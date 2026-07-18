@extends('layouts.app')
@section('title', 'Barang Keluar')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-arrow-up me-2" style="color:#dc2626;"></i>Barang Keluar</h2>
        <p class="section-subtitle mb-0 mt-1">Catatan pengeluaran barang inventaris</p>
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
                    <div style="width:8px;height:8px;border-radius:50%;background:#dc2626;"></div>
                    <span class="fw-semibold">Input Barang Keluar</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('inventory.keluar.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih Barang <span class="text-danger">*</span></label>
                        <select name="inventory_item_id" id="keluar_item_select" class="form-select @error('inventory_item_id') is-invalid @enderror" required onchange="updateStockInfo(this)">
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-stock="{{ $item->stock }}" data-unit="{{ $item->unit_label }}" {{ old('inventory_item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->category_label }}) — Stok: {{ $item->stock }} {{ $item->unit_label }}
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div id="stock_info" class="mb-3" style="display:none;">
                        <div class="alert alert-warning py-2 mb-0" style="font-size:0.82rem;">
                            <i class="fa-solid fa-box me-1"></i>Stok tersedia: <strong id="stock_available">0</strong> <span id="stock_unit"></span>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Jumlah <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity') }}" min="1" required placeholder="0">
                            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Kondisi <span class="text-danger">*</span></label>
                            <select name="condition" class="form-select @error('condition') is-invalid @enderror" required>
                                <option value="baik" {{ old('condition', 'baik') === 'baik' ? 'selected' : '' }}>Baik</option>
                                <option value="terpakai" {{ old('condition') === 'terpakai' ? 'selected' : '' }}>Terpakai</option>
                                <option value="rusak" {{ old('condition') === 'rusak' ? 'selected' : '' }}>Rusak</option>
                            </select>
                            @error('condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control @error('date') is-invalid @enderror"
                                   value="{{ old('date', date('Y-m-d')) }}" required>
                            @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pelanggan (Opsional)</label>
                        <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                            <option value="">-- Tidak Terkait Pelanggan --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->customer_code }} — {{ $customer->name }} ({{ $customer->phone }})
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Keterangan</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                  placeholder="Contoh: Pemasangan baru di Jl. Merdeka No.5">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="fa-solid fa-check me-1"></i>Catat Barang Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- Riwayat --}}
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:#dc2626;"></div>
                <span class="fw-semibold">Riwayat Barang Keluar</span>
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
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
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
                                <th>Kondisi</th>
                                <th>Pelanggan</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>

                        <tbody>
                            @forelse($transactions as $trx)
                                <tr>
                                    <td>{{ $trx->date->format('d/m/Y') }}</td>
                                    <td class="fw-semibold">{{ $trx->item->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">-{{ $trx->quantity }}</span>
                                    </td>
                                    <td>
                                        @if($trx->condition === 'baik')
                                            <span class="badge bg-success">{{ $trx->condition_label }}</span>
                                        @elseif($trx->condition === 'terpakai')
                                            <span class="badge bg-warning text-dark">{{ $trx->condition_label }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $trx->condition_label }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($trx->customer)
                                            <small>{{ $trx->customer->customer_code }} — {{ $trx->customer->name }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $trx->notes ?? '-' }}</td>
                                    <td>{{ $trx->creator->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat barang keluar.</td>
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
@push('scripts')
<script>
function updateStockInfo(select) {
    const info = document.getElementById('stock_info');
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('stock_available').textContent = opt.dataset.stock;
        document.getElementById('stock_unit').textContent = opt.dataset.unit;
        info.style.display = '';
    } else {
        info.style.display = 'none';
    }
}
</script>
@endpush
