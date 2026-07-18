<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikInterfaceMetadata extends Model
{
    protected $fillable = [
        'tenant_id',
        'mikrotik_router_id',
        'interface_name',
        'alias',
        'tags',
        'notes',
        'is_monitored',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_monitored' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    /**
     * Scope: find metadata by router and interface name.
     */
    public function scopeForInterface($query, int $routerId, string $interfaceName)
    {
        return $query->where('mikrotik_router_id', $routerId)
            ->where('interface_name', $interfaceName);
    }

    /**
     * Scope: find by tag.
     */
    public function scopeByTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
