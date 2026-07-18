<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\SmartQos\SmartQosService;
use Illuminate\Console\Command;

class QosSync extends Command
{
    protected $signature = 'qos:sync {--router= : Specific router ID}';

    protected $description = 'Sync all active customers to MikroTik Simple Queues';

    public function handle(): int
    {
        $routerId = $this->option('router');

        $routers = $routerId
            ? MikrotikRouter::where('id', $routerId)->get()
            : SmartQosService::getActivePppoeRouters();

        if ($routers->isEmpty()) {
            $this->warn('Tidak ada router aktif ditemukan.');

            return Command::SUCCESS;
        }

        $this->info('SmartQos Sync — Prosesing '.$routers->count().' router(s)');
        $this->newLine();

        $totalProvisioned = 0;
        $totalSkipped = 0;
        $totalRemoved = 0;

        foreach ($routers as $router) {
            $name = $router->display_identity ?? $router->name;
            $this->line("  {$name}...");

            $result = SmartQosService::syncAllQueues($router);
            $totalProvisioned += $result['provisioned'];
            $totalSkipped += $result['skipped'];
            $totalRemoved += $result['removed'];

            $this->line("    Provisioned: {$result['provisioned']} | Skipped: {$result['skipped']} | Orphans removed: {$result['removed']}");
        }

        $this->newLine();
        $this->info("Sync selesai: {$totalProvisioned} provisioned, {$totalSkipped} skipped, {$totalRemoved} removed");

        return Command::SUCCESS;
    }
}
