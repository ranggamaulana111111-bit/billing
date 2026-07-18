@extends('layouts.app')
@section('title', 'Suspend / Isolir')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pause-circle me-2" style="color:#d97706;"></i>Suspend / Isolir</h2>
        <p class="section-subtitle mb-0 mt-1">Pelanggan yang ditangguhkan — PPP Profile isolir aktif</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-list me-1"></i>Semua Pelanggan
        </a>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#d97706;"></div>
            <span>Pelanggan Ditangguhkan</span>
            <span class="badge badge-premium ms-2" style="background:#fef3c7;color:#d97706;">{{ $customers->total() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Pelanggan</th>
                        <th>Paket</th>
                        <th>Telepon</th>
                        <th>Ditangguhkan Sejak</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($customers as $c)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0;">
                                        {{ strtoupper(substr($c->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $c->name }}</div>
                                        <small style="font-size:0.7rem;color:var(--primary);font-weight:600;">{{ $c->customer_code }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">{{ $c->package->name ?? '-' }}</span></td>
                            <td>{{ $c->phone ?? '-' }}</td>
                            <td><small class="text-muted">{{ $c->suspended_at ? \Carbon\Carbon::parse($c->suspended_at)->format('d/m/Y H:i') : '-' }}</small></td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('customer.activate', $c->customer_code) }}" class="d-inline" onsubmit="return confirm('Aktifkan kembali {{ $c->name }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success px-3" title="Aktifkan Kembali">
                                        <i class="fa-solid fa-play me-1"></i>Aktifkan
                                    </button>
                                </form>
                                <a href="{{ route('customer.edit', $c->customer_code) }}" class="btn btn-sm btn-outline-primary px-2" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-circle-check" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Tidak ada pelanggan yang ditangguhkan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($customers->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $customers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
