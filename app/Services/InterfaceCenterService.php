<?php

namespace App\Services;

use App\Models\InterfaceChangeLog;
use App\Models\MikrotikInterfaceMetadata;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use App\Services\Mikrotik\RouterResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service layer for MikroTik Interface Center.
 *
 * All communication with RouterOS goes through RouterOS API Service.
 * This service handles:
 *  - Fetching live interface data from all routers
 *  - Merging with local metadata (alias, tags, notes)
 *  - Computing traffic rates
 *  - Interface configuration changes
 *  - Bulk operations
 *  - Change logging
 */
class InterfaceCenterService
{
    private const CACHE_PREFIX = 'iface_center:';

    private const RATE_CACHE_TTL = 5; // seconds

    /**
     * Fetch all interfaces from all active routers, merged with metadata.
     *
     * @return array<int, array{id: int, router_name: string, router_id: int, site: ?string, interfaces: array}>
     */
    public function fetchAllInterfaces(): array
    {
        $routers = MikrotikRouter::where('is_active', true)->get();
        $results = [];

        foreach ($routers as $router) {
            $routerData = $this->fetchRouterInterfaces($router);
            $results[] = $routerData;
        }

        return $results;
    }

    /**
     * Fetch interfaces for a single router.
     */
    public function fetchRouterInterfaces(MikrotikRouter $router): array
    {
        $base = [
            'id' => $router->id,
            'router_name' => $router->display_identity,
            'router_id' => $router->id,
            'host' => $router->host,
            'site' => $router->site,
            'online' => false,
            'interfaces' => [],
            'error' => null,
        ];

        if (! $router->is_active) {
            return $base;
        }

        try {
            $service = RouterConnectionService::forRouter($router->id);
            if (! $service) {
                $base['error'] = 'Router tidak ditemukan';

                return $base;
            }

            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());

            if (! $result->isSuccess()) {
                $base['error'] = $result->getMessage();

                return $base;
            }

            $base['online'] = true;
            $interfaces = $result->toArray() ?? [];
            $metadata = $this->getMetadataMap($router->id);

            $base['interfaces'] = array_map(function ($iface) use ($router, $metadata) {
                $name = $iface['name'] ?? $iface['.name'] ?? '';
                $meta = $metadata[$name] ?? null;

                return $this->mergeInterfaceData($router, $iface, $meta);
            }, $interfaces);

        } catch (\Exception $e) {
            $base['error'] = $e->getMessage();
            Log::warning('InterfaceCenter: gagal fetch interfaces', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $base;
    }

    /**
     * Fetch interfaces for detail view with traffic rates.
     */
    public function fetchInterfaceDetail(MikrotikRouter $router, string $interfaceName): array
    {
        $service = RouterConnectionService::forRouter($router->id);
        if (! $service) {
            return ['error' => 'Router tidak ditemukan'];
        }

        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());

        if (! $result->isSuccess()) {
            return ['error' => $result->getMessage()];
        }

        $interfaces = $result->toArray() ?? [];
        $iface = null;
        foreach ($interfaces as $i) {
            $iname = $i['name'] ?? $i['.name'] ?? '';
            if ($iname === $interfaceName) {
                $iface = $i;
                break;
            }
        }

        if (! $iface) {
            return ['error' => 'Interface tidak ditemukan'];
        }

        $metadata = $this->getMetadataMap($router->id);
        $meta = $metadata[$interfaceName] ?? null;
        $merged = $this->mergeInterfaceData($router, $iface, $meta);

        // Get traffic snapshot for rate calculation
        $trafficResult = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaceTraffic($interfaceName));
        $traffic = $trafficResult->isSuccess() ? ($trafficResult->first() ?? []) : [];

        // Get previous bytes from cache for rate calculation
        $cacheKey = self::CACHE_PREFIX."bytes:{$router->id}:{$interfaceName}";
        $prevBytes = Cache::get($cacheKey);
        $now = microtime(true);

        $rateRx = 0;
        $rateTx = 0;

        if ($prevBytes) {
            $elapsed = $now - $prevBytes['time'];
            if ($elapsed > 0) {
                $curRx = (int) ($traffic['rx-byte'] ?? $iface['rx-byte'] ?? 0);
                $curTx = (int) ($traffic['tx-byte'] ?? $iface['tx-byte'] ?? 0);
                $rateRx = max(0, ($curRx - $prevBytes['rx']) * 8 / $elapsed);
                $rateTx = max(0, ($curTx - $prevBytes['tx']) * 8 / $elapsed);
            }
        }

