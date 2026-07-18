@extends('layouts.app')
@section('title', 'Voucher WiFi')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-ticket me-2" style="color:var(--primary);"></i>Voucher WiFi</h2>
        <p class="section-subtitle mb-0 mt-1">Generate, kelola, laporan, profile & template hotspot</p>
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
            <i class="fa-solid fa-plus me-2"></i>Generate Voucher
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
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all"></th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>

                    <tbody>
                        @forelse($vouchers as $v)
                            <tr class="{{ $v->status === 'expired' ? 'table-warning' : ($v->status === 'used' ? 'table-danger' : '') }}">
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
                                            'expired' => ['bg' => '#fef3c7', 'text' => '#92400e'],
                                            default => ['bg' => '#f1f5f9', 'text' => '#64748b'],
                                        };
                                    @endphp
                                    <span class="badge badge-premium" style="background:{{ $badge['bg'] }};color:{{ $badge['text'] }};">
                                        {{ ucfirst($v->status) }}
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;">{{ $v->created_at->format('d M Y H:i') }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('vouchers.print', $v->id) }}" class="btn btn-sm btn-outline-secondary px-2" title="Cetak" target="_blank">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                                <li><a class="dropdown-item" href="{{ route('vouchers.print', $v->id) }}" target="_blank"><i class="fa-solid fa-print me-2 text-secondary"></i>Cetak</a></li>
                                                @if($v->status === 'active')
                                                    <li>
                                                        <form action="{{ route('vouchers.used', $v->id) }}" method="POST" onsubmit="return confirm('Tandai voucher {{ $v->username }} sebagai terpakai?')">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="fa-solid fa-check me-2 text-success"></i>Tandai Terpakai</button>
                                                        </form>
                                                    </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('vouchers.destroy', $v->id) }}" method="POST" onsubmit="return confirm('Hapus voucher {{ $v->username }}?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
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
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Username</th>
                        <th>Profile</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Expires</th>
                        <th>Cetak</th>
                    </tr>

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
{{-- TAB 3: PROFILES (MikroTik) --}}
@if($tab === 'profiles')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted">Profile hotspot dari MikroTik</span>
    </div>
    <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createMikrotikProfileModal">
        <i class="fa-solid fa-plus me-1"></i>Buat Profile
    </button>
</div>
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Name</th>
                        <th>Address Pool</th>
                        <th>Shared Users</th>
                        <th>Rate Limit</th>
                        <th>Price</th>
                        <th>Selling Price</th>
                        <th>Lock User</th>
                        <th>Parent Queue</th>
                        <th>Router</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($mikrotikProfiles as $profile)
                        <tr>
                            <td class="fw-semibold">{{ $profile['name'] }}</td>
                            <td>{{ $profile['address_pool'] ?? '-' }}</td>
                            <td class="text-center">{{ $profile['shared_users'] }}</td>
                            <td><code>{{ $profile['speed'] ?? '-' }}</code></td>
                            <td>Rp {{ number_format($profile['price'] ?? 0, 0, ',', '.') }}</td>
                            <td>{{ $profile['selling_price'] ? 'Rp '.number_format($profile['selling_price'], 0, ',', '.') : '-' }}</td>
                            <td class="text-center">
                                @if($profile['lock_user'])
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">Enable</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Disable</span>
                                @endif
                            </td>
                            <td>{{ $profile['parent_queue'] ?? '-' }}</td>
                            <td><span class="badge" style="background:#eef2ff;color:#4f46e5;">{{ $profile['router'] }}</span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary px-2 edit-mikrotik-profile-btn"
                                        data-id="{{ $profile['id'] }}"
                                        data-name="{{ $profile['name'] }}"
                                        data-speed="{{ $profile['speed'] ?? '' }}"
                                        data-shared="{{ $profile['shared_users'] }}"
                                        data-address-pool="{{ $profile['address_pool'] ?? '' }}"
                                        data-lock-user="{{ $profile['lock_user'] ? '1' : '0' }}"
                                        data-price="{{ $profile['price'] ?? 0 }}"
                                        data-selling-price="{{ $profile['selling_price'] ?? '' }}"
                                        data-parent-queue="{{ $profile['parent_queue'] ?? '' }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                            <li><a class="dropdown-item edit-mikrotik-profile-btn" href="#"
                                                data-id="{{ $profile['id'] }}"
                                                data-name="{{ $profile['name'] }}"
                                                data-speed="{{ $profile['speed'] ?? '' }}"
                                                data-shared="{{ $profile['shared_users'] }}"
                                                data-address-pool="{{ $profile['address_pool'] ?? '' }}"
                                                data-lock-user="{{ $profile['lock_user'] ? '1' : '0' }}"
                                                data-price="{{ $profile['price'] ?? 0 }}"
                                                data-selling-price="{{ $profile['selling_price'] ?? '' }}"
                                                data-parent-queue="{{ $profile['parent_queue'] ?? '' }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('voucher-profiles.destroy-mikrotik', $profile['id']) }}" onsubmit="return confirm('Hapus profile &quot;{{ $profile['name'] }}&quot; dari MikroTik?')">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada profile MikroTik</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{-- Create MikroTik Profile Modal --}}
