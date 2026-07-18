<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationJobLog extends Model
{
    use BelongsToTenant;

    protected $table = 'noc_automation_job_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'automation_job_id',
        'status',
        'attempt',
        'message',
        'error',
        'duration_ms',
        'result_data',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'duration_ms' => 'integer',
        'result_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(AutomationJob::class, 'automation_job_id');
    }

    // ── Helpers ──

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'running' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'timeout' => 'warning',
            'retrying' => 'info',
            default => 'secondary',
        };
    }

    public function getDurationHumanAttribute(): ?string
    {
        if (! $this->duration_ms) {
            return null;
        }

        $ms = $this->duration_ms;
        if ($ms < 1000) {
            return "{$ms}ms";
        }

        return round($ms / 1000, 1).'s';
    }
}
