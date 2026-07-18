<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\Mikrotik\DataCollectorService;
use Illuminate\Console\Command;

class NetworkDataCollect extends Command
{
    protected $signature = 'network:data-collect
        {--aggregate : Run aggregation on older data}
        {--prune : Prune raw data >24h and aggregated >7d}
        {--summary : Print recent summary for all routers}
        {--router= : Filter by specific router ID}
        {--interval= : Override dynamic sampling interval (minutes)}';

    protected $description = 'Auto-collect network performance metrics from all MikroTik routers';

    public function handle(): int
    {
        if ($this->option('summary')) {
            return $this->showSummary();
        }

        if ($this->option('aggregate')) {
            return $this->runAggregate();
        }

        if ($this->option('prune')) {
            return $this->runPrune();
        }

        return $this->collect();
    }

    protected function collect(): int
    {
        $interval = $this->option('interval') ? (int) $this->option('interval') : DataCollectorService::getDynamicInterval();

        $this->info("Network Data Collection — interval: {$interval}m");
        $this->newLine();

        $result = DataCollectorService::collectAll();

        $this->info("Collected: {$result['collected']} | Failed: {$result['failed']}");
        $this->newLine();

        if (! empty($result['metrics'])) {
            $this->table(
                ['Router', 'Download', 'Upload', 'Latency', 'CPU', 'Mem%', 'Conn', 'Status'],
                array_map(function ($m) {
                    $memPct = $m->memory_total > 0
                        ? round(($m->memory_used / $m->memory_total) * 100, 1)
                        : 0;

                    return [
                        $m->router->display_identity ?? 'N/A',
                        $this->formatBytes($m->bandwidth_download).'/s',
                        $this->formatBytes($m->bandwidth_upload).'/s',
                        $m->latency_idle.'ms',
                        $m->cpu_load.'%',
                        $memPct.'%',
                        $m->total_connections,
                        $m->router_status,
                    ];
                }, $result['metrics'])
            );
        }

        $this->newLine();
        $this->info('Dynamic interval next cycle: '.DataCollectorService::getDynamicInterval().'m');

        return Command::SUCCESS;
    }

    protected function runAggregate(): int
    {
        $this->info('Aggregating raw metrics into 5-minute buckets...');
        $affected = DataCollectorService::aggregate();
        $this->info("Aggregated: {$affected} buckets created/updated.");

        return Command::SUCCESS;
    }

    protected function runPrune(): int
    {
        $this->info('Pruning old data (raw >24h, aggregated >7d)...');
        $result = DataCollectorService::prune();
        $this->info("Pruned: {$result['raw_deleted']} raw, {$result['aggregated_deleted']} aggregated.");

        return Command::SUCCESS;
    }

    protected function showSummary(): int
    {
        $routerId = $this->option('router');

        $routers = $routerId
            ? MikrotikRouter::where('id', $routerId)->get()
            : MikrotikRouter::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $this->warn('No active routers found.');

            return Command::SUCCESS;
        }

        $this->info('Network Metrics Summary (Last 30 minutes)');
        $this->newLine();

        foreach ($routers as $router) {
            $summary = DataCollectorService::getRecentSummary($router->id);

            if (! $summary) {
                $this->warn("  {$router->display_identity}: No data available");

                continue;
            }

            $this->info("  {$router->display_identity}:");
            $this->line("    Bandwidth: ↓ {$this->formatBytes($summary['avg_bandwidth_download'])}/s (avg) | ↑ {$this->formatBytes($summary['avg_bandwidth_upload'])}/s (avg)");
            $this->line("    Latency: {$summary['avg_latency_idle']}ms (avg) | {$summary['max_latency_idle']}ms (max)");
            $this->line("    CPU: {$summary['avg_cpu_load']}% (avg) | {$summary['max_cpu_load']}% (max)");
            $this->line("    Connections: {$summary['avg_connections']} (avg)");
            $this->line("    Samples: {$summary['sample_count']}");
            $this->newLine();
        }

        $peaks = DataCollectorService::detectPeakPatterns($routers->first()->id);

        if (! empty($peaks)) {
            $this->info('Peak Patterns (Last 24h):');
            foreach ($peaks as $peak) {
                $this->line("  Hour {$peak['hour']}:00 — BW: {$this->formatBytes($peak['avg_total_bandwidth_bps'])}/s | Latency: {$peak['avg_latency_ms']}ms | Connections: {$peak['avg_connections']}");
            }
        }

        return Command::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
