@extends('layouts.app')

@section('title', 'Automation Jobs — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-list-check me-2" style="color:var(--primary);"></i>Automation Jobs</h2>
    </div>
    <a href="{{ route('noc.automation.create') }}" class="btn btn-primary px-3 py-2">
        <i class="fa-solid fa-plus me-1"></i>New Job
    </a>
</div>

{{-- ═══ FILTERS ═══ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Job name or type..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($types as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-filter"></i></button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('noc.automation.jobs') }}" class="btn btn-sm btn-outline-secondary w-100"><i class="fa-solid fa-xmark"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ JOBS TABLE ═══ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
            
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Schedule</th>
                        <th>Priority</th>
                        <th>Attempts</th>
                        <th>Last Run</th>
                        <th></th>
                    </tr>

                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td class="text-muted">{{ $job->id }}</td>
                        <td class="fw-semibold">
                            <a href="{{ route('noc.automation.show', $job->id) }}">{{ Str::limit($job->name, 35) }}</a>
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $job->type }}</span></td>
                        <td><span class="badge bg-{{ $job->status_badge }}">{{ $job->status }}</span></td>
                        <td style="font-size:0.78rem;">
                            {{ $job->schedule_type }}
                            @if($job->schedule_config) <small class="text-muted">({{ $job->schedule_config }})</small>@endif
                            @if($job->next_run_at) <br><small class="text-muted">Next: {{ $job->next_run }}</small>@endif
                        </td>
                        <td><span class="badge bg-secondary">{{ $job->priority }}</span></td>
                        <td>{{ $job->current_attempt }}/{{ $job->max_attempts }}</td>
                        <td class="text-muted">{{ $job->last_run_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('noc.automation.show', $job->id) }}" class="btn btn-outline-primary py-0"><i class="fa-solid fa-eye"></i></a>
                                <a href="{{ route('noc.automation.edit', $job->id) }}" class="btn btn-outline-secondary py-0"><i class="fa-solid fa-pen"></i></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No jobs found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($jobs->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $jobs->withQueryString()->links() }}</div>
    @endif
</div>
@endsection

