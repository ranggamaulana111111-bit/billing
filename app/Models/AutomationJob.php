<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationJob extends Model
{
    use BelongsToTenant;

    protected $table = 'noc_automation_jobs';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'parameters',
        'schedule_type',
        'schedule_config',
        'priority',
        'max_attempts',
        'timeout_seconds',
        'is_active',
        'status',
        'current_attempt',
        'last_error',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'priority' => 'integer',
        'max_attempts' => 'integer',
        'timeout_seconds' => 'integer',
        'is_active' => 'boolean',
        'current_attempt' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationJobLog::class, 'automation_job_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(AutomationTrigger::class, 'automation_job_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'idle')
            ->where('next_run_at', '<=', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ── Helpers ──

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'idle' => 'secondary',
            'queued' => 'info',
            'running' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'dark',
            default => 'light',
        };
    }

    public function isRetryable(): bool
    {
        return $this->current_attempt < $this->max_attempts;
    }

    public function getNextRunAttribute(): ?string
    {
        if (! $this->next_run_at) {
            return null;
        }

        return $this->next_run_at->diffForHumans();
    }
}
