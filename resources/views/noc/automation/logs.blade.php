@extends('layouts.app')

@section('title', 'Automation Logs — NOC')

@section('content')
<div class="page-header mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
            <li class="breadcrumb-item"><a href="{{ route('noc.automation.index') }}">Automation</a></li>
            <li class="breadcrumb-item active">Logs</li>
        </ol>
    </nav>
    <h2 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>Automation Logs</h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>#</th>
                        <th>Job</th>
                        <th>Status</th>
                        <th>Attempt</th>
                        <th>Duration</th>
                        <th>Started</th>
                        <th>Error</th>
                    </tr>

                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->id }}</td>
                        <td class="fw-semibold">
                            @if($log->job)
                            <a href="{{ route('noc.automation.show', $log->job_id) }}">{{ Str::limit($log->job->name, 35) }}</a>
                            @else
                            <span class="text-muted">Deleted</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $log->status_badge }}">{{ $log->status }}</span></td>
                        <td>{{ $log->attempt }}</td>
                        <td>{{ $log->duration_human ?? '—' }}</td>
                        <td class="text-muted">{{ $log->started_at->diffForHumans() }}</td>
                        <td class="text-danger" style="max-width:250px;">{{ $log->error ? Str::limit($log->error, 80) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No logs recorded yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $logs->links() }}</div>
    @endif
</div>
@endsection

