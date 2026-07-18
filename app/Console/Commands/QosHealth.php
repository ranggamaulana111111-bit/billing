<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\SmartQos\SmartQosService;
use Illuminate\Console\Command;

class QosHealth extends Command
{
    protected $signature = 'qos:health
        {--router= : Specific router ID}
        {--json : Output JSON}';

    protected $description = 'QoS health check — queue count, latency, CAKE status';

    public function handle(): int
    {
        $routerId = $this->option('router');
        $jsonOutput = $this->option('json');

        $routers = $routerId
            ? MikrotikRouter::where('id', $routerId)->get()
            : SmartQosService::getActivePppoeRouters();

        if ($routers->isEmpty()) {
            $this->warn('Tidak ada router aktif ditemukan.');

            return Command::SUCCESS;
        }

        $results = [];

        foreach ($routers as $router) {
            $stats = SmartQosService::getHealthStats($router);
            $grade = SmartQosService::bufferbloatGrade($stats['latency_ms']);
            $stats['grade'] = $grade;
            $results[] = $stats;
        }

        if ($jsonOutput) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info('SmartQos Health Check');
        $this->newLine();

        foreach ($results as $stats) {
            $cakeIcon = $stats['cake_active'] ? '<info>ACTIVE</info>' : '<error>MISSING</error>';
            $this->line("  Router: {$stats['router_name']}");
            $this->line("    CAKE: {$cakeIcon}");
            $this->line("    Queues: {$stats['active_queues']} active / {$stats['total_queues']} total");
            $this->line("    Latency: {$stats['latency_ms']}ms — Grade: <info>{$stats['grade']}</info>");
            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