<div class="modal fade" id="createMikrotikProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('voucher-profiles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Buat Profile di MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Profile name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address Pool</label>
                            <input type="text" name="address_pool" class="form-control" placeholder="none">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shared Users</label>
                            <input type="number" name="shared_users" class="form-control" value="1" min="1" max="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rate limit [up/down]</label>
                            <input type="text" name="speed" class="form-control" placeholder="Example : 512k/1M">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price Rp</label>
                            <input type="number" name="price" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Selling Price Rp</label>
                            <input type="number" name="selling_price" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                Lock User
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" name="lock_user" class="form-check-input" id="createMkLockUser" value="1">
                                    <label class="form-check-label" for="createMkLockUser">Disable</label>
                                </div>
                            </label>
                            <small class="text-muted d-block">Username can only be used on 1 device only.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Queue</label>
                            <input type="text" name="parent_queue" class="form-control" placeholder="none">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Buat di MikroTik</button>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Edit MikroTik Profile Modal --}}
<div class="modal fade" id="editMikrotikProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile di MikroTik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address Pool</label>
                            <input type="text" name="address_pool" class="form-control" placeholder="none">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shared Users</label>
                            <input type="number" name="shared_users" class="form-control" min="1" max="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rate limit [up/down]</label>
                            <input type="text" name="speed" class="form-control" placeholder="Example : 512k/1M">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price Rp</label>
                            <input type="number" name="price" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Selling Price Rp</label>
                            <input type="number" name="selling_price" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                Lock User
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" name="lock_user" class="form-check-input" id="editMkLockUser" value="1">
                                    <label class="form-check-label" for="editMkLockUser">Disable</label>
                                </div>
                            </label>
                            <small class="text-muted d-block">Username can only be used on 1 device only.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Queue</label>
                            <input type="text" name="parent_queue" class="form-control" placeholder="none">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
{{-- TAB 4: TEMPLATES --}}
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
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="fw-semibold">{{ $tpl->name }}</td>
                            <td>
                                @if($tpl->hasFiles())
                                    <span class="badge" style="background:#f0fdf4;color:#059669;">
                                        <i class="fa-solid fa-folder me-1"></i>Folder
                                    </span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">
                                        <i class="fa-solid fa-database me-1"></i>Database
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge" style="background:{{ $tpl->is_active ? '#f0fdf4' : '#f1f5f9' }};color:{{ $tpl->is_active ? '#059669' : '#64748b' }};">
                                    {{ $tpl->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ $tpl->vouchers()->count() }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    @if($tpl->hasFiles())
                                        <a href="{{ url('hotspot/templates/' . $tpl->id . '/login.html') }}" class="btn btn-sm btn-outline-info px-2" target="_blank" title="Preview">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('voucher-templates.preview', $tpl) }}" class="btn btn-sm btn-outline-info px-2" target="_blank" title="Preview">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    @endif
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                            @if($tpl->hasFiles())
                                                <li><a class="dropdown-item" href="{{ url('hotspot/templates/' . $tpl->id . '/login.html') }}" target="_blank"><i class="fa-solid fa-eye me-2 text-info"></i>Preview</a></li>
                                            @else
                                                <li><a class="dropdown-item" href="{{ route('voucher-templates.preview', $tpl) }}" target="_blank"><i class="fa-solid fa-eye me-2 text-info"></i>Preview</a></li>
                                            @endif
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editTemplateModal{{ $tpl->id }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('voucher-templates.destroy', $tpl) }}" onsubmit="return confirm('Hapus template {{ $tpl->name }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
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
            <form method="POST" action="{{ route('voucher-templates.store') }}" enctype="multipart/form-data">
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File Template (ZIP)</label>
                        <div class="border rounded p-3" style="background:#f8fafc;">
                            <input type="file" name="template_file" class="form-control" accept=".zip" required>
                            <div class="form-text mt-2">
                                <i class="fa-solid fa-info-circle me-1"></i>Upload file <strong>.zip</strong> yang berisi folder template hotspot.
                                <br>Pastikan di dalamnya ada file <code>login.html</code>, <code>status.html</code>, dll beserta asset pendukung (CSS, JS, gambar).
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-0">
                        <small><i class="fa-solid fa-lightbulb me-1"></i>Template akan otomatis disalin ke folder hotspot aktif jika status <strong>Aktif</strong> dicentang.</small>
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
            <form method="POST" action="{{ route('voucher-templates.update', $tpl) }}" enctype="multipart/form-data">
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ganti File Template (ZIP)</label>
                        <div class="border rounded p-3" style="background:#f8fafc;">
                            <input type="file" name="template_file" class="form-control" accept=".zip">
                            <div class="form-text mt-2">
                                <i class="fa-solid fa-info-circle me-1"></i>Kosongkan jika tidak ingin mengganti file template.
                                <br>Upload file <strong>.zip</strong> berisi folder template hotspot baru jika ingin mengganti.
                            </div>
                        </div>
                    </div>
                    @if($tpl->hasFiles())
                        <div class="mb-3">
                            <label class="form-label fw-semibold">File Saat Ini</label>
                            <div class="border rounded p-3" style="background:#f0fdf4;">
                                <small class="text-muted">
                                    <i class="fa-solid fa-folder-open me-1"></i>
                                    Template ini menggunakan file dari folder <code>hotspot/templates/{{ $tpl->id }}/</code>
                                </small>
                                <div class="mt-2">
                                    <a href="{{ url('hotspot/templates/' . $tpl->id . '/login.html') }}" class="btn btn-sm btn-outline-info" target="_blank">
                                        <i class="fa-solid fa-eye me-1"></i>Lihat Template
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning py-2">
                            <small><i class="fa-solid fa-triangle-exclamation me-1"></i>Template ini menggunakan konten dari database. Upload file ZIP untuk beralih ke folder-based template.</small>
                        </div>
                    @endif
                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editTemplateActive{{ $tpl->id }}" {{ $tpl->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="editTemplateActive{{ $tpl->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-info" onclick="window.open('{{ $tpl->hasFiles() ? url('hotspot/templates/'.$tpl->id.'/login.html') : route('voucher-templates.preview', $tpl) }}', '_blank')">
                        <i class="fa-solid fa-eye me-1"></i>Preview
                    </button>
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
    // ── Select all / Print ──
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
    // ── Edit MikroTik Profile ──
    document.querySelectorAll('.edit-mikrotik-profile-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const speed = this.dataset.speed;
            const shared = this.dataset.shared;
            const addressPool = this.dataset.addressPool;
            const lockUser = this.dataset.lockUser;
            const price = this.dataset.price;
            const sellingPrice = this.dataset.sellingPrice;
            const parentQueue = this.dataset.parentQueue;
            const modal = document.getElementById('editMikrotikProfileModal');
            const form = modal.querySelector('form');
            form.action = '{{ route("voucher-profiles.update-mikrotik", "_id_") }}'.replace('_id_', id);
            form.querySelector('[name="name"]').value = name;
            form.querySelector('[name="speed"]').value = speed;
            form.querySelector('[name="shared_users"]').value = shared;
            form.querySelector('[name="address_pool"]').value = addressPool;
            form.querySelector('[name="price"]').value = price;
            form.querySelector('[name="selling_price"]').value = sellingPrice;
            form.querySelector('[name="parent_queue"]').value = parentQueue;
            form.querySelector('[name="lock_user"]').checked = lockUser === '1';
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });
</script>
@endpush
