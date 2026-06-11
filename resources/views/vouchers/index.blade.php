@extends('layouts.app')

@section('title', 'Voucher WiFi')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-ticket me-2" style="color:var(--primary);"></i>Voucher WiFi</h2>
        <p class="section-subtitle mb-0 mt-1">Generate & kelola voucher hotspot</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if($mikrotikConnected)
            <form action="{{ route('vouchers.sync-mikrotik') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-premium px-3 py-2" onclick="return confirm('Sinkronisasi dengan MikroTik?')">
                    <i class="fa-solid fa-rotate me-1"></i>Sync MikroTik
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

{{-- STATS --}}
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

{{-- TABLE --}}
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
                    <i class="fa-solid fa-plug me-1"></i>MikroTik off
                </span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2 align-items-center" action="{{ route('vouchers.index') }}">
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
                    <a href="{{ route('vouchers.index') }}" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-times"></i></a>
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

@push('scripts')
<script>
    const selectAll = document.getElementById('select-all');
    const checks = document.querySelectorAll('.voucher-check');
    const printBtn = document.getElementById('print-selected');

    function updatePrintBtn() {
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
@endsection
