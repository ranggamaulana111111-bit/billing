<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkMetric extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'mikrotik_router_id', 'collected_at',
        'bandwidth_download', 'bandwidth_upload',
        'latency_idle', 'latency_load', 'packet_loss', 'total_connections',
        'router_status', 'cpu_load', 'memory_used', 'memory_total', 'uptime_seconds',
        'interfaces_data', 'wan_data', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'bandwidth_download' => 'integer',
            'bandwidth_upload' => 'integer',
            'latency_idle' => 'integer',
            'latency_load' => 'integer',
            'packet_loss' => 'decimal:2',
            'total_connections' => 'integer',
            'cpu_load' => 'integer',
            'memory_used' => 'integer',
            'memory_total' => 'integer',
            'uptime_seconds' => 'integer',
            'interfaces_data' => 'array',
            'wan_data' => 'array',
            'metadata' => 'array',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function scopeForRouter($query, int $routerId)
    {
        return $query->where('mikrotik_router_id', $routerId);
    }

    public function scopeSince($query, $minutes)
    {
        return $query->where('collected_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('collected_at', [$from, $to]);
    }

    public function getMemoryUsagePctAttribute(): float
    {
        return $this->memory_total > 0
            ? round(($this->memory_used / $this->memory_total) * 100, 1)
            : 0;
    }

    public function getBandwidthTotalAttribute(): int
    {
        return $this->bandwidth_download + $this->bandwidth_upload;
    }
}
