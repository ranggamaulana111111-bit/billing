<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigVersion extends Model
{
    use BelongsToTenant;

    protected $table = 'config_versions';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'mikrotik_router_id',
        'module',
        'item_id',
        'item_name',
        'version',
        'config_data',
        'checksum',
        'change_source',
        'user_id',
        'change_summary',
        'diff_from_previous',
        'sync_log_id',
        'created_at',
    ];

    protected $casts = [
        'config_data' => 'array',
        'diff_from_previous' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
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

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(RouterosSyncLog::class, 'sync_log_id');
    }

    // ── Scopes ──

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForRouter($query, int $routerId)
    {
        return $query->where('mikrotik_router_id', $routerId);
    }

    public function scopeForItem($query, int $routerId, string $module, string $itemId)
    {
        return $query->where('mikrotik_router_id', $routerId)
            ->where('module', $module)
            ->where('item_id', $itemId);
    }

    public function scopeLatestVersion($query)
    {
        return $query->orderByDesc('version');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('change_source', $source);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // ── Helpers ──

    public function getChangeSourceBadgeAttribute(): string
    {
        return match ($this->change_source) {
            'sync' => 'info',
            'manual' => 'warning',
            'api' => 'primary',
            'script' => 'secondary',
            default => 'light',
        };
    }

    public function getChangeSourceLabelAttribute(): string
    {
        return match ($this->change_source) {
            'sync' => 'Sync',
            'manual' => 'Manual',
            'api' => 'API',
            'script' => 'Script',
            default => ucfirst($this->change_source),
        };
    }
}
