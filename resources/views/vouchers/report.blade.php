@extends('layouts.app')

@section('title', 'Laporan Penjualan Voucher')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-line me-2" style="color:var(--primary);"></i>Laporan Penjualan Voucher</h2>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="card-body">
                <div class="stat-number">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Voucher</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="card-body">
                <div class="stat-number">{{ $stats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <div class="card-body text-white">
                <div class="stat-number">{{ $stats['used'] }}</div>
                <div class="stat-label">Terpakai</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="background:linear-gradient(135deg,#059669,#047857);">
            <div class="card-body text-white">
                <div class="stat-number">Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</div>
                <div class="stat-label">Pendapatan</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Profile</label>
                <select name="profile_id" class="form-select">
                    <option value="">Semua Profile</option>
                    @foreach($profiles as $id => $name)
                        <option value="{{ $id }}" {{ request('profile_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>Terpakai</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Kadaluarsa</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Profile</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Expires</th>
                        <th>Cetak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vouchers as $v)
                        <tr>
                            <td class="fw-semibold">{{ $v->username }}</td>
                            <td>{{ $v->profile->name ?? '-' }}</td>
                            <td>Rp {{ number_format($v->price ?? 0, 0, ',', '.') }}</td>
                            <td>
                                @php
                                    $badge = match($v->status) {
                                        'active' => ['bg' => '#f0fdf4', 'color' => '#059669', 'label' => 'Aktif'],
                                        'used' => ['bg' => '#fef2f2', 'color' => '#dc2626', 'label' => 'Terpakai'],
                                        'expired' => ['bg' => '#fff7ed', 'color' => '#d97706', 'label' => 'Kadaluarsa'],
                                        default => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => $v->status],
                                    };
                                @endphp
                                <span class="badge" style="background:{{ $badge['bg'] }};color:{{ $badge['color'] }};">{{ $badge['label'] }}</span>
                            </td>
                            <td>{{ $v->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $v->expires_at ? $v->expires_at->format('d/m/Y H:i') : '-' }}</td>
                            <td>
                                <a href="{{ route('vouchers.print', $v) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data voucher</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $vouchers->links() }}
        </div>
    </div>
</div>
@endsection
