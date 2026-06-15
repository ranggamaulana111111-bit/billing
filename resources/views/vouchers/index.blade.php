@extends('layouts.app')

@section('title', 'Voucher WiFi')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-ticket me-2" style="color:var(--primary);"></i>Voucher WiFi</h2>
        <p class="section-subtitle mb-0 mt-1">Generate, kelola, laporan, profile & router hotspot</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if($mikrotikConnected)
            <form action="{{ route('vouchers.sync-mikrotik') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-premium px-3 py-2" onclick="return confirm('Sinkronisasi dengan MikroTik?')">
                    <i class="fa-solid fa-rotate me-1"></i>Sync
                </button>
            </form>
        @endif
        <a href="{{ route('vouchers.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-plus me-2"></i>Buat Voucher
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">
        {{ session('success') }}
        @if(session('vouchers'))
            <div class="mt-2 d-flex gap-2 flex-wrap">
                @foreach(session('vouchers') as $v)
                    <a href="{{ route('vouchers.print', $v->id) }}" class="btn btn-sm btn-outline-light" target="_blank">
                        <i class="fa-solid fa-print me-1"></i>{{ $v->username }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- TABS --}}
<ul class="nav nav-tabs mb-4" id="voucherTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $tab === 'vouchers' ? 'active' : '' }}" href="{{ route('vouchers.index', ['tab' => 'vouchers']) }}" role="tab">
            <i class="fa-solid fa-ticket me-1"></i>Voucher
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $tab === 'report' ? 'active' : '' }}" href="{{ route('vouchers.index', ['tab' => 'report']) }}" role="tab">
            <i class="fa-solid fa-chart-simple me-1"></i>Laporan
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $tab === 'profiles' ? 'active' : '' }}" href="{{ route('vouchers.index', ['tab' => 'profiles']) }}" role="tab">
            <i class="fa-solid fa-tags me-1"></i>Profile
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $tab === 'routers' ? 'active' : '' }}" href="{{ route('vouchers.index', ['tab' => 'routers']) }}" role="tab">
            <i class="fa-solid fa-server me-1"></i>Router
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $tab === 'templates' ? 'active' : '' }}" href="{{ route('vouchers.index', ['tab' => 'templates']) }}" role="tab">
            <i class="fa-solid fa-palette me-1"></i>Template
        </a>
    </li>
</ul>

