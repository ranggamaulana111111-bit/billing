<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\SmartQos\SmartQosService;
use Illuminate\Console\Command;

class QosOptimize extends Command
{
    protected $signature = 'qos:optimize {--router=}';

    protected $description = 'Optimize QoS — sync queues, adjust CAKE params, clean orphans';

    public function handle(): int
    {
        $routers = SmartQosService::getActivePppoeRouters();

        if ($this->option('router')) {
            $routers = $routers->filter(fn (MikrotikRouter $r) => (string) $r->id === $this->option('router'));
        }

        if ($routers->isEmpty()) {
            $this->warn('Tidak ada aktif PPPoE router ditemukan.');

            return Command::SUCCESS;
        }

        $totalProvisioned = 0;
        $totalSkipped = 0;
        $totalOrphans = 0;
        $totalCpuOptimized = 0;
        $warnings = [];

        foreach ($routers as $router) {
            $routerName = $router->display_identity ?? $router->name;
            $this->line("Processing: {$routerName}...");

            $syncResult = SmartQosService::syncAllQueues($router);
            $totalProvisioned += $syncResult['provisioned'];
            $totalSkipped += $syncResult['skipped_no_qos'] + $syncResult['skipped_no_ip'];

            $cpuResult = SmartQosService::optimizeCpuQueues($router);
            $totalCpuOptimized += $cpuResult['optimized'];

            $latencyResult = SmartQosService::getHealthStats($router);
            $latency = $latencyResult['latency_ms'] ?? 0;
            $grade = SmartQosService::bufferbloatGrade($latency);

            if ($latency > 100) {
                $warnings[] = "{$routerName}: HIGH latency {$latency}ms (Grade {$grade})";
            }

            $this->info("  Queues: {$syncResult['provisioned']} provisioned | CPU optimized: {$cpuResult['optimized']} | Latency: {$latency}ms (Grade {$grade})");
        }

        $this->newLine();
        $this->info('=== Optimization Complete ===');
        $this->info("Provisioned: {$totalProvisioned} queues");
        $this->info("Skipped: {$totalSkipped} (no QoS config / no IP)");
        $this->info("CPU optimized: {$totalCpuOptimized} queues");

        if (! empty($warnings)) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($warnings as $w) {
                $this->warn("  ⚠ {$w}");
            }
        }

        return Command::SUCCESS;
    }
}
