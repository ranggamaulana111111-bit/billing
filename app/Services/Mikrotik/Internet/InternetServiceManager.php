<?php

namespace App\Services\Mikrotik\Internet;

use App\Models\MikrotikRouter;
use App\Models\NetworkConfigAuditLog;
use App\Models\RouterosSyncedConfig;
use App\Models\RouterosSyncLog;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Internet Service Manager — centralized service for ISP service modules.
 *
 * Handles CRUD for IP Pool, DHCP Server, DHCP Lease, PPP Profile,
 * PPP Secret, Hotspot Server, Hotspot User, Hotspot Profile via RouterOS API.
 * All changes are audited.
 */
class InternetServiceManager
{
    private const LOG_CHANNEL = 'mikrotik';

    private const RESOURCES = [
        'ip_pool' => [
            'path' => '/ip/pool',
            'nameField' => 'name',
            'label' => 'IP Pool',
            'createFields' => ['name', 'ranges', 'comment'],
        ],
        'dhcp_server' => [
            'path' => '/ip/dhcp-server',
            'nameField' => 'name',
            'label' => 'DHCP Server',
            'createFields' => ['name', 'interface', 'address-pool', 'lease-time', 'disabled', 'comment'],
        ],
        'dhcp_lease' => [
            'path' => '/ip/dhcp-server/lease',
            'nameField' => 'address',
            'label' => 'DHCP Lease',
            'createFields' => ['address', 'mac-address', 'server', 'comment'],
        ],
        'dhcp_lease_static' => [
            'path' => '/ip/dhcp-server/lease',
            'nameField' => 'address',
            'label' => 'Static DHCP Lease',
            'createFields' => ['address', 'mac-address', 'server', 'comment'],
        ],
        'ppp_profile' => [
            'path' => '/ppp/profile',
            'nameField' => 'name',
            'label' => 'PPP Profile',
            'createFields' => ['name', 'local-address', 'remote-address', 'rate-limit', 'only-one', 'comment'],
        ],
        'ppp_secret' => [
            'path' => '/ppp/secret',
            'nameField' => 'name',
            'label' => 'PPP Secret',
            'createFields' => ['name', 'password', 'service', 'profile', 'local-address', 'remote-address', 'comment'],
        ],
        'ppp_active' => [
            'path' => '/ppp/active',
            'nameField' => 'name',
            'label' => 'PPP Active Session',
            'createFields' => [],
        ],
        'hotspot_server' => [
            'path' => '/ip/hotspot',
            'nameField' => 'name',
            'label' => 'Hotspot Server',
            'createFields' => ['name', 'interface', 'address-pool', 'profile', 'disabled', 'comment'],
        ],
        'hotspot_user' => [
            'path' => '/ip/hotspot/user',
            'nameField' => 'name',
            'label' => 'Hotspot User',
            'createFields' => ['name', 'password', 'server', 'profile', 'limit-uptime', 'comment'],
        ],
        'hotspot_active' => [
            'path' => '/ip/hotspot/active',
            'nameField' => 'user',
            'label' => 'Hotspot Active Session',
            'createFields' => [],
        ],
        'hotspot_profile' => [
            'path' => '/ip/hotspot/user/profile',
            'nameField' => 'name',
            'label' => 'Hotspot Profile',
            'createFields' => ['name', 'rate-limit', 'session-timeout', 'comment'],
        ],
    ];

    // ── CRUD ──

