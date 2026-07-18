<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouterosSyncLog extends Model
{
    use BelongsToTenant;

    protected $table = 'routeros_sync_logs';

    protected $fillable = [
        'tenant_id',
        'mikrotik_router_id',
        'user_id',
        'sync_type',
        'modules_synced',
        'total_items',
        'new_items',
        'updated_items',
        'deleted_items',
        'conflict_items',
        'duration_ms',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'modules_synced' => 'array',
        'total_items' => 'integer',
        'new_items' => 'integer',
        'updated_items' => 'integer',
        'deleted_items' => 'integer',
        'conflict_items' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncedConfigs(): HasMany
    {
        return $this->hasMany(RouterosSyncedConfig::class, 'sync_log_id');
    }

    // ── Scopes ──

    public function scopeForRouter($query, int $routerId)
    {
        return $query->where('mikrotik_router_id', $routerId);
    }

    public function scopeManual($query)
    {
        return $query->where('sync_type', 'manual');
    }

    public function scopeScheduled($query)
    {
        return $query->where('sync_type', 'scheduled');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    // ── Helpers ──

    public function getDurationHumanAttribute(): string
    {
        $ms = $this->duration_ms;
        if ($ms < 1000) {
            return "{$ms}ms";
        }

        return round($ms / 1000, 1).'s';
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'partial' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    public function markCompleted(string $status = 'success'): void
    {
        $this->update([
            'status' => $status,
            'completed_at' => now(),
            'duration_ms' => (int) ($this->started_at->diffInMilliseconds(now())),
        ]);
    }
}
