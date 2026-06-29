<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class MikrotikSetupIsolir extends Command
{
    protected $signature = 'mikrotik:setup-isolir
                            {--router= : Specific router ID (optional)}
                            {--redirect-ip= : Landing page server IP}
                            {--remove : Remove the isolir setup instead of creating}';

    protected $description = 'Setup Profile-Isolir PPPoE profile + Web Proxy redirect + DROP filter on MikroTik';

    public function handle(): int
    {
        $redirectIp = $this->option('redirect-ip') ?: Setting::get('isolir_redirect_ip', '');
        $remove = $this->option('remove');

        $routers = MikrotikRouter::where('is_active', true);

        if ($routerId = $this->option('router')) {
            $routers = $routers->where('id', $routerId);
        }

        $routerModels = $routers->get();

        if ($routerModels->isEmpty()) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $routerModels = collect([null]);
            } else {
                $this->error('MikroTik belum dikonfigurasi. Tambah router atau isi Setting.');

                return Command::FAILURE;
            }
        }

        foreach ($routerModels as $router) {
            $mikrotik = $router ? new MikrotikService($router) : new MikrotikService;

            $label = $router ? $router->name : 'Global';
            $this->info("Memproses router: {$label}");

            if ($remove) {
                $this->removeIsolirRules($mikrotik);
            } else {
                $this->setupIsolirProfile($mikrotik, $redirectIp);
            }
        }

        return Command::SUCCESS;
    }

    private function setupIsolirProfile(MikrotikService $mikrotik, string $redirectIp): void
    {
        // 1. PPP profile dengan address-list otomatis
        $this->line('  1. Membuat/update PPP profile Profile-Isolir...');
        $result = $mikrotik->addPppProfile('Profile-Isolir', [
            'local-address' => '0.0.0.0',
            'dns-server' => '8.8.8.8,1.1.1.1',
            'address-list' => 'isolir-users',
        ]);

        if ($result['success']) {
            $this->info('  ✅ PPP profile Profile-Isolir siap (address-list=isolir-users)');
        } else {
            $this->warn("  ⚠️  {$result['message']}");
        }

        // 2. DROP rule untuk non-HTTP traffic
        $this->line('  2. Membuat DROP rule untuk non-HTTP traffic...');
        $result = $mikrotik->addFilterDropForAddressList('isolir-users');
        if ($result['success']) {
            $this->info('  ✅ DROP filter rule ditambahkan');
        } else {
            $this->warn("  ⚠️  {$result['message']}");
        }

        // 3. Web proxy + redirect untuk HTTP (biar URL-nya bener)
        // 3. DST-NAT redirect untuk HTTP (port 80) dan HTTPS (port 443)
        if ($redirectIp) {
            $this->line("  3. Setup DST-NAT redirect HTTP & HTTPS ke {$redirectIp} ...");

            $result = $mikrotik->addHttpRedirectForAddressList('isolir-users', $redirectIp, 80);
            if ($result['success']) {
                $this->info('  ✅ DST-NAT redirect HTTP (port 80) ditambahkan');
            } else {
                $this->warn("  ⚠️  {$result['message']}");
            }

            $result = $mikrotik->addHttpRedirectForAddressList('isolir-users', $redirectIp, 443);
            if ($result['success']) {
                $this->info('  ✅ DST-NAT redirect HTTPS (port 443) ditambahkan');
            } else {
                $this->warn("  ⚠️  {$result['message']}");
            }

            $result = $mikrotik->addFilterDropForAddressList('isolir-users', $redirectIp);
            if ($result['success']) {
                $this->info('  ✅ Filter rules (ACCEPT redirect + DROP lainnya) ditambahkan');
            } else {
                $this->warn("  ⚠️  {$result['message']}");
            }
        } else {
            $this->warn('  ⚠️  --redirect-ip tidak diisi. Set nanti via Setting isolir_redirect_ip.');
        }

        $this->newLine();
        $this->info('  ✅ Setup selesai.');
        $this->line('  Cek di MikroTik:');
        $this->line('  • PPP → Profiles: Profile-Isolir (address-list=isolir-users)');
        $this->line('  • IP → Firewall → NAT: rule isolir-redirect-80 + isolir-redirect-443');
        $this->line('  • IP → Firewall → Filter Rules: ISOLIR-ACCEPT-REDIRECT + BLOCK-ISOLIR');
    }

    private function removeIsolirRules(MikrotikService $mikrotik): void
    {
        $this->line('  Menghapus Web Proxy redirect...');
        $mikrotik->removeWebProxyNatRedirect();
        $mikrotik->removeWebProxyRedirectForAddressList();
        $this->info('  ✅ Web proxy redirect dihapus');
    }
}
