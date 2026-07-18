<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkMetricAggregated extends Model
{
    use BelongsToTenant;

    protected $table = 'network_metrics_aggregated';

    protected $fillable = [
        'tenant_id', 'mikrotik_router_id', 'period_start', 'period_end',
        'interval_minutes', 'sample_count',
        'avg_bandwidth_download', 'avg_bandwidth_upload',
        'max_bandwidth_download', 'max_bandwidth_upload',
        'min_bandwidth_download', 'min_bandwidth_upload',
        'avg_latency_idle', 'max_latency_idle',
        'avg_latency_load', 'max_latency_load',
        'avg_packet_loss', 'max_packet_loss', 'avg_connections',
        'avg_cpu_load', 'max_cpu_load', 'avg_memory_usage_pct',
        'interfaces_summary', 'wan_summary',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'interval_minutes' => 'integer',
            'sample_count' => 'integer',
            'interfaces_summary' => 'array',
            'wan_summary' => 'array',
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

    public function scopeRecent($query, int $hours = 168)
    {
        return $query->where('period_start', '>=', now()->subHours($hours));
    }
}
