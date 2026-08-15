@extends('layouts.app')
@section('title', 'Pembayaran')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-money-bill-wave me-2" style="color:var(--primary);"></i>Pembayaran</h2>
        <p class="section-subtitle mb-0 mt-1">Riwayat semua pembayaran dari pelanggan</p>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:var(--primary);">Rp {{ number_format($stats['total'], 0, ',', '.') }}</div>
            <small class="text-muted">Total Pendapatan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#059669;">Rp {{ number_format($stats['today'], 0, ',', '.') }}</div>
            <small class="text-muted">Hari Ini</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#2563eb;">Rp {{ number_format($stats['month'], 0, ',', '.') }}</div>
            <small class="text-muted">Bulan Ini</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#d97706;">{{ $stats['count'] }}</div>
            <small class="text-muted">Total Transaksi</small>
        </div>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Riwayat Pembayaran</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $payments->total() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Tanggal</th>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Paket</th>
                        <th>Metode</th>
                        <th class="text-end">Jumlah</th>
                    </tr>

                <tbody>
                    @forelse($payments as $p)
                        <tr>
                            <td><small class="text-muted">{{ $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y') : $p->created_at->format('d/m/Y') }}</small></td>
                            <td><code style="font-size:0.75rem;">{{ $p->invoice->invoice_code ?? '-' }}</code></td>
                            <td>
                                <div class="fw-semibold" style="font-size:0.85rem;">{{ $p->invoice->customer->name ?? '-' }}</div>
                                <small class="text-muted">{{ $p->invoice->customer->customer_code ?? '' }}</small>
                            </td>
                            <td><span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">{{ $p->invoice->customer->package->name ?? '-' }}</span></td>
                            <td>
                                @php
                                    $methods = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'midtrans' => 'Midtrans', 'xendit' => 'Xendit'];
                                    $colors = ['cash' => '#059669', 'transfer' => '#2563eb', 'qris' => '#7c3aed', 'midtrans' => '#e11d48', 'xendit' => '#6366f1'];
                                @endphp
                                <span class="badge" style="background:{{ $colors[$p->payment_method] ?? '#6b7280' }}22;color:{{ $colors[$p->payment_method] ?? '#6b7280' }};">
                                    {{ $methods[$p->payment_method] ?? $p->payment_method }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold" style="color:#059669;">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-money-bill-wave" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada pembayaran tercatat
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payments->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $payments->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
