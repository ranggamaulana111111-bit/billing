<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterfaceChangeLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'mikrotik_router_id',
        'interface_name',
        'change_type',
        'old_value',
        'new_value',
        'status',
        'message',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    /**
     * Log a configuration change.
     */
    public static function logChange(
        int $routerId,
        string $interfaceName,
        string $changeType,
        ?array $oldValue,
        ?array $newValue,
        string $status,
        ?string $message = null,
    ): self {
        return static::create([
            'tenant_id' => auth()->user()->tenant_id ?? 0,
            'user_id' => auth()->id(),
            'mikrotik_router_id' => $routerId,
            'interface_name' => $interfaceName,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
