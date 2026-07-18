<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkConfigAuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'network_config_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'mikrotik_router_id',
        'resource_type',
        'item_id',
        'item_name',
        'action',
        'before_data',
        'after_data',
        'summary',
        'status',
        'user_id',
        'api_error',
        'created_at',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'secondary',
        };
    }

    public function getActionBadgeAttribute(): string
    {
        return match ($this->action) {
            'create' => 'success',
            'update' => 'warning',
            'delete' => 'danger',
            'enable' => 'info',
            'disable' => 'secondary',
            'bulk' => 'primary',
            default => 'light',
        };
    }
}
