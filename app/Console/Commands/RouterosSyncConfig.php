<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\Mikrotik\Sync\ConfigSyncModuleRegistry;
use App\Services\Mikrotik\Sync\RouterosConfigSyncService;
use Illuminate\Console\Command;

class RouterosSyncConfig extends Command
{
    protected $signature = 'routeros:sync-config
        {--router= : Sync a specific router ID only}
        {--module= : Sync specific modules (comma-separated)}
        {--list-modules : List all available sync modules}';

    protected $description = 'Synchronize RouterOS configuration from MikroTik routers to local database';

    public function handle(): int
    {
        if ($this->option('list-modules')) {
            $this->listModules();

            return Command::SUCCESS;
        }

        $this->info('RouterOS Configuration Sync Engine');
        $this->line('');

        $modules = $this->parseModules();
        $router = $this->parseRouter();

        if (empty($modules)) {
            $this->error('No valid modules specified. Use --module=interface,bridge or --list-modules');

            return Command::FAILURE;
        }

        $this->info('Modules: '.implode(', ', $modules));
        if ($router) {
            $this->info("Router: {$router->display_identity} ({$router->host})");
        } else {
            $activeCount = MikrotikRouter::where('is_active', true)->count();
            $this->info("Routers: All active ({$activeCount})");
        }
        $this->line('');

        $service = new RouterosConfigSyncService;

        if ($router) {
            $this->info("Syncing {$router->display_identity}...");
            $result = $service->syncRouter($router, 'manual');
            $this->outputResult($result);
        } else {
            $bar = $this->output->createProgressBar(MikrotikRouter::where('is_active', true)->count());
            $bar->start();

            $result = $service->sync(null, $modules, 'manual', null);

            $bar->finish();
            $this->line('');
            $this->line('');

            foreach ($result['results'] as $r) {
                $this->outputResult($r);
            }

            $this->line('');
            $this->info($result['message']);
        }

        return Command::SUCCESS;
    }

    private function parseRouter(): ?MikrotikRouter
    {
        $routerId = $this->option('router');

        if (! $routerId) {
            return null;
        }

        $router = MikrotikRouter::find($routerId);

        if (! $router) {
            $this->error("Router ID {$routerId} not found.");

            exit(1);
        }

        return $router;
    }

    private function parseModules(): array
    {
        $moduleStr = $this->option('module');

        if (! $moduleStr) {
            return ConfigSyncModuleRegistry::enabledKeys();
        }

        $requested = array_map('trim', explode(',', $moduleStr));

        return array_filter($requested, fn (string $m) => ConfigSyncModuleRegistry::isEnabled($m));
    }

    private function outputResult(array $result): void
    {
        $status = match ($result['status']) {
            'success' => '<info>SUCCESS</info>',
            'partial' => '<comment>PARTIAL</comment>',
            'failed' => '<error>FAILED</error>',
            default => '<fg=gray>UNKNOWN</fg=gray>',
        };

        $stats = $result['stats'];
        $this->line("  [{$status}] {$result['router_name']} — {$stats['total']} items ({$stats['new']} new, {$stats['updated']} updated, {$stats['deleted']} deleted, {$stats['conflict']} conflicts) — {$result['duration_ms']}ms");

        if (! empty($result['failed_modules'])) {
            $this->line('    <error>Failed:</error> '.implode(', ', $result['failed_modules']));
        }
    }

    private function listModules(): void
    {
        $this->info('Available sync modules:');
        $this->line('');

        $table = [];
        foreach (ConfigSyncModuleRegistry::all() as $key => $module) {
            $table[] = [
                $key,
                $module['label'],
                $module['path'],
                $module['enabled'] ? '<info>Yes</info>' : '<comment>No</comment>',
            ];
        }

        $this->table(['Key', 'Label', 'REST Path', 'Enabled'], $table);
    }
}