{{-- TAB 1: VOUCHERS --}}
@if($tab === 'vouchers')
<div class="row g-4 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-ticket"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Voucher</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-check"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card stat-card stat-card-gradient-orange text-white">
            <div class="stat-bg"><i class="fa-solid fa-clock"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['expired'] }}</div>
                <div class="stat-label">Kadaluarsa</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.2s">
        <div class="card stat-card stat-card-gradient-red text-white">
            <div class="stat-bg"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $stats['used'] }}</div>
                <div class="stat-label">Terpakai</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Daftar Voucher</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $vouchers->total() }}</span>
            @if($mikrotikConnected)
                <span class="badge" style="background:#ecfdf5;color:#059669;font-size:0.65rem;">
                    <i class="fa-solid fa-wifi me-1"></i>MikroTik
                </span>
            @else
                <span class="badge" style="background:#f1f5f9;color:#94a3b8;font-size:0.65rem;">
                    <i class="fa-solid fa-plug me-1"></i>Off
                </span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="tab" value="vouchers">
                <select name="status" class="form-select form-select-sm" style="width:auto;border-radius:8px;font-size:0.8rem;">
                    <option value="">Semua Status</option>
                    <option value="active" {{ ($filterStatus ?? '') == 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="used" {{ ($filterStatus ?? '') == 'used' ? 'selected' : '' }}>Terpakai</option>
                    <option value="expired" {{ ($filterStatus ?? '') == 'expired' ? 'selected' : '' }}>Expired</option>
                </select>
                <div class="input-group input-group-sm" style="width:200px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari username..." value="{{ $search ?? '' }}" style="border-radius:8px 0 0 8px;font-size:0.8rem;">
                    <button class="btn btn-outline-secondary" type="submit" style="border-radius:0 8px 8px 0;"><i class="fa-solid fa-search"></i></button>
                </div>
                @if(($search ?? '') || ($filterStatus ?? ''))
                    <a href="{{ route('vouchers.index', ['tab' => 'vouchers']) }}" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-times"></i></a>
                @endif
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <form id="batch-form" method="GET" action="{{ route('vouchers.print-batch') }}" target="_blank">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all"></th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $v)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $v->id }}" class="voucher-check"></td>
                                <td><code style="font-size:0.85rem;">{{ $v->username }}</code></td>
                                <td><code style="font-size:0.85rem;">{{ $v->password }}</code></td>
                                <td>
                                    @php
                                        $days = intdiv($v->duration_hours, 24);
                                        $hours = $v->duration_hours % 24;
                                        $durText = $days > 0
                                            ? trim($days.' hari '.($hours > 0 ? $hours.' jam' : ''))
                                            : $hours.' jam';
                                    @endphp
                                    {{ $durText }}
                                </td>
                                <td>
                                    @php
                                        $badge = match($v->status) {
                                            'active' => ['bg' => '#f0fdf4', 'text' => '#059669'],
                                            'used' => ['bg' => '#fef2f2', 'text' => '#dc2626'],
                                            'expired' => ['bg' => '#f1f5f9', 'text' => '#94a3b8'],
                                            default => ['bg' => '#f1f5f9', 'text' => '#64748b'],
                                        };
                                    @endphp
                                    <span class="badge badge-premium" style="background:{{ $badge['bg'] }};color:{{ $badge['text'] }};">
                                        {{ ucfirst($v->status) }}
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;">{{ $v->created_at->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('vouchers.print', $v->id) }}" class="btn btn-sm btn-outline-secondary px-2" title="Cetak" target="_blank">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        @if($v->status === 'active')
                                            <form action="{{ route('vouchers.used', $v->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tandai voucher {{ $v->username }} sebagai terpakai?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Tandai Terpakai">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('vouchers.destroy', $v->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus voucher {{ $v->username }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-ticket" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                    Belum ada voucher. <a href="{{ route('vouchers.create') }}" style="color:var(--primary);font-weight:600;">Buat sekarang</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    @if($vouchers->count() > 0)
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <button type="submit" form="batch-form" class="btn btn-sm btn-outline-premium" id="print-selected" disabled onclick="return confirm('Cetak voucher terpilih?')">
                <i class="fa-solid fa-print me-1"></i>Cetak Terpilih
            </button>
            <div>
                {{ $vouchers->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>
@endif

{{-- TAB 2: REPORT --}}
@if($tab === 'report')
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="card-body">
                <div class="stat-number">{{ $reportStats['total'] }}</div>
                <div class="stat-label">Total Voucher</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="card-body">
                <div class="stat-number">{{ $reportStats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <div class="card-body text-white">
                <div class="stat-number">{{ $reportStats['used'] }}</div>
                <div class="stat-label">Terpakai</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="background:linear-gradient(135deg,#059669,#047857);">
            <div class="card-body text-white">
                <div class="stat-number">Rp {{ number_format($reportStats['revenue'], 0, ',', '.') }}</div>
                <div class="stat-label">Pendapatan</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="tab" value="report">
            <div class="col-md-3">
                <label class="form-label">Profile</label>
                <select name="report_profile_id" class="form-select">
                    <option value="">Semua Profile</option>
                    @foreach($reportProfiles as $id => $name)
                        <option value="{{ $id }}" {{ request('report_profile_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="report_status" class="form-select">
                    <option value="">Semua</option>
                    <option value="active" {{ request('report_status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="used" {{ request('report_status') === 'used' ? 'selected' : '' }}>Terpakai</option>
                    <option value="expired" {{ request('report_status') === 'expired' ? 'selected' : '' }}>Kadaluarsa</option>
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
                    @forelse($reportVouchers as $v)
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
            {{ $reportVouchers->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endif

{{-- TAB 3: PROFILES --}}
@if($tab === 'profiles')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted">Kelola profile paket voucher hotspot</span>
    </div>
    <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createProfileModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Profile
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Speed</th>
                        <th>Harga</th>
                        <th>Time Limit</th>
                        <th>Kuota</th>
                        <th>Masa Berlaku</th>
                        <th>Shared</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td class="fw-semibold">{{ $profile->name }}</td>
                            <td>{{ $profile->speed ?? '-' }}</td>
                            <td>Rp {{ number_format($profile->price, 0, ',', '.') }}</td>
                            <td>{{ $profile->time_limit ? $profile->time_limit.' Jam' : 'Unlimited' }}</td>
                            <td>{{ $profile->quota_limit ? number_format($profile->quota_limit).' MB' : 'Unlimited' }}</td>
                            <td>{{ $profile->validity_days ? $profile->validity_days.' Hari' : '-' }}</td>
                            <td class="text-center">{{ $profile->shared_users }}</td>
                            <td>
                                <span class="badge" style="background:{{ $profile->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $profile->is_active ? '#059669' : '#64748b' }};">
                                    {{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editProfileModal{{ $profile->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('voucher-profiles.destroy', $profile) }}" class="d-inline" onsubmit="return confirm('Hapus profile {{ $profile->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada profile voucher</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Profile Modal --}}
<div class="modal fade" id="createProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-profiles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Profile Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Paket 10GB" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Speed</label>
                            <input type="text" name="speed" class="form-control" placeholder="10Mbps">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Harga (Rp)</label>
                            <input type="number" name="price" class="form-control" value="0" min="0" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Limit (Jam)</label>
                            <input type="number" name="time_limit" class="form-control" placeholder="Kosongkan jika unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kuota (MB)</label>
                            <input type="number" name="quota_limit" class="form-control" placeholder="Kosongkan jika unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Masa Berlaku (Hari)</label>
                            <input type="number" name="validity_days" class="form-control" placeholder="Kosongkan jika 1 hari">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="number" name="shared_users" class="form-control" value="1" min="1" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsional"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createProfileIsActive" checked>
                        <label class="form-check-label" for="createProfileIsActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Profile Modals --}}
@foreach($profiles as $profile)
<div class="modal fade" id="editProfileModal{{ $profile->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-profiles.update', $profile) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Profile</label>
                        <input type="text" name="name" class="form-control" value="{{ $profile->name }}" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Speed</label>
                            <input type="text" name="speed" class="form-control" value="{{ $profile->speed }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Harga (Rp)</label>
                            <input type="number" name="price" class="form-control" value="{{ $profile->price }}" min="0" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Limit (Jam)</label>
                            <input type="number" name="time_limit" class="form-control" value="{{ $profile->time_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kuota (MB)</label>
                            <input type="number" name="quota_limit" class="form-control" value="{{ $profile->quota_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Masa Berlaku (Hari)</label>
                            <input type="number" name="validity_days" class="form-control" value="{{ $profile->validity_days }}">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Shared Users</label>
                        <input type="number" name="shared_users" class="form-control" value="{{ $profile->shared_users }}" min="1" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2">{{ $profile->description }}</textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editProfileIsActive{{ $profile->id }}" {{ $profile->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editProfileIsActive{{ $profile->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- TAB 4: ROUTERS --}}
@if($tab === 'routers')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted">Kelola router MikroTik untuk push voucher hotspot</span>
    </div>
    <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createRouterModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Router
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Hotspot Server</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routers as $router)
                        <tr>
                            <td class="fw-semibold">{{ $router->name }}</td>
                            <td><code>{{ $router->host }}:{{ $router->port }}</code></td>
                            <td>{{ $router->port }}</td>
                            <td>{{ $router->hotspot_server ?: 'default' }}</td>
                            <td>
                                <span class="badge" style="background:{{ $router->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $router->is_active ? '#059669' : '#64748b' }};">
                                    {{ $router->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ $router->vouchers()->count() }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('mikrotik-routers.test', $router) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success px-2" title="Test Koneksi">
                                        <i class="fa-solid fa-plug"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editRouterModal{{ $router->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('mikrotik-routers.destroy', $router) }}" class="d-inline" onsubmit="return confirm('Hapus router {{ $router->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada router</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Router Modal --}}
<div class="modal fade" id="createRouterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mikrotik-routers.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Router MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: RB-Main" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host/IP</label>
                            <input type="text" name="host" class="form-control" placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="8728" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" placeholder="Kosongkan untuk default">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createRouterIsActive" checked>
                        <label class="form-check-label" for="createRouterIsActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Router Modals --}}
@foreach($routers as $router)
<div class="modal fade" id="editRouterModal{{ $router->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mikrotik-routers.update', $router) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Router</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Router</label>
                        <input type="text" name="name" class="form-control" value="{{ $router->name }}" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host/IP</label>
                            <input type="text" name="host" class="form-control" value="{{ $router->host }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="port" class="form-control" value="{{ $router->port }}" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" value="{{ $router->username }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Hotspot Server</label>
                        <input type="text" name="hotspot_server" class="form-control" value="{{ $router->hotspot_server }}">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editRouterIsActive{{ $router->id }}" {{ $router->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editRouterIsActive{{ $router->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- TAB 5: TEMPLATES --}}
@if($tab === 'templates')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted">Template landing page untuk voucher hotspot</span>
    </div>
    <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Template
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Konten</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="fw-semibold">{{ $tpl->name }}</td>
                            <td style="max-width:300px;">
                                <div class="text-truncate text-muted small" style="max-height:40px;overflow:hidden;">
                                    {{ $tpl->content ? strip_tags(substr($tpl->content, 0, 100)) : '-' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background:{{ $tpl->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $tpl->is_active ? '#059669' : '#64748b' }};">
                                    {{ $tpl->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ $tpl->vouchers()->count() }}</td>
                            <td class="text-center">
                                <a href="{{ route('voucher-templates.preview', $tpl) }}" class="btn btn-sm btn-outline-info px-2" target="_blank" title="Preview">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editTemplateModal{{ $tpl->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('voucher-templates.destroy', $tpl) }}" class="d-inline" onsubmit="return confirm('Hapus template {{ $tpl->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada template</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Template Modal --}}
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-templates.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Template Landing Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Template</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Premium Blue" required>
                    </div>

                    <ul class="nav nav-tabs mb-3" id="createTemplateTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#createLogin" role="tab">login.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#createStatus" role="tab">status.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#createRedirect" role="tab">redirect.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#createError" role="tab">error.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#createAlive" role="tab">alive.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#createLogout" role="tab">logout.html</a></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="createLogin">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Login <code>login.html</code></label>
                                <textarea name="content" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML login page --&gt;"></textarea>
                                <div class="form-text">Halaman login hotspot — user melihat form login di sini.</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="createStatus">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Status <code>status.html</code></label>
                                <textarea name="status_page" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML status page --&gt;"></textarea>
                                <div class="form-text">Halaman setelah login berhasil — menampilkan status koneksi.</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="createRedirect">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Redirect <code>redirect.html</code></label>
                                <textarea name="redirect_page" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML redirect page --&gt;"></textarea>
                                <div class="form-text">Halaman pengalihan setelah login sukses.</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="createError">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Error <code>error.html</code></label>
                                <textarea name="error_page" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML error page --&gt;"></textarea>
                                <div class="form-text">Halaman yang tampil saat login gagal (username/password salah).</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="createAlive">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Alive <code>alive.html</code></label>
                                <textarea name="alive_page" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML alive page --&gt;"></textarea>
                                <div class="form-text">Halaman keep-alive — MikroTik ping halaman ini untuk cek status session.</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="createLogout">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Logout <code>logout.html</code></label>
                                <textarea name="logout_page" class="form-control font-monospace" rows="8" placeholder="&lt;!-- HTML logout page --&gt;"></textarea>
                                <div class="form-text">Halaman yang tampil setelah user logout dari hotspot.</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="createTemplateActive" checked>
                        <label class="form-check-label" for="createTemplateActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Template Modals --}}
@foreach($templates as $tpl)
<div class="modal fade" id="editTemplateModal{{ $tpl->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-templates.update', $tpl) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Template: {{ $tpl->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Template</label>
                        <input type="text" name="name" class="form-control" value="{{ $tpl->name }}" required>
                    </div>

                    <ul class="nav nav-tabs mb-3" id="editTemplateTabs{{ $tpl->id }}" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#editLogin{{ $tpl->id }}" role="tab">login.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editStatus{{ $tpl->id }}" role="tab">status.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editRedirect{{ $tpl->id }}" role="tab">redirect.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editError{{ $tpl->id }}" role="tab">error.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editAlive{{ $tpl->id }}" role="tab">alive.html</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editLogout{{ $tpl->id }}" role="tab">logout.html</a></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="editLogin{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Login <code>login.html</code></label>
                                <textarea name="content" class="form-control font-monospace" rows="8">{{ $tpl->content }}</textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="editStatus{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Status <code>status.html</code></label>
                                <textarea name="status_page" class="form-control font-monospace" rows="8">{{ $tpl->status_page }}</textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="editRedirect{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Redirect <code>redirect.html</code></label>
                                <textarea name="redirect_page" class="form-control font-monospace" rows="8">{{ $tpl->redirect_page }}</textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="editError{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Error <code>error.html</code></label>
                                <textarea name="error_page" class="form-control font-monospace" rows="8">{{ $tpl->error_page }}</textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="editAlive{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Alive <code>alive.html</code></label>
                                <textarea name="alive_page" class="form-control font-monospace" rows="8">{{ $tpl->alive_page }}</textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="editLogout{{ $tpl->id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Halaman Logout <code>logout.html</code></label>
                                <textarea name="logout_page" class="form-control font-monospace" rows="8">{{ $tpl->logout_page }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editTemplateActive{{ $tpl->id }}" {{ $tpl->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editTemplateActive{{ $tpl->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('voucher-templates.preview', $tpl) }}" class="btn btn-outline-info" target="_blank">
                        <i class="fa-solid fa-eye me-1"></i>Preview
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif
@endsection

@push('scripts')
<script>
    const selectAll = document.getElementById('select-all');
    const checks = document.querySelectorAll('.voucher-check');
    const printBtn = document.getElementById('print-selected');

    function updatePrintBtn() {
        if (!printBtn) return;
        const checked = document.querySelectorAll('.voucher-check:checked').length;
        printBtn.disabled = checked === 0;
        if (checked > 0) {
            printBtn.innerHTML = '<i class="fa-solid fa-print me-1"></i>Cetak Terpilih (' + checked + ')';
        } else {
            printBtn.innerHTML = '<i class="fa-solid fa-print me-1"></i>Cetak Terpilih';
        }
    }

    selectAll?.addEventListener('change', function() {
        checks.forEach(cb => cb.checked = this.checked);
        updatePrintBtn();
    });

    checks.forEach(cb => {
        cb.addEventListener('change', updatePrintBtn);
    });

    updatePrintBtn();
</script>
@endpush