    public static function list(MikrotikRouter $router, string $resource, array $query = []): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'items' => [], 'error' => "Unknown resource: {$resource}"];
        }

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($def['path'], $query));

            if (! $result->isSuccess()) {
                return ['success' => false, 'items' => [], 'error' => $result->getMessage()];
            }

            $items = $result->toArray();

            return ['success' => true, 'items' => is_array($items) ? $items : [], 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error("InternetService list {$resource} failed", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    public static function get(MikrotikRouter $router, string $resource, string $itemId): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'item' => null, 'error' => "Unknown resource: {$resource}"];
        }

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($def['path'].'/'.$itemId));

            if (! $result->isSuccess()) {
                return ['success' => false, 'item' => null, 'error' => $result->getMessage()];
            }

            $data = $result->toArray();
            $item = is_array($data) && isset($data[0]) ? $data[0] : $data;

            return ['success' => true, 'item' => $item, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'item' => null, 'error' => $e->getMessage()];
        }
    }

    public static function create(MikrotikRouter $router, string $resource, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resource}"];
        }

        $createFields = $def['createFields'] ?? [];
        $filtered = $createFields ? array_filter(array_intersect_key($data, array_flip($createFields))) : $data;

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut($def['path'], $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resource, '', $data['name'] ?? $data['address'] ?? '', 'create', null, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            $newData = $result->toArray();
            $newId = $newData['ret'] ?? $newData['.id'] ?? '';

            $created = null;
            if ($newId) {
                $get = self::get($router, $resource, $newId);
                $created = $get['item'] ?? null;
            }

            self::audit($router, $resource, $newId, $data['name'] ?? $data['address'] ?? '', 'create', null, $created ?? $data, 'success', $userId);

            return ['success' => true, 'id' => $newId, 'item' => $created, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error("InternetService create {$resource} failed", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            self::audit($router, $resource, '', $data['name'] ?? '', 'create', null, $data, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function update(MikrotikRouter $router, string $resource, string $itemId, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resource}"];
        }

        $before = self::get($router, $resource, $itemId);

        $createFields = $def['createFields'] ?? [];
        $filtered = $createFields ? array_filter(array_intersect_key($data, array_flip($createFields))) : array_filter($data);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch($def['path'].'/'.$itemId, $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resource, $itemId, $data['name'] ?? '', 'update', $before['item'] ?? null, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            $after = self::get($router, $resource, $itemId);
            self::audit($router, $resource, $itemId, $data['name'] ?? '', 'update', $before['item'] ?? null, $after['item'] ?? $data, 'success', $userId);

            return ['success' => true, 'item' => $after['item'] ?? null, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error("InternetService update {$resource} failed", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function delete(MikrotikRouter $router, string $resource, string $itemId, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resource}"];
        }

        $before = self::get($router, $resource, $itemId);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete($def['path'].'/'.$itemId));

            if (! $result->isSuccess()) {
                self::audit($router, $resource, $itemId, '', 'delete', $before['item'] ?? null, null, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            self::audit($router, $resource, $itemId, '', 'delete', $before['item'] ?? null, null, 'success', $userId);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error("InternetService delete {$resource} failed", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function toggle(MikrotikRouter $router, string $resource, string $itemId, bool $disable, ?int $userId = null): array
    {
        return self::update($router, $resource, $itemId, ['disabled' => $disable ? 'true' : 'false'], $userId);
    }

    // ── Bulk Operations ──

    public static function bulkOperation(MikrotikRouter $router, string $resource, string $action, array $itemIds, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = match ($action) {
                'enable' => self::toggle($router, $resource, $itemId, false, $userId),
                'disable' => self::toggle($router, $resource, $itemId, true, $userId),
                'delete' => self::delete($router, $resource, $itemId, $userId),
                default => ['success' => false],
            };
            $result['success'] ? $success++ : $failed++;
        }

        self::audit($router, $resource, '', "Bulk {$action}", 'bulk', null, ['count' => count($itemIds)], 'success', $userId);

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    // ── Dashboard Stats ──

    public static function getDashboardStats(?MikrotikRouter $router = null): array
    {
        if (! $router) {
            $router = MikrotikRouter::where('is_active', true)->first();
        }
        if (! $router) {
            return self::emptyStats();
        }

        try {
            $service = new RouterConnectionService($router);

            $pools = self::fetchSafe($service, '/ip/pool');
            $dhcpServers = self::fetchSafe($service, '/ip/dhcp-server');
            $leases = self::fetchSafe($service, '/ip/dhcp-server/lease');
            $pppProfiles = self::fetchSafe($service, '/ppp/profile');
            $pppSecrets = self::fetchSafe($service, '/ppp/secret');
            $pppActive = self::fetchSafe($service, '/ppp/active');
            $hotspotServers = self::fetchSafe($service, '/ip/hotspot');
            $hotspotActive = self::fetchSafe($service, '/ip/hotspot/active');
            $hotspotUsers = self::fetchSafe($service, '/ip/hotspot/user');

            $totalPools = count($pools);
            $totalDhcpServers = count($dhcpServers);
            $totalLeases = count($leases);
            $totalPppSecrets = count($pppSecrets);
            $pppOnline = count($pppActive);
            $pppOffline = max(0, $totalPppSecrets - $pppOnline);
            $totalHotspotServers = count($hotspotServers);
            $hotspotActiveCount = count($hotspotActive);
            $totalHotspotUsers = count($hotspotUsers);

            $poolUsage = self::computePoolUsage($pools, $leases);
            $dhcpLeasesPerServer = self::computeLeasesPerServer($dhcpServers, $leases);

            $lastSync = RouterosSyncedConfig::where('mikrotik_router_id', $router->id)
                ->latest('last_synced_at')
                ->first();

            return [
                'router' => $router,
                'total_pools' => $totalPools,
                'total_dhcp_servers' => $totalDhcpServers,
                'total_leases' => $totalLeases,
                'total_ppp_profiles' => count($pppProfiles),
                'total_ppp_secrets' => $totalPppSecrets,
                'ppp_online' => $pppOnline,
                'ppp_offline' => $pppOffline,
                'total_hotspot_servers' => $totalHotspotServers,
                'total_hotspot_users' => $totalHotspotUsers,
                'hotspot_active' => $hotspotActiveCount,
                'total_sessions' => $pppOnline + $hotspotActiveCount,
                'pool_usage' => $poolUsage,
                'dhcp_leases_per_server' => $dhcpLeasesPerServer,
                'last_sync_at' => $lastSync?->last_synced_at,
            ];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('InternetService dashboard stats failed', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return self::emptyStats();
        }
    }

    // ── Pool Usage Computation ──

    public static function getPoolUsageDetails(MikrotikRouter $router): array
    {
        $service = new RouterConnectionService($router);
        $pools = self::fetchSafe($service, '/ip/pool');
        $leases = self::fetchSafe($service, '/ip/dhcp-server/lease');
        $pppActive = self::fetchSafe($service, '/ppp/active');

        $usage = [];
        foreach ($pools as $pool) {
            $poolName = $pool['name'] ?? '';
            $ranges = $pool['ranges'] ?? '';
            $totalIps = self::countIpsInRanges($ranges);
            $dhcpUsed = 0;
            $pppUsed = 0;
            foreach ($leases as $lease) {
                if (($lease['status'] ?? '') === 'used' && isset($lease['address'])) {
                    $dhcpUsed++;
                }
            }
            foreach ($pppActive as $session) {
                if (isset($session['address'])) {
                    $pppUsed++;
                }
            }
            $used = $dhcpUsed + $pppUsed;
            $free = max(0, $totalIps - $used);
            $pct = $totalIps > 0 ? round(($used / $totalIps) * 100, 1) : 0;

            $usage[] = [
                'name' => $poolName,
                'ranges' => $ranges,
                'total_ips' => $totalIps,
                'dhcp_used' => $dhcpUsed,
                'ppp_used' => $pppUsed,
                'total_used' => $used,
                'free' => $free,
                'percent' => $pct,
                'warning' => $pct > 85,
                'critical' => $pct > 95,
                '.id' => $pool['.id'] ?? '',
                'disabled' => ($pool['disabled'] ?? 'false') === 'true',
            ];
        }

        return $usage;
    }

    // ── Audit ──

    public static function audit(MikrotikRouter $router, string $resourceType, string $itemId, string $itemName, string $action, ?array $before, ?array $after, string $status, ?int $userId = null, ?string $error = null): void
    {
        $summary = strtoupper($action).': '.$resourceType;
        if ($itemName) {
            $summary .= ' ('.$itemName.')';
        }

        NetworkConfigAuditLog::create([
            'mikrotik_router_id' => $router->id,
            'resource_type' => 'internet_service.'.$resourceType,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'action' => $action,
            'before_data' => $before,
            'after_data' => $after,
            'summary' => $summary,
            'status' => $status,
            'user_id' => $userId,
            'api_error' => $error,
            'created_at' => now(),
        ]);
    }

    public static function getAuditLogs(?string $resourceType = null, ?int $routerId = null, ?string $action = null, int $limit = 30): LengthAwarePaginator
    {
        $query = NetworkConfigAuditLog::with(['router', 'user'])
            ->where('resource_type', 'like', 'internet_service.%')
            ->latest('created_at');

        if ($resourceType) {
            $query->where('resource_type', 'internet_service.'.$resourceType);
        }
        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($action) {
            $query->where('action', $action);
        }

        return $query->paginate($limit);
    }

    public static function getResourceDef(string $resource): ?array
    {
        return self::RESOURCES[$resource] ?? null;
    }

    public static function getResourceDefs(): array
    {
        return self::RESOURCES;
    }

    // ── Helpers ──

    private static function fetchSafe(RouterConnectionService $service, string $path): array
    {
        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($path));

        if (! $result->isSuccess()) {
            return [];
        }

        $data = $result->toArray();

        return is_array($data) ? $data : [];
    }

    /**
     * RouterOS returns single-object endpoints (e.g. /system/resource) either as a
     * flat associative array or as a list containing one associative array. Normalize
     * both shapes into the first record (associative array) so callers can read keys.
     */
    private static function extractFirstRecord(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $first = reset($data);

        return is_array($first) ? $first : $data;
    }

    private static function countIpsInRanges(string $ranges): int
    {
        $count = 0;
        $lines = explode("\n", $ranges);
        foreach ($lines as $line) {
            $line = trim($line, " \t\r\n");
            if ($line === '') {
                continue;
            }
            $parts = explode('-', $line, 2);
            if (count($parts) === 2) {
                $start = ip2long(trim($parts[0]));
                $end = ip2long(trim($parts[1]));
                if ($start !== false && $end !== false && $end >= $start) {
                    $count += ($end - $start + 1);
                }
            } else {
                $count++;
            }
        }

        return $count;
    }

    private static function computePoolUsage(array $pools, array $leases): array
    {
        $usedCount = 0;
        foreach ($leases as $lease) {
            if (($lease['status'] ?? '') === 'used') {
                $usedCount++;
            }
        }

        $totalIps = 0;
        foreach ($pools as $pool) {
            $totalIps += self::countIpsInRanges($pool['ranges'] ?? '');
        }

        return [
            'total_pools' => count($pools),
            'total_ips' => $totalIps,
            'used_ips' => $usedCount,
            'free_ips' => max(0, $totalIps - $usedCount),
            'percent' => $totalIps > 0 ? round(($usedCount / $totalIps) * 100, 1) : 0,
        ];
    }

    private static function computeLeasesPerServer(array $dhcpServers, array $leases): array
    {
        $result = [];
        foreach ($dhcpServers as $server) {
            $name = $server['name'] ?? 'unknown';
            $count = 0;
            foreach ($leases as $lease) {
                if (($lease['server'] ?? '') === $name && ($lease['status'] ?? '') === 'used') {
                    $count++;
                }
            }
            $result[] = [
                'name' => $name,
                'interface' => $server['interface'] ?? '',
                'lease_count' => $count,
                'disabled' => ($server['disabled'] ?? 'false') === 'true',
            ];
        }

        return $result;
    }

    // ── IP Conflict Detection ──

    public static function detectIpConflicts(MikrotikRouter $router): array
    {
        $service = new RouterConnectionService($router);
        $leases = self::fetchSafe($service, '/ip/dhcp-server/lease');
        $pppActive = self::fetchSafe($service, '/ppp/active');
        $arp = self::fetchSafe($service, '/ip/arp');

        $ipMap = [];
        $conflicts = [];

        foreach ($leases as $lease) {
            $ip = $lease['address'] ?? '';
            if ($ip === '') {
                continue;
            }
            $mac = $lease['mac-address'] ?? '';
            $owner = 'dhcp:'.($lease['server'] ?? 'unknown');

            if (isset($ipMap[$ip])) {
                $conflicts[] = [
                    'ip' => $ip,
                    'entries' => [
                        ['type' => 'dhcp', 'mac' => $mac, 'owner' => $owner, 'status' => $lease['status'] ?? 'unknown'],
                        $ipMap[$ip],
                    ],
                ];
            } else {
                $ipMap[$ip] = ['type' => 'dhcp', 'mac' => $mac, 'owner' => $owner, 'status' => $lease['status'] ?? 'unknown'];
            }
        }

        foreach ($pppActive as $session) {
            $ip = $session['address'] ?? '';
            if ($ip === '') {
                continue;
            }
            $callerId = $session['caller-id'] ?? '';
            $owner = 'ppp:'.$session['name'];

            if (isset($ipMap[$ip])) {
                $existing = $ipMap[$ip];
                $isSameType = str_starts_with($existing['type'], 'ppp');
                $conflicts[] = [
                    'ip' => $ip,
                    'entries' => [
                        ['type' => 'ppp', 'mac' => $callerId, 'owner' => $owner, 'status' => 'active'],
                        $existing,
                    ],
                ];
            } else {
                $ipMap[$ip] = ['type' => 'ppp', 'mac' => $callerId, 'owner' => $owner, 'status' => 'active'];
            }
        }

        foreach ($arp as $entry) {
            $ip = $entry['address'] ?? '';
            $mac = $entry['mac-address'] ?? '';
            if ($ip === '' || $mac === '') {
                continue;
            }

            if (isset($ipMap[$ip]) && $ipMap[$ip]['mac'] !== '' && $ipMap[$ip]['mac'] !== $mac) {
                $alreadyListed = false;
                foreach ($conflicts as $c) {
                    if ($c['ip'] === $ip) {
                        $alreadyListed = true;
                        break;
                    }
                }
                if (! $alreadyListed) {
                    $conflicts[] = [
                        'ip' => $ip,
                        'entries' => [
                            ['type' => 'arp', 'mac' => $mac, 'owner' => 'arp', 'status' => 'resolved'],
                            $ipMap[$ip],
                        ],
                    ];
                }
            }
        }

        return ['success' => true, 'conflicts' => $conflicts, 'total' => count($conflicts)];
    }

    // ── Hotspot Sub-Resources ──

    public static function getHotspotActive(MikrotikRouter $router): array
    {
        return self::listRaw($router, '/ip/hotspot/active');
    }

    public static function getHotspotHosts(MikrotikRouter $router): array
    {
        return self::listRaw($router, '/ip/hotspot/host');
    }

    public static function getHotspotCookies(MikrotikRouter $router): array
    {
        return self::listRaw($router, '/ip/hotspot/cookie');
    }

    public static function getHotspotSessions(MikrotikRouter $router): array
    {
        return self::listRaw($router, '/ip/hotspot/active');
    }

    public static function getHotspotLoginHistory(MikrotikRouter $router): array
    {
        return self::listRaw($router, '/ip/hotspot/active');
    }

    // ── PPP Active with Details ──

    public static function getPppActiveDetails(MikrotikRouter $router): array
    {
        $service = new RouterConnectionService($router);
        $active = self::fetchSafe($service, '/ppp/active');
        $secrets = self::fetchSafe($service, '/ppp/secret');

        $secretMap = [];
        foreach ($secrets as $s) {
            $secretMap[$s['name'] ?? ''] = $s;
        }

        $details = [];
        foreach ($active as $session) {
            $name = $session['name'] ?? '';
            $secret = $secretMap[$name] ?? null;
            $details[] = [
                '.id' => $session['.id'] ?? '',
                'name' => $name,
                'service' => $session['service'] ?? 'pppoe',
                'caller-id' => $session['caller-id'] ?? '',
                'address' => $session['address'] ?? '',
                'uptime' => $session['uptime'] ?? '',
                'encoding' => $session['encoding'] ?? '',
                'rate' => $session['rate'] ?? '',
                'profile' => $secret['profile'] ?? $session['profile'] ?? '',
                'disabled' => $secret['disabled'] ?? 'false',
                'comment' => $secret['comment'] ?? '',
            ];
        }

        return ['success' => true, 'items' => $details, 'error' => null];
    }

    // ── Raw List (for sub-resources not in RESOURCES) ──

    public static function listRaw(MikrotikRouter $router, string $path, array $query = []): array
    {
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($path, $query));

            if (! $result->isSuccess()) {
                return ['success' => false, 'items' => [], 'error' => $result->getMessage()];
            }

            $items = $result->toArray();

            return ['success' => true, 'items' => is_array($items) ? $items : [], 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error("InternetService listRaw {$path} failed", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Bulk Comment ──

    public static function bulkComment(MikrotikRouter $router, string $resource, array $itemIds, string $comment, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = self::update($router, $resource, $itemId, ['comment' => $comment], $userId);
            $result['success'] ? $success++ : $failed++;
        }

        self::audit($router, $resource, '', 'Bulk comment', 'bulk', null, ['count' => count($itemIds), 'comment' => $comment], 'success', $userId);

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    // ── Bulk Refresh (re-fetch from router) ──

    public static function refresh(MikrotikRouter $router, string $resource): array
    {
        $def = self::RESOURCES[$resource] ?? null;
        if (! $def) {
            return ['success' => false, 'items' => [], 'error' => "Unknown resource: {$resource}"];
        }

        return self::list($router, $resource);
    }

    // ── Monitoring Data ──

    public static function getMonitoringData(?MikrotikRouter $router = null): array
    {
        if (! $router) {
            $router = MikrotikRouter::where('is_active', true)->first();
        }
        if (! $router) {
            return self::emptyMonitoring();
        }

        try {
            $service = new RouterConnectionService($router);

            $interfaces = self::fetchSafe($service, '/interface');
            $resources = self::fetchSafe($service, '/system/resource');
            $sysResource = self::extractFirstRecord($resources);

            $lastSyncLog = RouterosSyncLog::where('mikrotik_router_id', $router->id)
                ->latest('created_at')
                ->first();

            $recentChanges = NetworkConfigAuditLog::where('mikrotik_router_id', $router->id)
                ->where('resource_type', 'like', 'internet_service.%')
                ->latest('created_at')
                ->limit(10)
                ->get();

            $dhcpServers = self::fetchSafe($service, '/ip/dhcp-server');
            $leases = self::fetchSafe($service, '/ip/dhcp-server/lease');
            $pppSecrets = self::fetchSafe($service, '/ppp/secret');
            $pppActive = self::fetchSafe($service, '/ppp/active');
            $hotspotServers = self::fetchSafe($service, '/ip/hotspot');
            $hotspotActive = self::fetchSafe($service, '/ip/hotspot/active');
            $hotspotUsers = self::fetchSafe($service, '/ip/hotspot/user');
            $pools = self::fetchSafe($service, '/ip/pool');

            $moduleStats = [
                'ip_pool' => ['total' => count($pools), 'path' => '/ip/pool'],
                'dhcp_server' => ['total' => count($dhcpServers), 'path' => '/ip/dhcp-server'],
                'dhcp_lease' => ['total' => count($leases), 'active' => count(array_filter($leases, fn ($l) => ($l['status'] ?? '') === 'used')), 'path' => '/ip/dhcp-server/lease'],
                'ppp_profile' => ['total' => count(self::fetchSafe($service, '/ppp/profile')), 'path' => '/ppp/profile'],
                'ppp_secret' => ['total' => count($pppSecrets), 'online' => count($pppActive), 'path' => '/ppp/secret'],
                'hotspot_server' => ['total' => count($hotspotServers), 'path' => '/ip/hotspot'],
                'hotspot_user' => ['total' => count($hotspotUsers), 'active' => count($hotspotActive), 'path' => '/ip/hotspot/user'],
                'hotspot_profile' => ['total' => count(self::fetchSafe($service, '/ip/hotspot/user/profile')), 'path' => '/ip/hotspot/user/profile'],
            ];

            return [
                'router' => $router,
                'router_status' => $router->status,
                'router_version' => $sysResource['version'] ?? $router->routeros_version ?? '—',
                'router_uptime' => $sysResource['uptime'] ?? '—',
                'router_cpu' => $sysResource['cpu-load'] ?? '—',
                'router_memory' => $sysResource['total-memory'] ?? '—',
                'router_free_memory' => $sysResource['free-memory'] ?? '—',
                'interfaces' => $interfaces,
                'module_stats' => $moduleStats,
                'last_sync' => $lastSyncLog,
                'recent_changes' => $recentChanges,
                'total_sessions' => count($pppActive) + count($hotspotActive),
                'ppp_active' => $pppActive,
                'hotspot_active' => $hotspotActive,
            ];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('InternetService monitoring data failed', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return self::emptyMonitoring();
        }
    }

    // ── Dashboard Enhanced (with conflict + low pool warnings) ──

    public static function getEnhancedDashboardStats(?MikrotikRouter $router = null): array
    {
        $base = self::getDashboardStats($router);

        if (! $router || ! isset($base['router'])) {
            return array_merge($base, ['pool_warnings' => [], 'conflicts' => [], 'conflict_count' => 0, 'low_pool_count' => 0]);
        }

        $poolUsage = self::getPoolUsageDetails($router);
        $poolWarnings = array_filter($poolUsage, fn ($p) => $p['warning'] || $p['critical']);

        $conflictResult = self::detectIpConflicts($router);

        return array_merge($base, [
            'pool_usage_details' => $poolUsage,
            'pool_warnings' => array_values($poolWarnings),
            'low_pool_count' => count($poolWarnings),
            'conflicts' => $conflictResult['conflicts'] ?? [],
            'conflict_count' => $conflictResult['total'] ?? 0,
        ]);
    }

    // ── Helpers ──

    private static function emptyMonitoring(): array
    {
        return [
            'router' => null,
            'router_status' => 'unknown',
            'router_version' => '—',
            'router_uptime' => '—',
            'router_cpu' => '—',
            'router_memory' => '—',
            'router_free_memory' => '—',
            'interfaces' => [],
            'module_stats' => [],
            'last_sync' => null,
            'recent_changes' => [],
            'total_sessions' => 0,
        ];
    }

    private static function emptyStats(): array
    {
        return [
            'router' => null,
            'total_pools' => 0,
            'total_dhcp_servers' => 0,
            'total_leases' => 0,
            'total_ppp_profiles' => 0,
            'total_ppp_secrets' => 0,
            'ppp_online' => 0,
            'ppp_offline' => 0,
            'total_hotspot_servers' => 0,
            'total_hotspot_users' => 0,
            'hotspot_active' => 0,
            'total_sessions' => 0,
            'pool_usage' => ['total_pools' => 0, 'total_ips' => 0, 'used_ips' => 0, 'free_ips' => 0, 'percent' => 0],
            'dhcp_leases_per_server' => [],
            'last_sync_at' => null,
        ];
    }
}
