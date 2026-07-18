<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use App\Models\NetworkMetric;
use App\Models\NetworkMetricAggregated;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataCollectorService
{
    protected MikrotikService $mikrotik;

    protected MikrotikRouter $router;

    public function __construct(MikrotikRouter $router)
    {
        $this->router = $router;
        $this->mikrotik = new MikrotikService($router);
    }

    /**
     * Collect metrics from a single router.
     */
    public function collect(): ?NetworkMetric
    {
        $startMs = microtime(true);

        try {
            $systemResource = $this->mikrotik->getSystemResource();
            $resource = $systemResource[0] ?? $systemResource;

            if (empty($resource)) {
                return null;
            }

            $interfaces = $this->mikrotik->getInterfaces();
            $latency = $this->mikrotik->getLatency() ?? 0;
            $wanData = $this->parseWanInterfaces($interfaces);

            $totalDownload = 0;
            $totalUpload = 0;
            $parsedInterfaces = [];

            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? $iface['interface'] ?? '';
                $type = $iface['type'] ?? '';
                $running = ($iface['running'] ?? '') === 'true' || ($iface['running'] ?? '') === true;
                $disabled = ($iface['disabled'] ?? '') === 'true' || ($iface['disabled'] ?? '') === true;

                $traffic = $this->mikrotik->getInterfaceTraffic($name);
                $trafficData = $traffic[0] ?? $traffic;

                $rx = (int) ($trafficData['rx-byte-per-second'] ?? 0);
                $tx = (int) ($trafficData['tx-byte-per-second'] ?? 0);

                $totalDownload += $rx;
                $totalUpload += $tx;

                $parsedInterfaces[] = [
                    'name' => $name,
                    'type' => $type,
                    'running' => $running,
                    'disabled' => $disabled,
                    'rx_bps' => $rx,
                    'tx_bps' => $tx,
                ];
            }

            $pppActive = $this->mikrotik->getPppActive();
            $hotspotActive = $this->mikrotik->getActiveHotspotSessions();

            $totalConnections = count($pppActive) + count($hotspotActive);

            $latencyIdle = (int) round($latency);
            $latencyLoad = (int) round($latency * 1.3);

            $cpuLoad = (int) ($resource['cpu-load'] ?? $resource['cpu'] ?? 0);
            $totalMemory = (int) ($resource['total-memory'] ?? 0);
            $freeMemory = (int) ($resource['free-memory'] ?? 0);
            $usedMemory = $totalMemory - $freeMemory;

            $uptimeStr = $resource['uptime'] ?? '0d00:00:00';
            $uptimeSeconds = $this->parseUptime($uptimeStr);

            $collectDuration = round((microtime(true) - $startMs) * 1000);

            $metric = NetworkMetric::create([
                'tenant_id' => $this->router->tenant_id,
                'mikrotik_router_id' => $this->router->id,
                'collected_at' => now(),
                'bandwidth_download' => $totalDownload,
                'bandwidth_upload' => $totalUpload,
                'latency_idle' => $latencyIdle,
                'latency_load' => $latencyLoad,
                'packet_loss' => 0,
                'total_connections' => $totalConnections,
                'router_status' => 'online',
                'cpu_load' => $cpuLoad,
                'memory_used' => $usedMemory,
                'memory_total' => $totalMemory,
                'uptime_seconds' => $uptimeSeconds,
                'interfaces_data' => $parsedInterfaces,
                'wan_data' => $wanData,
                'metadata' => [
                    'collect_duration_ms' => $collectDuration,
                    'ppp_active_count' => count($pppActive),
                    'hotspot_active_count' => count($hotspotActive),
                    'router_name' => $this->router->display_identity,
                ],
            ]);

            $this->router->update([
                'status' => 'online',
                'last_seen' => now(),
                'last_connected' => now(),
            ]);

            return $metric;

        } catch (\Exception $e) {
            Log::error("DataCollector: failed for router {$this->router->name}: {$e->getMessage()}", [
                'router_id' => $this->router->id,
                'error' => $e->getMessage(),
            ]);

            $this->router->update(['status' => 'offline']);

            return null;
        }
    }

    /**
     * Collect from all active routers.
     *
     * @return array{collected: int, failed: int, metrics: array<int, NetworkMetric>}
     */
    public static function collectAll(): array
    {
        $routers = MikrotikRouter::where('is_active', true)->get();

        $collected = 0;
        $failed = 0;
        $metrics = [];

        foreach ($routers as $router) {
            $collector = new self($router);
            $metric = $collector->collect();

            if ($metric) {
                $collected++;
                $metrics[] = $metric;
            } else {
                $failed++;
            }
        }

        return [
            'collected' => $collected,
            'failed' => $failed,
            'metrics' => $metrics,
        ];
    }

    /**
     * Aggregate raw metrics into 5-minute buckets, up to 7 days.
     */
    public static function aggregate(): int
    {
        $cutoffRaw = now()->subHours(24);
        $cutoffAggregate = now()->subDays(7);
        $affected = 0;

        $routerIds = NetworkMetric::where('collected_at', '<', $cutoffRaw)
            ->where('collected_at', '>=', $cutoffAggregate)
            ->select('mikrotik_router_id', 'tenant_id')
            ->distinct()
            ->pluck('mikrotik_router_id', 'tenant_id');

        foreach ($routerIds as $tenantId => $routerId) {
            $buckets = DB::select("
                SELECT
                    DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00') as bucket,
                    MIN(collected_at) as period_start,
                    MAX(collected_at) as period_end,
                    COUNT(*) as sample_count,
                    ROUND(AVG(bandwidth_download)) as avg_bd,
                    ROUND(AVG(bandwidth_upload)) as avg_bu,
                    MAX(bandwidth_download) as max_bd,
                    MAX(bandwidth_upload) as max_bu,
                    MIN(bandwidth_download) as min_bd,
                    MIN(bandwidth_upload) as min_bu,
                    ROUND(AVG(latency_idle)) as avg_li,
                    MAX(latency_idle) as max_li,
                    ROUND(AVG(latency_load)) as avg_ll,
                    MAX(latency_load) as max_ll,
                    ROUND(AVG(packet_loss), 2) as avg_pl,
                    MAX(packet_loss) as max_pl,
                    ROUND(AVG(total_connections)) as avg_conn,
                    ROUND(AVG(cpu_load)) as avg_cpu,
                    MAX(cpu_load) as max_cpu,
                    ROUND(AVG(CASE WHEN memory_total > 0 THEN (memory_used / memory_total) * 100 ELSE 0 END), 2) as avg_mem_pct
                FROM network_metrics
                WHERE mikrotik_router_id = ? AND tenant_id = ?
                  AND collected_at < ?
                  AND collected_at >= ?
                GROUP BY DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00')
            ", [$routerId, $tenantId, $cutoffRaw, $cutoffAggregate]);

            foreach ($buckets as $bucket) {
                NetworkMetricAggregated::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'mikrotik_router_id' => $routerId,
                        'period_start' => $bucket->bucket,
                        'interval_minutes' => 5,
                    ],
                    [
                        'period_end' => $bucket->period_end,
                        'sample_count' => $bucket->sample_count,
                        'avg_bandwidth_download' => $bucket->avg_bd,
                        'avg_bandwidth_upload' => $bucket->avg_bu,
                        'max_bandwidth_download' => $bucket->max_bd,
                        'max_bandwidth_upload' => $bucket->max_bu,
                        'min_bandwidth_download' => $bucket->min_bd,
                        'min_bandwidth_upload' => $bucket->min_bu,
                        'avg_latency_idle' => $bucket->avg_li,
                        'max_latency_idle' => $bucket->max_li,
                        'avg_latency_load' => $bucket->avg_ll,
                        'max_latency_load' => $bucket->max_ll,
                        'avg_packet_loss' => $bucket->avg_pl,
                        'max_packet_loss' => $bucket->max_pl,
                        'avg_connections' => $bucket->avg_conn,
                        'avg_cpu_load' => $bucket->avg_cpu,
                        'max_cpu_load' => $bucket->max_cpu,
                        'avg_memory_usage_pct' => $bucket->avg_mem_pct,
                    ]
                );
                $affected++;
            }
        }

        return $affected;
    }

    /**
     * Prune raw data older than 24h and aggregated data older than 7d.
     */
    public static function prune(): array
    {
        $rawDeleted = NetworkMetric::where('collected_at', '<', now()->subHours(24))->delete();
        $aggDeleted = NetworkMetricAggregated::where('period_start', '<', now()->subDays(7))->delete();

        return [
            'raw_deleted' => $rawDeleted,
            'aggregated_deleted' => $aggDeleted,
        ];
    }

    /**
     * Get recent metrics summary (last 30 minutes) for a router.
     */
    public static function getRecentSummary(int $routerId, int $minutes = 30): ?array
    {
        $metrics = NetworkMetric::forRouter($routerId)
            ->since($minutes)
            ->orderByDesc('collected_at')
            ->get();

        if ($metrics->isEmpty()) {
            return null;
        }

        return [
            'router_id' => $routerId,
            'period' => "{$minutes}m",
            'sample_count' => $metrics->count(),
            'avg_bandwidth_download' => round($metrics->avg('bandwidth_download')),
            'avg_bandwidth_upload' => round($metrics->avg('bandwidth_upload')),
            'max_bandwidth_download' => $metrics->max('bandwidth_download'),
            'max_bandwidth_upload' => $metrics->max('bandwidth_upload'),
            'avg_latency_idle' => round($metrics->avg('latency_idle')),
            'max_latency_idle' => $metrics->max('latency_idle'),
            'avg_latency_load' => round($metrics->avg('latency_load')),
            'max_latency_load' => $metrics->max('latency_load'),
            'avg_packet_loss' => round($metrics->avg('packet_loss'), 2),
            'max_packet_loss' => round($metrics->max('packet_loss'), 2),
            'avg_connections' => round($metrics->avg('total_connections')),
            'avg_cpu_load' => round($metrics->avg('cpu_load')),
            'max_cpu_load' => $metrics->max('cpu_load'),
            'latest' => [
                'bandwidth_total' => $metrics->first()->bandwidth_total,
                'latency_idle' => $metrics->first()->latency_idle,
                'cpu_load' => $metrics->first()->cpu_load,
                'memory_usage_pct' => $metrics->first()->memory_usage_pct,
                'connections' => $metrics->first()->total_connections,
                'collected_at' => $metrics->first()->collected_at->toIso8601String(),
            ],
        ];
    }

    /**
     * Get historical trend (24h or 7d) from aggregated data.
     */
    public static function getHistoricalTrend(int $routerId, int $hours = 168): array
    {
        $aggregates = NetworkMetricAggregated::forRouter($routerId)
            ->recent($hours)
            ->orderBy('period_start')
            ->get();

        if ($aggregates->isEmpty()) {
            return [];
        }

        return $aggregates->map(fn ($agg) => [
            'period' => $agg->period_start->toIso8601String(),
            'avg_download' => $agg->avg_bandwidth_download,
            'avg_upload' => $agg->avg_bandwidth_upload,
            'max_download' => $agg->max_bandwidth_download,
            'max_upload' => $agg->max_bandwidth_upload,
            'avg_latency' => $agg->avg_latency_idle,
            'max_latency' => $agg->max_latency_idle,
            'avg_connections' => $agg->avg_connections,
            'avg_cpu' => $agg->avg_cpu_load,
            'samples' => $agg->sample_count,
        ])->toArray();
    }

    /**
     * Detect peak patterns in recent data.
     */
    public static function detectPeakPatterns(int $routerId): array
    {
        $hourly = DB::select('
            SELECT
                HOUR(collected_at) as hour,
                ROUND(AVG(bandwidth_download + bandwidth_upload)) as avg_total_bw,
                ROUND(AVG(latency_idle)) as avg_latency,
                ROUND(AVG(total_connections)) as avg_connections,
                COUNT(*) as samples
            FROM network_metrics
            WHERE mikrotik_router_id = ? AND collected_at >= ?
            GROUP BY HOUR(collected_at)
            ORDER BY avg_total_bw DESC
        ', [$routerId, now()->subHours(24)]);

        $peaks = array_slice($hourly, 0, 3);

        return array_map(fn ($p) => [
            'hour' => $p->hour,
            'avg_total_bandwidth_bps' => $p->avg_total_bw,
            'avg_latency_ms' => $p->avg_latency,
            'avg_connections' => $p->avg_connections,
            'samples' => $p->samples,
        ], $peaks);
    }

    /**
     * Determine dynamic sampling interval based on activity level.
     */
    public static function getDynamicInterval(): int
    {
        $recentMetrics = NetworkMetric::since(5)->count();

        if ($recentMetrics > 50) {
            return 1;
        }

        if ($recentMetrics > 20) {
            return 3;
        }

        return 5;
    }

    protected function parseWanInterfaces(array $interfaces): array
    {
        $wanPatterns = ['wan', 'pppoe', 'lte', 'sfp', 'ether1', 'ether2'];
        $wanData = [];

        foreach ($interfaces as $iface) {
            $name = strtolower($iface['name'] ?? '');
            $isWan = false;

            foreach ($wanPatterns as $pattern) {
                if (str_contains($name, $pattern)) {
                    $isWan = true;
                    break;
                }
            }

            if (! $isWan) {
                continue;
            }

            $running = ($iface['running'] ?? '') === 'true' || ($iface['running'] ?? '') === true;
            $disabled = ($iface['disabled'] ?? '') === 'true' || ($iface['disabled'] ?? '') === true;

            $status = 'DOWN';
            if ($running && ! $disabled) {
                $status = 'UP';
            } elseif ($running && $disabled) {
                $status = 'DEGRADED';
            }

            $traffic = $this->mikrotik->getInterfaceTraffic($iface['name']);
            $trafficData = $traffic[0] ?? $traffic;

            $wanData[] = [
                'id' => $iface['name'],
                'type' => $iface['type'] ?? '',
                'status' => $status,
                'usage_in' => (int) ($trafficData['rx-byte-per-second'] ?? 0),
                'usage_out' => (int) ($trafficData['tx-byte-per-second'] ?? 0),
                'latency' => 0,
                'packet_loss' => 0,
            ];
        }

        return $wanData;
    }

    protected function parseUptime(string $uptime): int
    {
        $total = 0;
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $total += (int) $m[1] * 86400;
        }
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $total += (int) $m[1] * 3600;
        }
        if (preg_match('/(\d+)m/', $uptime, $m)) {
            $total += (int) $m[1] * 60;
        }
        if (preg_match('/(\d+)s/', $uptime, $m)) {
            $total += (int) $m[1];
        }

        return $total;
    }
}
