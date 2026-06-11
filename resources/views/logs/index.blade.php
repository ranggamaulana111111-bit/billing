@extends('layouts.app')

@section('title', 'Log Sistem')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-terminal me-2" style="color:var(--primary);"></i>Log Sistem</h2>
        <p class="section-subtitle mb-0 mt-1">Riwayat aktivitas sistem secara sistematis</p>
    </div>
</div>

{{-- FILTERS --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Kata kunci..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Tipe Aksi</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
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
                <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-rotate me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- LOG TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Riwayat Aktivitas</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $logs->total() }}</span>
        </div>
        <small class="text-muted">Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th style="width:160px;">Waktu</th>
                        <th style="width:120px;">User</th>
                        <th style="width:140px;">Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-muted" style="font-size:0.75rem;">{{ $loop->iteration + ($logs->currentPage() - 1) * $logs->perPage() }}</td>
                            <td>
                                <span style="font-size:0.8rem;font-weight:500;">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                <br>
                                <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <span style="font-size:0.8rem;">{{ $log->user?->name ?? '—' }}</span>
                            </td>
                            <td>
                                @php
                                    $badge = match($log->action) {
                                        'Pembayaran', 'Pembayaran Online' => ['bg' => '#f0fdf4', 'text' => '#059669'],
                                        'Reminder WA' => ['bg' => '#fef3c7', 'text' => '#d97706'],
                                        'Login', 'Logout' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
                                        default => ['bg' => '#eef2ff', 'text' => '#475569'],
                                    };
                                @endphp
                                <span class="badge badge-premium" style="background:{{ $badge['bg'] }};color:{{ $badge['text'] }};">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem;">{{ $log->details }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-circle-xmark" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada aktivitas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
