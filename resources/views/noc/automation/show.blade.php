@extends('layouts.app')

@section('title', $job->name . ' — Automation — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.automation.index') }}">Automation</a></li>
                <li class="breadcrumb-item active">{{ $job->name }}</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <span class="badge bg-{{ $job->status_badge }} me-2">{{ $job->status }}</span>
            {{ $job->name }}
        </h2>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        @if(in_array($job->status, ['idle', 'completed']))
        <form method="POST" action="{{ route('noc.automation.dispatch', $job->id) }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-success px-3"><i class="fa-solid fa-play me-1"></i>Run Now</button>
        </form>
        @endif
        @if($job->status === 'failed')
        <form method="POST" action="{{ route('noc.automation.retry', $job->id) }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-warning px-3"><i class="fa-solid fa-rotate me-1"></i>Retry</button>
        </form>
        @endif
        @if(in_array($job->status, ['running', 'queued']))
        <form method="POST" action="{{ route('noc.automation.cancel', $job->id) }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-danger px-3"><i class="fa-solid fa-stop me-1"></i>Cancel</button>
        </form>
        @endif
        @if(!in_array($job->status, ['idle']))
        <form method="POST" action="{{ route('noc.automation.reset', $job->id) }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-rotate-left me-1"></i>Reset</button>
        </form>
        @endif
        <a href="{{ route('noc.automation.edit', $job->id) }}" class="btn btn-outline-primary px-3"><i class="fa-solid fa-pen me-1"></i>Edit</a>
    </div>
</div>

<div class="row g-4">
    {{-- ═══ JOB INFO ═══ --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0"><i class="fa-solid fa-circle-info me-1"></i> Job Details</h6></div>
            <div class="card-body" style="font-size:0.85rem;">
                
                    <tr><td class="text-muted" style="width:140px;">Type</td><td><span class="badge bg-light text-dark">{{ $job->type }}</span></td></tr>
                    <tr><td class="text-muted">Schedule</td><td>{{ $job->schedule_type }} {{ $job->schedule_config ? "({$job->schedule_config})" : '' }}</td></tr>
                    <tr><td class="text-muted">Priority</td><td>{{ $job->priority }} / 10</td></tr>
                    <tr><td class="text-muted">Max Attempts</td><td>{{ $job->max_attempts }}</td></tr>
                    <tr><td class="text-muted">Timeout</td><td>{{ $job->timeout_seconds }}s</td></tr>
                    <tr><td class="text-muted">Active</td><td>{{ $job->is_active ? 'Yes' : 'No' }}</td></tr>
                    <tr><td class="text-muted">Last Run</td><td>{{ $job->last_run_at?->diffForHumans() ?? 'Never' }}</td></tr>
                    <tr><td class="text-muted">Next Run</td><td>{{ $job->next_run ?? 'N/A' }}</td></tr>
                    <tr><td class="text-muted">Created By</td><td>{{ $job->creator->name ?? 'System' }}</td></tr>
                    @if($job->last_error)
                    <tr><td class="text-muted">Last Error</td><td class="text-danger">{{ Str::limit($job->last_error, 100) }}</td></tr>
                    @endif
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>

        @if($job->parameters)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0"><i class="fa-solid fa-code me-1"></i> Parameters</h6></div>
            <div class="card-body p-0">
                <pre class="p-3 mb-0" style="font-size:0.75rem; background:#1e1e2e; color:#cdd6f4; border-radius:0 0 0.5rem 0;">{{ json_encode($job->parameters, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
        @endif
    </div>

    {{-- ═══ RECENT LOGS ═══ --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> Execution History</h6></div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr><th>#</th><th>Status</th><th>Attempt</th><th>Duration</th><th>When</th><th>Error</th></tr>

                        <tbody>
                            @forelse($job->logs as $log)
                            <tr>
                                <td class="text-muted">{{ $log->id }}</td>
                                <td><span class="badge bg-{{ $log->status_badge }}">{{ $log->status }}</span></td>
                                <td>{{ $log->attempt }}</td>
                                <td>{{ $log->duration_human ?? '—' }}</td>
                                <td class="text-muted">{{ $log->started_at->diffForHumans() }}</td>
                                <td class="text-danger" style="max-width:200px;">{{ $log->error ? Str::limit($log->error, 60) : '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No execution history</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

