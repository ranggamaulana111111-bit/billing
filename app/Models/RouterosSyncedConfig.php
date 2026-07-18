<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterosSyncedConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'routeros_synced_configs';

    protected $fillable = [
        'tenant_id',
        'mikrotik_router_id',
        'module',
        'item_id',
        'item_name',
        'config_data',
        'checksum',
        'sync_log_id',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'config_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    // ── Relationships ──

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
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

    public function scopeForRouterModule($query, int $routerId, string $module)
    {
        return $query->where('mikrotik_router_id', $routerId)->where('module', $module);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeConflicts($query)
    {
        return $query->where('status', 'conflict');
    }

    public function scopeDeleted($query)
    {
        return $query->where('status', 'deleted');
    }

    // ── Helpers ──

    public static function computeChecksum(array $configData): string
    {
        return hash('sha256', json_encode($configData, JSON_THROW_ON_ERROR));
    }

    public function hasChanged(array $newConfigData): bool
    {
        return $this->checksum !== self::computeChecksum($newConfigData);
    }
}
