@extends('layouts.app')

@section('title', 'Sync Logs — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>Sync Logs</h2>
        <p class="section-subtitle mb-0 mt-1">Riwayat sinkronisasi konfigurasi RouterOS</p>
    </div>
    <a href="{{ route('noc.sync.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Router</label>
                <select name="router_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                            {{ $router->display_identity }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Tipe</label>
                <select name="sync_type" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="manual" {{ request('sync_type') === 'manual' ? 'selected' : '' }}>Manual</option>
                    <option value="scheduled" {{ request('sync_type') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Dari</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Sampai</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('noc.sync.logs') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ LOG TABLE ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th style="font-size:0.78rem;">Time</th>
                        <th style="font-size:0.78rem;">Router</th>
                        <th style="font-size:0.78rem;">Type</th>
                        <th style="font-size:0.78rem;">User</th>
                        <th style="font-size:0.78rem;">Status</th>
                        <th style="font-size:0.78rem;" class="text-end">Total</th>
                        <th style="font-size:0.78rem;" class="text-end">New</th>
                        <th style="font-size:0.78rem;" class="text-end">Updated</th>
                        <th style="font-size:0.78rem;" class="text-end">Deleted</th>
                        <th style="font-size:0.78rem;" class="text-end">Conflicts</th>
                        <th style="font-size:0.78rem;" class="text-end">Duration</th>
                        <th style="font-size:0.78rem;">Error</th>
                    </tr>

                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="font-size:0.8rem;">
                            {{ $log->started_at->format('d M Y') }}
                            <br><small class="text-muted">{{ $log->started_at->format('H:i:s') }}</small>
                        </td>
                        <td>
                            <span class="fw-semibold" style="font-size:0.85rem;">{{ $log->router->display_identity ?? '—' }}</span>
                            @if($log->router)
                                <br><code style="font-size:0.72rem;">{{ $log->router->host }}</code>
                            @endif
                        </td>
                        <td>
                            @if($log->sync_type === 'manual')
                                <span class="badge bg-info">Manual</span>
                            @else
                                <span class="badge bg-secondary">Scheduled</span>
                            @endif
                        </td>
                        <td style="font-size:0.8rem;">{{ $log->user->name ?? 'System' }}</td>
                        <td>
                            <span class="badge bg-{{ $log->status_badge_color }}">{{ ucfirst($log->status) }}</span>
                        </td>
                        <td class="text-end" style="font-size:0.8rem;">{{ $log->total_items }}</td>
                        <td class="text-end" style="font-size:0.8rem;">
                            @if($log->new_items > 0)
                                <span class="text-success fw-bold">+{{ $log->new_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end" style="font-size:0.8rem;">
                            @if($log->updated_items > 0)
                                <span class="text-warning fw-bold">~{{ $log->updated_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end" style="font-size:0.8rem;">
                            @if($log->deleted_items > 0)
                                <span class="text-danger fw-bold">-{{ $log->deleted_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end" style="font-size:0.8rem;">
                            @if($log->conflict_items > 0)
                                <span class="text-danger fw-bold">{{ $log->conflict_items }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end" style="font-size:0.8rem;">{{ $log->duration_human }}</td>
                        <td style="font-size:0.78rem;">
                            @if($log->error_message)
                                <span class="text-danger" title="{{ $log->error_message }}">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 d-block opacity-25"></i>
                            Belum ada riwayat sinkronisasi.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-transparent">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection

