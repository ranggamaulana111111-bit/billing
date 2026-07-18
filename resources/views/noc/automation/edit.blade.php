@extends('layouts.app')

@section('title', 'Edit ' . $job->name . ' — Automation — NOC')

@section('content')
<div class="page-header mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
            <li class="breadcrumb-item"><a href="{{ route('noc.automation.index') }}">Automation</a></li>
            <li class="breadcrumb-item"><a href="{{ route('noc.automation.show', $job->id) }}">{{ $job->name }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
    <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit Job</h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('noc.automation.update', $job->id) }}">
            @csrf
            @method('PUT')
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Job Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $job->name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <input type="text" name="type" class="form-control" value="{{ old('type', $job->type) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Schedule Type <span class="text-danger">*</span></label>
                            <select name="schedule_type" class="form-select" required>
                                @foreach(['manual' => 'Manual (on-demand)', 'interval' => 'Interval', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'cron' => 'Cron Expression'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('schedule_type', $job->schedule_type) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Schedule Config</label>
                            <input type="text" name="schedule_config" class="form-control" value="{{ old('schedule_config', $job->schedule_config) }}">
                            <small class="text-muted">Interval: 30m / 1h / 2d | Daily: 08:00 | Weekly: 08:00,1,3,5 | Monthly: 08:00,15 | Cron: */15 * * * *</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority (1=Highest)</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', $job->priority) }}" min="1" max="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Attempts</label>
                            <input type="number" name="max_attempts" class="form-control" value="{{ old('max_attempts', $job->max_attempts) }}" min="1" max="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Timeout (seconds)</label>
                            <input type="number" name="timeout_seconds" class="form-control" value="{{ old('timeout_seconds', $job->timeout_seconds) }}" min="10" max="3600">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Parameters (JSON)</label>
                            <textarea name="parameters" class="form-control font-monospace" rows="4">{{ old('parameters', is_array($job->parameters) ? json_encode($job->parameters, JSON_PRETTY_PRINT) : $job->parameters) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ old('is_active', $job->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save me-1"></i>Update Job</button>
                    <a href="{{ route('noc.automation.show', $job->id) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