        // Store current bytes for next calculation
        Cache::put($cacheKey, [
            'rx' => (int) ($traffic['rx-byte'] ?? $iface['rx-byte'] ?? 0),
            'tx' => (int) ($traffic['tx-byte'] ?? $iface['tx-byte'] ?? 0),
            'time' => $now,
        ], self::RATE_CACHE_TTL * 2);

        $merged['rate_rx'] = $rateRx;
        $merged['rate_tx'] = $rateTx;
        $merged['traffic'] = $traffic;

        // Get change history
        $merged['change_history'] = InterfaceChangeLog::where('mikrotik_router_id', $router->id)
            ->where('interface_name', $interfaceName)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $merged;
    }

    /**
     * Compute summary statistics across all routers.
     */
    public function computeStats(array $allRouterData): array
    {
        $totalInterfaces = 0;
        $totalUp = 0;
        $totalDown = 0;
        $totalDisabled = 0;
        $totalRx = 0;
        $totalTx = 0;
        $topTrafficIface = ['name' => '-', 'router' => '-', 'bytes' => 0];
        $routerCounts = [];

        foreach ($allRouterData as $rd) {
            $routerId = $rd['router_id'];
            $routerName = $rd['router_name'];
            $count = 0;

            foreach ($rd['interfaces'] as $iface) {
                $count++;
                $running = ($iface['running'] ?? '') === 'true' || ($iface['running'] ?? '') === true;
                $disabled = ($iface['disabled'] ?? '') === 'true' || ($iface['disabled'] ?? '') === true;
                $rx = (int) ($iface['rx-byte'] ?? 0);
                $tx = (int) ($iface['tx-byte'] ?? 0);

                $totalInterfaces++;
                if ($disabled) {
                    $totalDisabled++;
                } elseif ($running) {
                    $totalUp++;
                } else {
                    $totalDown++;
                }

                $totalRx += $rx;
                $totalTx += $tx;

                $totalBytes = $rx + $tx;
                if ($totalBytes > $topTrafficIface['bytes']) {
                    $topTrafficIface = [
                        'name' => $iface['name'] ?? $iface['.name'] ?? '-',
                        'router' => $routerName,
                        'router_id' => $routerId,
                        'bytes' => $totalBytes,
                        'rx' => $rx,
                        'tx' => $tx,
                    ];
                }
            }

            $routerCounts[] = ['router_id' => $routerId, 'router_name' => $routerName, 'count' => $count];
        }

        usort($routerCounts, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total' => $totalInterfaces,
            'up' => $totalUp,
            'down' => $totalDown,
            'disabled' => $totalDisabled,
            'total_rx' => $totalRx,
            'total_tx' => $totalTx,
            'top_router' => $routerCounts[0] ?? null,
            'top_traffic' => $topTrafficIface,
            'router_counts' => $routerCounts,
        ];
    }

    /**
     * Get unique interface types across all data.
     */
    public function getInterfaceTypes(array $allRouterData): array
    {
        $types = [];
        foreach ($allRouterData as $rd) {
            foreach ($rd['interfaces'] as $iface) {
                $type = $iface['type'] ?? 'unknown';
                $types[$type] = ($types[$type] ?? 0) + 1;
            }
        }
        arsort($types);

        return $types;
    }

    /**
     * Get all unique tags from metadata.
     */
    public function getAllTags(): array
    {
        $tags = MikrotikInterfaceMetadata::whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return $tags->toArray();
    }

    /**
     * Update interface configuration on the router.
     *
     * @param  array{disabled?: bool, comment?: string, mtu?: int, name?: string, auto_negotiation?: string, speed?: string}  $params
     */
    public function updateInterface(MikrotikRouter $router, string $interfaceName, array $params): RouterResult
    {
        $service = RouterConnectionService::forRouter($router->id);
        if (! $service) {
            return RouterResult::fail('Router tidak ditemukan', 'not_found');
        }

        // First, get current state for logging
        $currentResult = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaceByName($interfaceName));
        $oldState = $currentResult->isSuccess() ? ($currentResult->first() ?? []) : null;

        // Build patch data
        $patchData = [];
        $changeType = 'unknown';

        if (array_key_exists('disabled', $params)) {
            $patchData['disabled'] = $params['disabled'] ? 'true' : 'false';
            $changeType = $params['disabled'] ? 'disable' : 'enable';
        } elseif (array_key_exists('name', $params)) {
            $patchData['name'] = $params['name'];
            $changeType = 'rename';
        } elseif (array_key_exists('mtu', $params)) {
            $patchData['mtu'] = (string) $params['mtu'];
            $changeType = 'mtu';
        } elseif (array_key_exists('comment', $params)) {
            $patchData['comment'] = $params['comment'];
            $changeType = 'comment';
        } elseif (array_key_exists('auto_negotiation', $params)) {
            $patchData['auto-negotiation'] = $params['auto_negotiation'] ? 'true' : 'false';
            $changeType = 'auto_negotiation';
        } elseif (array_key_exists('speed', $params)) {
            $patchData['speed'] = $params['speed'];
            $changeType = 'speed';
        }

        if (empty($patchData)) {
            return RouterResult::fail('Tidak ada parameter yang diberikan', 'invalid_params');
        }

        // Get the interface ID from the raw data
        $ifaceId = $oldState['.id'] ?? null;
        if (! $ifaceId) {
            return RouterResult::fail('Interface ID tidak ditemukan', 'not_found');
        }

        $result = $service->rawPatch("/interface/{$ifaceId}", $patchData);

        // Log the change
        InterfaceChangeLog::logChange(
            $router->id,
            $interfaceName,
            $changeType,
            $oldState,
            $patchData,
            $result->isSuccess() ? 'success' : 'failed',
            $result->isSuccess() ? null : $result->getMessage(),
        );

        // Update local metadata if it was an alias/comment change
        if ($changeType === 'comment' || $changeType === 'rename') {
            $this->touchMetadata($router->id, $interfaceName);
        }

        ActivityLog::log(
            'Ubah Interface NOC',
            "{$changeType} pada {$interfaceName} di {$router->display_identity}: ".($result->isSuccess() ? 'berhasil' : $result->getMessage()),
        );

        return $result;
    }

    /**
     * Execute a bulk operation on multiple interfaces.
     *
     * @param  array{router_id: int, interfaces: string[], action: string, params?: array}  $request
     */
    public function bulkOperation(array $request): array
    {
        $routerId = $request['router_id'];
        $interfaceNames = $request['interfaces'];
        $action = $request['action'];
        $params = $request['params'] ?? [];

        $router = MikrotikRouter::find($routerId);
        if (! $router) {
            return ['success' => false, 'message' => 'Router tidak ditemukan', 'results' => []];
        }

        $service = RouterConnectionService::forRouter($routerId);
        if (! $service) {
            return ['success' => false, 'message' => 'Gagal koneksi ke router', 'results' => []];
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($interfaceNames as $ifaceName) {
            try {
                $updateParams = match ($action) {
                    'enable' => ['disabled' => false],
                    'disable' => ['disabled' => true],
                    'set_tag' => $this->bulkTagOperation($routerId, $ifaceName, 'add', $params['tags'] ?? []),
                    'remove_tag' => $this->bulkTagOperation($routerId, $ifaceName, 'remove', $params['tags'] ?? []),
                    default => null,
                };

                if ($updateParams === null) {
                    $results[] = ['interface' => $ifaceName, 'success' => false, 'message' => 'Aksi tidak dikenal: '.$action];
                    $failCount++;

                    continue;
                }

                // For tag operations, we handle locally (not via API)
                if (in_array($action, ['set_tag', 'remove_tag'])) {
                    $results[] = ['interface' => $ifaceName, 'success' => true, 'message' => 'Tag diperbarui'];
                    $successCount++;

                    continue;
                }

                $result = $this->updateInterface($router, $ifaceName, $updateParams);
                $results[] = [
                    'interface' => $ifaceName,
                    'success' => $result->isSuccess(),
                    'message' => $result->getMessage(),
                ];
                if ($result->isSuccess()) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $results[] = ['interface' => $ifaceName, 'success' => false, 'message' => $e->getMessage()];
                $failCount++;
            }
        }

        ActivityLog::log(
            'Bulk Operation Interface NOC',
            "{$action} pada ".count($interfaceNames)." interface di {$router->display_identity}: {$successCount} berhasil, {$failCount} gagal",
        );

        return [
            'success' => $failCount === 0,
            'message' => "{$successCount} berhasil, {$failCount} gagal",
            'results' => $results,
            'success_count' => $successCount,
            'fail_count' => $failCount,
        ];
    }

    /**
     * Update interface metadata (alias, tags, notes).
     */
    public function updateMetadata(int $routerId, string $interfaceName, array $data): MikrotikInterfaceMetadata
    {
        return MikrotikInterfaceMetadata::updateOrCreate(
            [
                'tenant_id' => auth()->user()->tenant_id ?? 0,
                'mikrotik_router_id' => $routerId,
                'interface_name' => $interfaceName,
            ],
            array_filter([
                'alias' => $data['alias'] ?? null,
                'tags' => $data['tags'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_monitored' => $data['is_monitored'] ?? true,
            ], fn ($v) => $v !== null),
        );
    }

    /**
     * Get filter options for the interface list.
     */
    public function getFilterOptions(): array
    {
        return [
            'sites' => MikrotikRouter::where('is_active', true)
                ->whereNotNull('site')->where('site', '!=', '')
                ->distinct()->pluck('site')->sort()->values(),
            'tags' => $this->getAllTags(),
            'routers' => MikrotikRouter::where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'identity']),
        ];
    }

    // ═══════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════

    private function getMetadataMap(int $routerId): array
    {
        $metadata = MikrotikInterfaceMetadata::where('mikrotik_router_id', $routerId)->get();

        $map = [];
        foreach ($metadata as $m) {
            $map[$m->interface_name] = $m;
        }

        return $map;
    }

    private function mergeInterfaceData(MikrotikRouter $router, array $iface, ?MikrotikInterfaceMetadata $meta): array
    {
        $name = $iface['name'] ?? $iface['.name'] ?? '';

        return [
            'name' => $name,
            '.id' => $iface['.id'] ?? null,
            'type' => $iface['type'] ?? 'unknown',
            'running' => ($iface['running'] ?? '') === 'true' || ($iface['running'] ?? '') === true,
            'disabled' => ($iface['disabled'] ?? '') === 'true' || ($iface['disabled'] ?? '') === true,
            'mtu' => $iface['mtu'] ?? null,
            'mac_address' => $iface['mac-address'] ?? null,
            'rx_byte' => (int) ($iface['rx-byte'] ?? 0),
            'tx_byte' => (int) ($iface['tx-byte'] ?? 0),
            'rx_packet' => (int) ($iface['rx-packet'] ?? 0),
            'tx_packet' => (int) ($iface['tx-packet'] ?? 0),
            'rx_error' => (int) ($iface['rx-error'] ?? 0),
            'tx_error' => (int) ($iface['tx-error'] ?? 0),
            'rx_drop' => (int) ($iface['rx-drop'] ?? 0),
            'tx_drop' => (int) ($iface['tx-drop'] ?? 0),
            'link_downs' => (int) ($iface['link-downs'] ?? 0),
            'last_link_down' => $iface['last-link-down'] ?? null,
            'last_link_up' => $iface['last-link-up'] ?? null,
            'comment' => $iface['comment'] ?? null,
            'auto_negotiation' => ($iface['auto-negotiation'] ?? '') === 'true',
            'speed' => $iface['speed'] ?? null,
            'full_duplex' => ($iface['full-duplex'] ?? '') === 'true',
            // Merged metadata
            'alias' => $meta?->alias,
            'tags' => $meta?->tags ?? [],
            'notes' => $meta?->notes,
            'is_monitored' => $meta?->is_monitored ?? true,
            // Router context
            'router_id' => $router->id,
            'router_name' => $router->display_identity,
            'site' => $router->site,
        ];
    }

    private function bulkTagOperation(int $routerId, string $interfaceName, string $operation, array $tags): ?array
    {
        $meta = MikrotikInterfaceMetadata::where('mikrotik_router_id', $routerId)
            ->where('interface_name', $interfaceName)
            ->first();

        if (! $meta) {
            $meta = MikrotikInterfaceMetadata::create([
                'tenant_id' => auth()->user()->tenant_id ?? 0,
                'mikrotik_router_id' => $routerId,
                'interface_name' => $interfaceName,
                'tags' => [],
            ]);
        }

        $currentTags = $meta->tags ?? [];

        if ($operation === 'add') {
            $currentTags = array_unique(array_merge($currentTags, $tags));
        } elseif ($operation === 'remove') {
            $currentTags = array_diff($currentTags, $tags);
        }

        $meta->update(['tags' => array_values($currentTags)]);

        return []; // No API params needed
    }

    private function touchMetadata(int $routerId, string $interfaceName): void
    {
        MikrotikInterfaceMetadata::where('mikrotik_router_id', $routerId)
            ->where('interface_name', $interfaceName)
            ->update(['updated_at' => now()]);
    }
}
