<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\SmartQos\SmartQosService;
use Illuminate\Console\Command;

class QosSetup extends Command
{
    protected $signature = 'qos:setup {--router= : Specific router ID}';

    protected $description = 'Setup CAKE queue type on all active PPPoE routers';

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

        $this->info('SmartQos Setup — Creating CAKE queue type on '.$routers->count().' router(s)');
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($routers as $router) {
            $name = $router->display_identity ?? $router->name;
            $this->line("  {$name}...");

            if (SmartQosService::ensureCakeQueueType($router)) {
                $this->line('    <info>OK</info> — cake-smartqos created/found');
                $success++;
            } else {
                $this->error('    FAILED — could not create queue type');
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Setup selesai: {$success} berhasil, {$failed} gagal");

        return Command::SUCCESS;
    }
}
