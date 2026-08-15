@extends('layouts.app')

@section('title', 'Automation Engine — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gears me-2" style="color:var(--primary);"></i>Automation Engine</h2>
        <p class="section-subtitle mb-0 mt-1">Pusat otomasi PROVISION NOC Control Center</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('noc.automation.trigger-scheduler') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-warning px-3 py-2" title="Run Scheduler Now">
                <i class="fa-solid fa-clock me-1"></i>Scheduler Tick
            </button>
        </form>
        <form method="POST" action="{{ route('noc.automation.trigger-worker') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-info px-3 py-2" title="Process Queue Now">
                <i class="fa-solid fa-play me-1"></i>Run Worker
            </button>
        </form>
        <a href="{{ route('noc.automation.create') }}" class="btn btn-primary px-3 py-2">
            <i class="fa-solid fa-plus me-1"></i>New Job
        </a>
    </div>
</div>

{{-- ═══ STATS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Total Jobs</div>
                <h4 class="mb-0 fw-bold">{{ number_format($stats['total']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Idle</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-secondary);">{{ $stats['idle'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Queued</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-info);">{{ $stats['queued'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Running</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-primary);">{{ $stats['running'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Completed</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-success);">{{ $stats['completed'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="text-muted mb-1" style="font-size:0.75rem;">Failed</div>
                <h4 class="mb-0 fw-bold" style="color:var(--bs-danger);">{{ $stats['failed'] }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- ═══ RECENT JOBS ═══ --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check me-1"></i> Recent Jobs</h6>
                <a href="{{ route('noc.automation.jobs') }}" style="font-size:0.8rem;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr><th>Name</th><th>Type</th><th>Status</th><th>Schedule</th><th>Priority</th><th></th></tr>

                        <tbody>
                            @forelse($jobs as $job)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('noc.automation.show', $job->id) }}">{{ Str::limit($job->name, 30) }}</a>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $job->type }}</span></td>
                                <td><span class="badge bg-{{ $job->status_badge }}">{{ $job->status }}</span></td>
                                <td style="font-size:0.78rem;">
                                    {{ $job->schedule_type }}
                                    @if($job->next_run_at) <br><small class="text-muted">Next: {{ $job->next_run }}</small>@endif
                                </td>
                                <td><span class="badge bg-secondary">{{ $job->priority }}</span></td>
                                <td>
                                    @if($job->status === 'idle' || $job->status === 'completed')
                                    <form method="POST" action="{{ route('noc.automation.dispatch', $job->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success py-0" title="Run Now"><i class="fa-solid fa-play"></i></button>
                                    </form>
                                    @elseif($job->status === 'running' || $job->status === 'queued')
                                    <form method="POST" action="{{ route('noc.automation.cancel', $job->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Cancel"><i class="fa-solid fa-stop"></i></button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No jobs configured yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ RECENT ACTIVITY ═══ --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> Activity Log</h6>
                <a href="{{ route('noc.automation.logs') }}" style="font-size:0.8rem;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentLogs as $log)
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-{{ $log->status_badge }} me-1">{{ $log->status }}</span>
                                <span class="fw-semibold" style="font-size:0.82rem;">{{ Str::limit($log->job->name ?? 'Deleted', 25) }}</span>
                                <small class="text-muted">attempt {{ $log->attempt }}</small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">{{ $log->started_at->diffForHumans() }}</small>
                                @if($log->duration_ms !== null)
                                <div><small class="text-muted">{{ $log->duration_human }}</small></div>
                                @endif
                            </div>
                        </div>
                        @if($log->error)
                        <div class="mt-1"><small class="text-danger" style="font-size:0.75rem;">{{ Str::limit($log->error, 80) }}</small></div>
                        @endif
                    </div>
                    @empty
                    <div class="text-center text-muted py-4">No activity yet</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

