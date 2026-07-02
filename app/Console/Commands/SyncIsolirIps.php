<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class SyncIsolirIps extends Command
{
    protected $signature = 'customer:sync-isolir-ips';

    protected $description = 'Sync IP pelanggan suspended ke firewall address-list isolir-users di MikroTik';

    public function handle(): int
    {
        $suspended = Customer::where('status', 'suspended')
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->get();

        if ($suspended->isEmpty()) {
            $this->info('Tidak ada pelanggan suspended.');

            return Command::SUCCESS;
        }

        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $this->syncOnRouter($mikrotik, $suspended);
            } else {
                $this->error('MikroTik belum dikonfigurasi.');
            }
        } else {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                $this->syncOnRouter($mikrotik, $suspended);
            }
        }

        return Command::SUCCESS;
    }

    private function syncOnRouter(MikrotikService $mikrotik, iterable $suspended): void
    {
        $active = $mikrotik->getPppActive();
        $activeByUsername = collect($active)->keyBy('name');

        $synced = 0;

        foreach ($suspended as $customer) {
            $session = $activeByUsername->get($customer->pppoe_username);
            if (! $session || ! isset($session['address'])) {
                continue;
            }

            $ip = $session['address'];
            $result = $mikrotik->addIpToAddressList($ip, 'isolir-users');
            if ($result['success']) {
                $synced++;
            }
        }

        if ($synced > 0) {
            $this->info("{$synced} IP pelanggan suspended disync ke address-list isolir-users.");
        }
    }
}
