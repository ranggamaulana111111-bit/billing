<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PingResult extends Model
{
    protected $table = 'ping_results';

    protected $fillable = [
        'tenant_id',
        'target_host',
        'target_label',
        'latency_ms',
        'jitter_ms',
        'packet_loss_percent',
        'response_time_ms',
        'status',
        'onu_id',
    ];

    protected $casts = [
        'latency_ms' => 'float',
        'jitter_ms' => 'float',
        'packet_loss_percent' => 'float',
        'response_time_ms' => 'integer',
    ];

    public function onu(): BelongsTo
    {
        return $this->belongsTo(Onu::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
