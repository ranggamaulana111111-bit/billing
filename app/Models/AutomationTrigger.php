<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationTrigger extends Model
{
    use BelongsToTenant;

    protected $table = 'noc_automation_triggers';

    protected $fillable = [
        'tenant_id',
        'name',
        'event_type',
        'event_config',
        'automation_job_id',
        'is_active',
        'fire_count',
        'last_fired_at',
    ];

    protected $casts = [
        'event_config' => 'array',
        'is_active' => 'boolean',
        'fire_count' => 'integer',
        'last_fired_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(AutomationJob::class, 'automation_job_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    // ── Helpers ──

    public function getEventTypeBadgeAttribute(): string
    {
        return match ($this->event_type) {
            'schedule' => 'secondary',
            'device_status' => 'warning',
            'monitoring_alert' => 'danger',
            'config_change' => 'info',
            'internal_event' => 'primary',
            default => 'light',
        };
    }
}
