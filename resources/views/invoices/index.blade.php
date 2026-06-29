@extends('layouts.app')

@section('title', 'Tagihan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-file-invoice me-2" style="color:var(--primary);"></i>Tagihan</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola tagihan pelanggan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('packages.mass-bill') }}" class="d-inline" onsubmit="return confirm('Buat tagihan untuk semua pelanggan aktif?')">
            @csrf
            <button type="submit" class="btn btn-outline-warning px-3 py-2">
                <i class="fa-solid fa-users-gear me-1"></i>Tagih Massal
            </button>
        </form>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-plus me-2"></i>Buat Tagihan
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- FILTERS --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice / Pelanggan..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Dibayar</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Dari</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Sampai</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-rotate me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Daftar Tagihan</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $invoices->total() }}</span>
        </div>
        <small class="text-muted">Halaman {{ $invoices->currentPage() }} dari {{ $invoices->lastPage() }}</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Paket</th>
                        <th>Periode</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <span class="badge badge-premium" style="background:#eef2ff;color:var(--primary);">
                                    {{ $inv->invoice_code }}
                                </span>
                            </td>
                            <td class="fw-medium">
                                {{ $inv->customer->name ?? '-' }}
                                @if($inv->customer && !empty($customerPaidMonths[$inv->customer_id]))
                                    <div style="font-size:0.7rem;color:#64748b;margin-top:2px;">
                                        @foreach($customerPaidMonths[$inv->customer_id] as $ym)
                                            @php $monthName = \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M'); @endphp
                                            <span title="Lunas {{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('F Y') }}" style="display:inline-block;background:#f0fdf4;color:#059669;padding:0 6px;border-radius:4px;margin-right:2px;">{{ $monthName }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ $inv->customer->package->name ?? '-' }}</td>
                            <td style="font-size:0.8rem;">{{ $inv->created_at->format('M Y') }}</td>
                            <td class="fw-bold text-end">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if($inv->payment_status === 'paid')
                                    <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">
                                        <i class="fa-regular fa-circle-check me-1"></i>Lunas
                                    </span>
                                @else
                                    <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">
                                        <i class="fa-regular fa-clock me-1"></i>Belum
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1 flex-wrap" style="min-width:130px;">
                                    <a href="{{ route('invoice.print', $inv->id) }}" class="btn btn-sm btn-outline-secondary px-2" title="Cetak A4" target="_blank">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <a href="{{ route('invoice.print-thermal', $inv->id) }}" class="btn btn-sm btn-outline-dark px-2" title="Cetak Thermal 58mm" target="_blank">
                                        <i class="fa-solid fa-receipt"></i>
                                    </a>
                                    <a href="{{ route('invoice.pdf', $inv->id) }}" class="btn btn-sm btn-outline-danger px-2" title="Download PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                      @if($inv->payment_status === 'unpaid')
                                          <a href="{{ route('payment.create', $inv->id) }}" class="btn btn-sm btn-info text-white px-2" title="Catat Pembayaran">
                                              <i class="fa-solid fa-money-bill-wave"></i>
                                          </a>
                                          <a href="{{ route('invoice.paid', $inv->id) }}" class="btn btn-sm btn-success px-2" title="Tandai Lunas" onclick="return confirm('Konfirmasi pembayaran untuk {{ $inv->customer->name ?? '?' }}?')">
                                              <i class="fa-solid fa-check"></i>
                                          </a>
                                          @if($inv->payment_status === 'unpaid' && \App\Models\Setting::get('midtrans_server_key'))
                                         <a href="{{ route('midtrans.pay', $inv->id) }}" class="btn btn-sm btn-outline-warning px-2" title="Bayar via Midtrans">
                                             <i class="fa-solid fa-credit-card"></i>
                                         </a>
                                        @endif
                                        <a href="{{ route('invoice.edit', $inv->id) }}" class="btn btn-sm btn-outline-primary px-2" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form method="POST" action="{{ route('invoice.destroy', $inv->id) }}" class="d-inline" onsubmit="return confirm('Hapus tagihan {{ $inv->invoice_code }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('invoice.reminder', $inv->id) }}" class="btn btn-sm btn-outline-success px-2" title="WA Reminder" onclick="return confirm('Kirim reminder WA?')">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                        @if($inv->customer?->email)
                                        <a href="{{ route('invoice.email-reminder', $inv->id) }}" class="btn btn-sm btn-outline-primary px-2" title="Email Reminder" onclick="return confirm('Kirim email reminder?')">
                                            <i class="fa-solid fa-envelope"></i>
                                        </a>
                                        @endif
                                    @else
                                        <a href="{{ route('payment.history', $inv->id) }}" class="btn btn-sm btn-outline-info px-2" title="Riwayat Pembayaran">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </a>
                                        @if($inv->customer?->email)
                                        <a href="{{ route('invoice.email-payment', $inv->id) }}" class="btn btn-sm btn-outline-primary px-2" title="Email Konfirmasi">
                                            <i class="fa-solid fa-envelope"></i>
                                        </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-file-lines" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada tagihan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $invoices->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
