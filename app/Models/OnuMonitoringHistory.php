<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnuMonitoringHistory extends Model
{
    protected $table = 'onu_monitoring_history';

    protected $fillable = [
        'tenant_id',
        'onu_id',
        'rx_power',
        'tx_power',
        'temperature',
        'voltage',
        'bias_current',
        'status',
        'los_detected',
        'dying_gasp_detected',
        'auth_failed',
        'rogue_detected',
        'uptime',
        'download_bytes',
        'upload_bytes',
        'restart_count',
    ];

    protected $casts = [
        'rx_power' => 'float',
        'tx_power' => 'float',
        'temperature' => 'float',
        'voltage' => 'float',
        'bias_current' => 'float',
        'los_detected' => 'boolean',
        'dying_gasp_detected' => 'boolean',
        'auth_failed' => 'boolean',
        'rogue_detected' => 'boolean',
        'uptime' => 'integer',
        'download_bytes' => 'integer',
        'upload_bytes' => 'integer',
        'restart_count' => 'integer',
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
