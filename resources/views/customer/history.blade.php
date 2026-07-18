@extends('layouts.app')
@section('title', 'Riwayat Pelanggan')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>Riwayat Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">Log aktivitas terkait pelanggan — tambah, edit, suspend, aktivasi</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-list me-1"></i>Semua Pelanggan
        </a>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Riwayat Aktivitas</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $logs->total() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Waktu</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                        <th>Oleh</th>
                    </tr>

                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td><small class="text-muted">{{ $log->created_at->format('d/m/Y H:i') }}</small></td>
                            <td>
                                @if(str_contains($log->action, 'Tambah'))
                                    <span class="badge" style="background:#f0fdf4;color:#059669;"><i class="fa-solid fa-plus me-1"></i>{{ $log->action }}</span>
                                @elseif(str_contains($log->action, 'Hapus'))
                                    <span class="badge" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-trash me-1"></i>{{ $log->action }}</span>
                                @elseif(str_contains($log->action, 'Isolir') || str_contains($log->action, 'Suspend'))
                                    <span class="badge" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-pause me-1"></i>{{ $log->action }}</span>
                                @elseif(str_contains($log->action, 'Aktifkan') || str_contains($log->action, 'Activate'))
                                    <span class="badge" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-play me-1"></i>{{ $log->action }}</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#475569;"><i class="fa-solid fa-pen me-1"></i>{{ $log->action }}</span>
                                @endif
                            </td>
                            <td><small>{{ $log->details ?? '-' }}</small></td>
                            <td><small class="text-muted">{{ $log->user->name ?? '-' }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada riwayat aktivitas pelanggan
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
