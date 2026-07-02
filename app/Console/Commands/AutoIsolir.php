<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoIsolir extends Command
{
    protected $signature = 'customer:auto-isolir
                            {--dry-run : Only show customers that would be isolated, do not execute}';

    protected $description = 'Isolir otomatis pelanggan yang melewati jatuh tempo dan memiliki tagihan unpaid';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::today();

        $customers = Customer::where('status', 'active')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $now)
            ->whereHas('invoices', function ($q) {
                $q->where('payment_status', 'unpaid');
            })
            ->get();

        if ($customers->isEmpty()) {
            $this->info('Tidak ada pelanggan yang perlu diisolir.');

            return Command::SUCCESS;
        }

        $this->warn("Ditemukan {$customers->count()} pelanggan melewati jatuh tempo:");

        foreach ($customers as $customer) {
            $this->line("  - {$customer->name} (due: {$customer->due_date})");

            if ($dryRun) {
                continue;
            }

            $customer->update([
                'original_ppp_profile' => $customer->package?->mikrotik_profile,
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            $this->syncPppIsolir($customer);

            ActivityLog::log('Auto Isolir', "Isolir otomatis: {$customer->name}");
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Tidak ada perubahan yang dilakukan.');
            $this->info("Jalankan tanpa --dry-run untuk mengeksekusi isolir {$customers->count()} pelanggan.");
        } else {
            $this->info("Berhasil mengisolir {$customers->count()} pelanggan.");
        }

        return Command::SUCCESS;
    }

    private function syncPppIsolir(Customer $customer): void
    {
        if (! $customer->pppoe_username) {
            return;
        }

        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isNotEmpty()) {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                $this->applyIsolirToRouter($mikrotik, $customer);
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $this->applyIsolirToRouter($mikrotik, $customer);
            }
        }
    }

    private function applyIsolirToRouter(MikrotikService $mikrotik, Customer $customer): void
    {
        if ($customer->original_ppp_profile) {
            $mikrotik->setPppSecretProfile($customer->pppoe_username, 'Profile-Isolir');
        }
        $this->addCustomerIpToAddressList($mikrotik, $customer);
        $this->disconnectPppSession($mikrotik, $customer->pppoe_username);
    }

    private function addCustomerIpToAddressList(MikrotikService $mikrotik, Customer $customer): void
    {
        $ip = $this->getCustomerPppIp($mikrotik, $customer->pppoe_username);
        if ($ip) {
            $mikrotik->addIpToAddressList($ip, 'isolir-users');
        }
    }

    private function getCustomerPppIp(MikrotikService $mikrotik, string $username): ?string
    {
        try {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $username);

            return $session['address'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function disconnectPppSession(MikrotikService $mikrotik, string $username): void
    {
        try {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $username);
            if ($session && isset($session['.id'])) {
                $mikrotik->disconnectPppSession($session['.id']);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal putus sesi PPP {$username}: {$e->getMessage()}");
        }
    }
}
