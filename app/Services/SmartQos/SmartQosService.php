<?php

namespace App\Services\SmartQos;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SmartQosService
{
    private const CAKE_TYPE_NAME = 'cake-smartqos';

    private const SMARTQOS_PREFIX = 'sq-';

    public static function getActivePppoeRouters(): Collection
    {
        $routers = MikrotikRouter::withoutGlobalScope('tenant_id')
            ->where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isEmpty()) {
            $routers = MikrotikRouter::withoutGlobalScope('tenant_id')
                ->where('is_active', true)
                ->get();
        }

        return $routers;
    }

    public static function ensureCakeQueueType(MikrotikRouter $router): bool
    {
        try {
            $service = new RouterConnectionService($router);

            $listResult = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/type'));
            if (! $listResult->isSuccess()) {
                Log::warning("SmartQos: Gagal list queue type di {$router->name}: {$listResult->getMessage()}");

                return false;
            }

            $existing = collect($listResult->toArray() ?? []);
            if ($existing->contains('name', self::CAKE_TYPE_NAME)) {
                return true;
            }

            $createResult = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut('/queue/type', [
                'name' => self::CAKE_TYPE_NAME,
                'kind' => 'cake',
                'cake-diffserv' => 'besteffort',
                'cake-flowmode' => 'flowblind',
                'cake-nat' => 'yes',
                'cake-memlimit' => '4mb',
            ]));

            if (! $createResult->isSuccess()) {
                Log::warning("SmartQos: Gagal buat queue type di {$router->name}: {$createResult->getMessage()}");

                return false;
            }

            Log::info("SmartQos: Queue type '".self::CAKE_TYPE_NAME."' dibuat di {$router->name}");

            return true;
        } catch (\Exception $e) {
            Log::warning("SmartQos: Exception buat queue type di {$router->name}: {$e->getMessage()}");

            return false;
        }
    }

    public static function provisionCustomerQueue(Customer $customer): bool
    {
        if (! $customer->package || ! $customer->package->hasQosConfig()) {
            return false;
        }

        $routers = self::getActivePppoeRouters();

        $provisioned = false;
        foreach ($routers as $router) {
            if (self::provisionOnRouter($customer, $router)) {
                $provisioned = true;
            }
        }

        return $provisioned;
    }

    private static function provisionOnRouter(Customer $customer, MikrotikRouter $router, ?array $pppSessions = null, ?array $dhcpLeases = null, ?array $arpEntries = null): bool
    {
        self::ensureCakeQueueType($router);

        $service = new RouterConnectionService($router);

        if ($pppSessions === null) {
            $pppSessions = self::getPppActiveSessions($service);
        }
        if ($dhcpLeases === null) {
            $dhcpLeases = self::getDhcpLeases($service);
        }
        if ($arpEntries === null) {
            $arpEntries = self::getArpEntries($service);
        }

        $ip = self::resolveCustomerIp($customer, $pppSessions, $dhcpLeases, $arpEntries);
        if (! $ip) {
            return false;
        }

        $queueName = self::SMARTQOS_PREFIX.$customer->customer_code;
        $dl = $customer->package->getDownloadRate();
        $ul = $customer->package->getUploadRate();

        try {
            $existing = self::findQueueByName($service, $queueName);

            $data = [
                'name' => $queueName,
                'target' => $ip.'/32',
                'max-limit' => $dl.'/'.$ul,
                'queue' => self::CAKE_TYPE_NAME.'/'.self::CAKE_TYPE_NAME,
                'comment' => "SmartQos: {$customer->name} ({$customer->package->getDownloadRate()}/{$customer->package->getUploadRate()})",
            ];

            if ($existing && isset($existing['.id'])) {
                unset($data['name']);
                $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch('/queue/simple/'.$existing['.id'], $data));
            } else {
                $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut('/queue/simple', $data));
            }

            if (! $result->isSuccess()) {
                Log::warning("SmartQos: Gagal provisioning queue untuk {$customer->name} di {$router->name}: {$result->getMessage()}");

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("SmartQos: Exception provisioning queue untuk {$customer->name} di {$router->name}: {$e->getMessage()}");

            return false;
        }
    }

    public static function removeCustomerQueue(Customer $customer): bool
    {
        $routers = self::getActivePppoeRouters();
        $removed = false;

        foreach ($routers as $router) {
            try {
                $service = new RouterConnectionService($router);
                $queueName = self::SMARTQOS_PREFIX.$customer->customer_code;
                $existing = self::findQueueByName($service, $queueName);

                if ($existing && isset($existing['.id'])) {
                    $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete('/queue/simple/'.$existing['.id']));
                    if ($result->isSuccess()) {
                        $removed = true;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("SmartQos: Exception remove queue {$customer->name}: {$e->getMessage()}");
            }
        }

        return $removed;
    }

    public static function disableCustomerQueue(Customer $customer): bool
    {
        $routers = self::getActivePppoeRouters();
        $disabled = false;

        foreach ($routers as $router) {
            try {
                $service = new RouterConnectionService($router);
                $queueName = self::SMARTQOS_PREFIX.$customer->customer_code;
                $existing = self::findQueueByName($service, $queueName);

                if ($existing && isset($existing['.id']) && ($existing['disabled'] ?? 'false') === 'false') {
                    $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch('/queue/simple/'.$existing['.id'], ['disabled' => 'true']));
                    if ($result->isSuccess()) {
                        $disabled = true;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("SmartQos: Exception disable queue {$customer->name}: {$e->getMessage()}");
            }
        }

        return $disabled;
    }

    public static function enableCustomerQueue(Customer $customer): bool
    {
        $routers = self::getActivePppoeRouters();
        $enabled = false;

        foreach ($routers as $router) {
            try {
                $service = new RouterConnectionService($router);
                $queueName = self::SMARTQOS_PREFIX.$customer->customer_code;
                $existing = self::findQueueByName($service, $queueName);

                if ($existing && isset($existing['.id']) && ($existing['disabled'] ?? 'false') === 'true') {
                    $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch('/queue/simple/'.$existing['.id'], ['disabled' => 'false']));
                    if ($result->isSuccess()) {
                        $enabled = true;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("SmartQos: Exception enable queue {$customer->name}: {$e->getMessage()}");
            }
        }

        return $enabled;
    }

    public static function updateCustomerQueue(Customer $customer): bool
    {
        if (! $customer->package || ! $customer->package->hasQosConfig()) {
            return false;
        }

        return self::provisionCustomerQueue($customer);
    }

    public static function syncAllQueues(MikrotikRouter $router): array
    {
        self::ensureCakeQueueType($router);

        $provisioned = 0;
        $skippedNoQos = 0;
        $skippedNoIp = 0;
        $failed = 0;

        $customers = Customer::where('status', 'active')
            ->whereNotNull('package_id')
            ->with('package')
            ->get();

        $service = new RouterConnectionService($router);

        $pppSessions = self::getPppActiveSessions($service);
        $dhcpLeases = self::getDhcpLeases($service);
        $arpEntries = self::getArpEntries($service);

        foreach ($customers as $customer) {
            if (! $customer->package || ! $customer->package->hasQosConfig()) {
                $skippedNoQos++;

                continue;
            }

            $ip = self::resolveCustomerIp($customer, $pppSessions, $dhcpLeases, $arpEntries);

            if (! $ip) {
                $skippedNoIp++;
                Log::debug("SmartQos: Skip {$customer->name} ({$customer->customer_code}) — IP tidak ditemukan di {$router->name}");

                continue;
            }

            if (self::provisionOnRouter($customer, $router, $pppSessions, $dhcpLeases, $arpEntries)) {
                $provisioned++;
            } else {
                $failed++;
            }
        }

        try {
            $listResult = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/simple'));
            if ($listResult->isSuccess()) {
                $queues = collect($listResult->toArray() ?? []);
                $smartQosQueues = $queues->filter(fn ($q) => str_starts_with($q['name'] ?? '', self::SMARTQOS_PREFIX));

                $customerCodes = $customers->pluck('customer_code')->toArray();
                foreach ($smartQosQueues as $queue) {
                    $code = str_replace(self::SMARTQOS_PREFIX, '', $queue['name'] ?? '');
                    if (! in_array($code, $customerCodes)) {
                        if (isset($queue['.id'])) {
                            $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete('/queue/simple/'.$queue['.id']));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Exception cleanup orphan queues di {$router->name}: {$e->getMessage()}");
        }

        Log::info("SmartQos: Sync {$router->name} — provisioned: {$provisioned}, skipped_no_qos: {$skippedNoQos}, skipped_no_ip: {$skippedNoIp}, failed: {$failed}");

        return [
            'provisioned' => $provisioned,
            'skipped_no_qos' => $skippedNoQos,
            'skipped_no_ip' => $skippedNoIp,
            'failed' => $failed,
            'removed' => 0,
        ];
    }

    public static function getHealthStats(MikrotikRouter $router): array
    {
        $service = new RouterConnectionService($router);

        $simpleQueues = self::fetchSimpleQueues($service);
        $queueTypes = self::fetchQueueTypes($service);
        $queueTrees = self::fetchQueueTrees($service);
        $interfaces = self::fetchInterfaces($service);
        $latency = self::fetchLatency($service);
        $pppStats = self::fetchPppStats($service);

        $smartQosQueues = $simpleQueues->filter(fn ($q) => str_starts_with($q['name'] ?? '', self::SMARTQOS_PREFIX));
        $existingQueues = $simpleQueues->reject(fn ($q) => str_starts_with($q['name'] ?? '', self::SMARTQOS_PREFIX));

        $cakeType = $queueTypes->firstWhere('name', self::CAKE_TYPE_NAME);
        $cakeActive = $cakeType !== null;

        $cakeQueues = $simpleQueues->filter(fn ($q) => str_contains($q['queue'] ?? '', self::CAKE_TYPE_NAME));
        $pfifoQueues = $simpleQueues->filter(fn ($q) => str_contains($q['queue'] ?? '', 'pfifo') || ! isset($q['queue']));

        $cakeTreeCount = $queueTrees->filter(fn ($t) => str_contains($t['queue'] ?? '', 'cake'))->count();
        $pfifoTreeCount = $queueTrees->count() - $cakeTreeCount;

        return [
            'router_id' => $router->id,
            'router_name' => $router->display_identity ?? $router->name,
            'latency_ms' => $latency,
            'grade' => self::bufferbloatGrade($latency),
            'cake_active' => $cakeActive,
            'cake_type' => $cakeType ? [
                'name' => $cakeType['name'] ?? '',
                'kind' => $cakeType['kind'] ?? '',
                'diffserv' => $cakeType['cake-diffserv'] ?? '',
                'flowmode' => $cakeType['cake-flowmode'] ?? '',
                'nat' => $cakeType['cake-nat'] ?? '',
                'memlimit' => $cakeType['cake-memlimit'] ?? '',
            ] : null,
            'queue_types' => $queueTypes->map(fn ($qt) => [
                'name' => $qt['name'] ?? '',
                'kind' => $qt['kind'] ?? '',
            ])->toArray(),
            'summary' => [
                'total_simple_queues' => count($simpleQueues),
                'smartqos_queues' => count($smartQosQueues),
                'existing_queues' => count($existingQueues),
                'cake_queues' => count($cakeQueues),
                'pfifo_queues' => count($pfifoQueues),
                'total_trees' => count($queueTrees),
                'cake_trees' => $cakeTreeCount,
                'pfifo_trees' => $pfifoTreeCount,
                'ppp_active' => $pppStats['active'] ?? 0,
                'ppp_total' => $pppStats['total'] ?? 0,
            ],
            'smartqos_queues' => $smartQosQueues->map(fn ($q) => self::formatQueue($q))->toArray(),
            'existing_queues' => $existingQueues->map(fn ($q) => self::formatQueue($q))->toArray(),
            'cake_queues' => $cakeQueues->map(fn ($q) => self::formatQueue($q))->toArray(),
            'queue_trees' => $queueTrees->map(fn ($t) => [
                'name' => $t['name'] ?? '',
                'parent' => $t['parent'] ?? '',
                'queue' => $t['queue'] ?? '',
                'rate' => $t['rate'] ?? '',
                'max_limit' => $t['max-limit'] ?? '',
                'bytes' => $t['bytes'] ?? '0',
                'disabled' => ($t['disabled'] ?? 'false') === 'true',
                'comment' => $t['comment'] ?? '',
            ])->toArray(),
            'interfaces' => $interfaces->map(fn ($i) => [
                'name' => $i['name'] ?? '',
                'type' => $i['type'] ?? '',
                'tx_rate' => $i['tx-rate'] ?? '0bps',
                'rx_rate' => $i['rx-rate'] ?? '0bps',
                'tx_bytes' => $i['tx-byte'] ?? '0',
                'rx_bytes' => $i['rx-byte'] ?? '0',
                'link_down' => ($i['link-down'] ?? 'false') === 'true',
                'running' => ($i['running'] ?? 'false') === 'true',
            ])->toArray(),
        ];
    }

    private static function formatQueue(array $queue): array
    {
        $name = $queue['name'] ?? '';
        $isSmartQos = str_starts_with($name, self::SMARTQOS_PREFIX);

        return [
            'id' => $queue['.id'] ?? '',
            'name' => $name,
            'target' => $queue['target'] ?? '',
            'max_limit' => $queue['max-limit'] ?? '',
            'min_limit' => $queue['min-limit'] ?? '',
            'queue_type' => $queue['queue'] ?? '',
            'rate' => $queue['rate'] ?? '0',
            'bytes' => $queue['bytes'] ?? '0',
            'packets' => $queue['packets'] ?? '0',
            'disabled' => ($queue['disabled'] ?? 'false') === 'true',
            'comment' => $queue['comment'] ?? '',
            'is_smartqos' => $isSmartQos,
            'burst_limit' => $queue['burst-limit'] ?? '',
            'burst_threshold' => $queue['burst-threshold'] ?? '',
            'burst_time' => $queue['burst-time'] ?? '',
        ];
    }

    private static function fetchSimpleQueues(RouterConnectionService $service): Collection
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/simple'));
            if ($result->isSuccess()) {
                return collect($result->toArray() ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal list simple queues: {$e->getMessage()}");
        }

        return collect([]);
    }

    private static function fetchQueueTypes(RouterConnectionService $service): Collection
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/type'));
            if ($result->isSuccess()) {
                return collect($result->toArray() ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal list queue types: {$e->getMessage()}");
        }

        return collect([]);
    }

    private static function fetchQueueTrees(RouterConnectionService $service): Collection
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/tree'));
            if ($result->isSuccess()) {
                return collect($result->toArray() ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal list queue trees: {$e->getMessage()}");
        }

        return collect([]);
    }

    private static function fetchInterfaces(RouterConnectionService $service): Collection
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface'));
            if ($result->isSuccess()) {
                return collect($result->toArray() ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal list interfaces: {$e->getMessage()}");
        }

        return collect([]);
    }

    private static function fetchLatency(RouterConnectionService $service): float
    {
        try {
            $latencyResult = $service->getLatency();
            if ($latencyResult->isSuccess()) {
                return (float) $latencyResult->first();
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal ambil latency: {$e->getMessage()}");
        }

        return 0;
    }

    private static function fetchPppStats(RouterConnectionService $service): array
    {
        $stats = ['active' => 0, 'total' => 0];

        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ppp/active'));
            if ($result->isSuccess()) {
                $data = $result->toArray();
                $stats['active'] = is_array($data) ? count($data) : 0;
            }
        } catch (\Exception $e) {
            // silent
        }

        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ppp/secret'));
            if ($result->isSuccess()) {
                $data = $result->toArray();
                $stats['total'] = is_array($data) ? count($data) : 0;
            }
        } catch (\Exception $e) {
            // silent
        }

        return $stats;
    }

    public static function optimizeCpuQueues(MikrotikRouter $router): array
    {
        $optimized = 0;

        try {
            $service = new RouterConnectionService($router);

            $listResult = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/simple'));
            if (! $listResult->isSuccess()) {
                return ['optimized' => 0];
            }

            $queues = collect($listResult->toArray() ?? []);
            $smartQosQueues = $queues->filter(fn ($q) => str_starts_with($q['name'] ?? '', self::SMARTQOS_PREFIX));

            foreach ($smartQosQueues as $queue) {
                $id = $queue['.id'] ?? null;
                if (! $id) {
                    continue;
                }

                $needsUpdate = false;
                $data = [];

                $currentQueueType = $queue['queue'] ?? '';
                if (! str_contains($currentQueueType, self::CAKE_TYPE_NAME)) {
                    $data['queue'] = self::CAKE_TYPE_NAME.'/'.self::CAKE_TYPE_NAME;
                    $needsUpdate = true;
                }

                $priority = (int) ($queue['priority'] ?? 8);
                if ($priority !== 8) {
                    $data['priority'] = '8';
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch('/queue/simple/'.$id, $data));
                    if ($result->isSuccess()) {
                        $optimized++;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("SmartQos: Gagal optimize CPU queues di {$router->name}: {$e->getMessage()}");
        }

        return ['optimized' => $optimized];
    }

    public static function bufferbloatGrade(float $latencyMs): string
    {
        if ($latencyMs <= 10) {
            return 'A+';
        }
        if ($latencyMs <= 20) {
            return 'A';
        }
        if ($latencyMs <= 50) {
            return 'B';
        }
        if ($latencyMs <= 100) {
            return 'C';
        }

        return 'D';
    }

    private static function findQueueByName(RouterConnectionService $service, string $name): ?array
    {
        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/queue/simple'));
        if (! $result->isSuccess()) {
            return null;
        }

        $queues = collect($result->toArray() ?? []);

        return $queues->firstWhere('name', $name);
    }

    private static function getPppActiveSessions(RouterConnectionService $service): array
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->getPppActive());
            if ($result->isSuccess()) {
                $data = $result->toArray();

                return is_array($data) ? $data : [];
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal ambil PPPoE active: {$e->getMessage()}");
        }

        return [];
    }

    private static function getDhcpLeases(RouterConnectionService $service): array
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->getDhcpLeases());
            if ($result->isSuccess()) {
                $data = $result->toArray();

                return is_array($data) ? $data : [];
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal ambil DHCP leases: {$e->getMessage()}");
        }

        return [];
    }

    private static function getArpEntries(RouterConnectionService $service): array
    {
        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/arp'));
            if ($result->isSuccess()) {
                $data = $result->toArray();

                return is_array($data) ? $data : [];
            }
        } catch (\Exception $e) {
            Log::warning("SmartQos: Gagal ambil ARP entries: {$e->getMessage()}");
        }

        return [];
    }

    private static function resolveCustomerIp(Customer $customer, array $pppSessions, array $dhcpLeases, array $arpEntries): ?string
    {
        if ($customer->pppoe_username) {
            foreach ($pppSessions as $session) {
                if (($session['name'] ?? '') === $customer->pppoe_username) {
                    return $session['address'] ?? null;
                }
            }
        }

        if ($customer->mac_address) {
            $mac = strtolower($customer->mac_address);

            foreach ($dhcpLeases as $lease) {
                if (strtolower($lease['mac-address'] ?? '') === $mac) {
                    return $lease['address'] ?? null;
                }
            }

            foreach ($arpEntries as $arp) {
                if (strtolower($arp['mac-address'] ?? '') === $mac) {
                    return $arp['address'] ?? null;
                }
            }
        }

        return null;
    }
}
