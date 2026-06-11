@extends('layouts.app')

@section('title', 'Riwayat Pembayaran')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>Riwayat Pembayaran</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $invoice->invoice_code }} — {{ $invoice->customer->name }}</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('invoice.print', $invoice->id) }}" class="btn btn-outline-secondary px-3" target="_blank">
            <i class="fa-solid fa-print me-1"></i>Cetak Faktur
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Data Pembayaran</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <small class="text-muted d-block">Invoice</small>
                <span class="fw-bold">{{ $invoice->invoice_code }}</span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Total Tagihan</small>
                <span class="fw-bold">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Status</small>
                @if($invoice->payment_status === 'paid')
                    <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">Lunas</span>
                @else
                    <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">Belum Dibayar</span>
                @endif
            </div>
        </div>

        @if($payments->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Metode</th>
                        <th class="text-end">Jumlah</th>
                        <th>Catatan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr>
                            <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">
                                    @switch($p->payment_method)
                                        @case('cash') Tunai @break
                                        @case('transfer') Transfer @break
                                        @case('qris') QRIS @break
                                        @case('midtrans') Midtrans @break
                                        @default {{ $p->payment_method }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="fw-bold text-end">Rp{{ number_format($p->amount, 0, ',', '.') }}</td>
                            <td class="text-muted">{{ $p->notes ?? '-' }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('payment.destroy', $p->id) }}" class="d-inline" onsubmit="return confirm('Hapus pembayaran ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-center text-muted py-3">Belum ada pembayaran tercatat.</p>
        @endif

        <div class="mt-3">
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>
</div>
@endsection
