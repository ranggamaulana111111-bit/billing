<?php

namespace App\Services\Mikrotik\Config;

use App\Models\MikrotikRouter;
use App\Models\NetworkConfigAuditLog;
use App\Models\RouterosSyncedConfig;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Network Configuration Center Service.
 *
 * Handles CRUD operations for Bridge, VLAN, and IP Address
 * via RouterOS API Service. All changes are audited and versioned.
 */
class NetworkConfigService
{
    private const LOG_CHANNEL = 'mikrotik';

    // ── Resource Definitions ──

    private const RESOURCES = [
        'bridge' => [
            'path' => '/interface/bridge',
            'nameField' => 'name',
            'label' => 'Bridge',
            'fields' => ['name', 'protocol-mode', 'priority', 'port-cost-mode', 'vlan-filtering', 'comment'],
            'createFields' => ['name', 'protocol-mode', 'priority', 'port-cost-mode', 'vlan-filtering', 'comment'],
            'updateFields' => ['name', 'protocol-mode', 'priority', 'port-cost-mode', 'vlan-filtering', 'comment'],
        ],
        'bridge_port' => [
            'path' => '/interface/bridge/port',
            'nameField' => 'interface',
            'label' => 'Bridge Port',
            'fields' => ['interface', 'bridge', 'priority', 'path-cost', 'horizon', 'comment'],
        ],
        'vlan' => [
            'path' => '/interface/vlan',
            'nameField' => 'name',
            'label' => 'VLAN',
            'fields' => ['name', 'vlan-id', 'interface', 'comment'],
            'createFields' => ['name', 'vlan-id', 'interface', 'comment'],
            'updateFields' => ['name', 'vlan-id', 'interface', 'comment'],
        ],
        'ip_address' => [
            'path' => '/ip/address',
            'nameField' => 'address',
            'label' => 'IP Address',
            'fields' => ['address', 'interface', 'network', 'comment', 'disabled'],
            'createFields' => ['address', 'interface', 'comment'],
            'updateFields' => ['address', 'interface', 'comment'],
        ],
    ];

    /**
     * List items from a RouterOS resource path.
     */
    public static function list(MikrotikRouter $router, string $resourceType, array $query = []): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'items' => [], 'error' => "Unknown resource: {$resourceType}"];
        }

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($def['path'], $query));

            if (! $result->isSuccess()) {
                return ['success' => false, 'items' => [], 'error' => $result->getMessage()];
            }

            $items = $result->toArray();
            if (! is_array($items)) {
                $items = [];
            }

            return ['success' => true, 'items' => $items, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('NetworkConfig list failed', [
                'router_id' => $router->id,
                'resource' => $resourceType,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a single item by ID.
     */
    public static function get(MikrotikRouter $router, string $resourceType, string $itemId): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'item' => null, 'error' => "Unknown resource: {$resourceType}"];
        }

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($def['path'].'/'.$itemId));

            if (! $result->isSuccess()) {
                return ['success' => false, 'item' => null, 'error' => $result->getMessage()];
            }

            $data = $result->toArray();
            $item = is_array($data) && count($data) > 0 ? $data[0] : $data;

            return ['success' => true, 'item' => $item, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'item' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a new item on the router.
     */
    public static function create(MikrotikRouter $router, string $resourceType, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $createFields = $def['createFields'] ?? $def['fields'];
        $filtered = array_filter(array_flip($createFields) ? array_intersect_key($data, array_flip($createFields)) : $data);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut($def['path'], $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, '', $data['name'] ?? '', 'create', null, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            $newData = $result->toArray();
            $newId = $newData['ret'] ?? $newData['.id'] ?? '';

            // Get the created item
            $created = null;
            if ($newId) {
                $get = self::get($router, $resourceType, $newId);
                $created = $get['item'] ?? null;
            }

            self::audit($router, $resourceType, $newId, $data['name'] ?? '', 'create', null, $created ?? $data, 'success', $userId);

            // Sync to repository
            self::syncToRepository($router, $resourceType, $newId, $data['name'] ?? '', $created ?? $data, $userId);

            return ['success' => true, 'id' => $newId, 'item' => $created, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('NetworkConfig create failed', [
                'router_id' => $router->id,
                'resource' => $resourceType,
                'error' => $e->getMessage(),
            ]);

            self::audit($router, $resourceType, '', $data['name'] ?? '', 'create', null, $data, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an existing item on the router.
     */
    public static function update(MikrotikRouter $router, string $resourceType, string $itemId, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        // Get current state for audit
        $before = self::get($router, $resourceType, $itemId);
        $beforeData = $before['item'] ?? null;

        $updateFields = $def['updateFields'] ?? $def['fields'];
        $filtered = array_filter(array_flip($updateFields) ? array_intersect_key($data, array_flip($updateFields)) : $data);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch($def['path'].'/'.$itemId, $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, $itemId, $data['name'] ?? '', 'update', $beforeData, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            // Get updated item
            $after = self::get($router, $resourceType, $itemId);
            $afterData = $after['item'] ?? $data;

            self::audit($router, $resourceType, $itemId, $data['name'] ?? ($afterData['name'] ?? ''), 'update', $beforeData, $afterData, 'success', $userId);

            // Sync to repository
            $name = $data['name'] ?? ($afterData['name'] ?? $afterData[$def['nameField']] ?? '');
            self::syncToRepository($router, $resourceType, $itemId, $name, $afterData, $userId);

            return ['success' => true, 'item' => $afterData, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('NetworkConfig update failed', [
                'router_id' => $router->id,
                'resource' => $resourceType,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            self::audit($router, $resourceType, $itemId, '', 'update', $beforeData, $data, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete an item from the router.
     */
    public static function delete(MikrotikRouter $router, string $resourceType, string $itemId, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        // Get current state for audit
        $before = self::get($router, $resourceType, $itemId);
        $beforeData = $before['item'] ?? null;
        $itemName = $beforeData[$def['nameField']] ?? $itemId;

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete($def['path'].'/'.$itemId));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, $itemId, $itemName, 'delete', $beforeData, null, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            self::audit($router, $resourceType, $itemId, $itemName, 'delete', $beforeData, null, 'success', $userId);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error('NetworkConfig delete failed', [
                'router_id' => $router->id,
                'resource' => $resourceType,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            self::audit($router, $resourceType, $itemId, $itemName, 'delete', $beforeData, null, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enable or disable an item.
     */
    public static function toggle(MikrotikRouter $router, string $resourceType, string $itemId, bool $disable, ?int $userId = null): array
    {
        return self::update($router, $resourceType, $itemId, ['disabled' => $disable ? 'true' : 'false'], $userId);
    }

    /**
     * Bulk operation on multiple items.
     */
    public static function bulkOperation(
        MikrotikRouter $router,
        string $resourceType,
        string $action,
        array $itemIds,
        ?array $data = null,
        ?int $userId = null,
    ): array {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($itemIds as $itemId) {
            $result = match ($action) {
                'enable' => self::toggle($router, $resourceType, $itemId, false, $userId),
                'disable' => self::toggle($router, $resourceType, $itemId, true, $userId),
                'delete' => self::delete($router, $resourceType, $itemId, $userId),
                'update' => self::update($router, $resourceType, $itemId, $data ?? [], $userId),
                default => ['success' => false, 'error' => "Unknown action: {$action}"],
            };

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "{$itemId}: {$result['error']}";
            }
        }

        return $results;
    }

    /**
     * Get dashboard stats for all network config resources.
     */
    public static function getDashboardStats(?int $routerId = null): array
    {
        $resources = ['bridge', 'vlan', 'ip_address'];
        $stats = [];

        foreach ($resources as $res) {
            $def = self::RESOURCES[$res];
            $query = RouterosSyncedConfig::where('module', $res);

            if ($routerId) {
                $query->where('mikrotik_router_id', $routerId);
            }

            $total = (clone $query)->count();
            $active = (clone $query)->where('status', 'active')->count();

            $stats[$res] = [
                'total' => $total,
                'active' => $active,
                'label' => $def['label'],
            ];
        }

        // Last sync
        $lastSync = RouterosSyncedConfig::query();
        if ($routerId) {
            $lastSync->where('mikrotik_router_id', $routerId);
        }
        $lastSyncTime = $lastSync->max('last_synced_at');

        // Recent audit logs
        $recentLogs = NetworkConfigAuditLog::with(['router', 'user'])
            ->when($routerId, fn ($q) => $q->where('mikrotik_router_id', $routerId))
            ->latest('created_at')
            ->limit(10)
            ->get();

        return [
            'resources' => $stats,
            'last_sync_at' => $lastSyncTime ? Carbon::parse($lastSyncTime) : null,
            'recent_logs' => $recentLogs,
        ];
    }

    /**
     * Get audit logs for a resource type.
     */
    public static function getAuditLogs(
        ?string $resourceType = null,
        ?int $routerId = null,
        ?string $action = null,
        int $limit = 30,
    ): LengthAwarePaginator {
        return NetworkConfigAuditLog::with(['router', 'user'])
            ->when($resourceType, fn ($q) => $q->where('resource_type', $resourceType))
            ->when($routerId, fn ($q) => $q->where('mikrotik_router_id', $routerId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->latest('created_at')
            ->paginate($limit);
    }

    /**
     * Sync an item to the Configuration Repository.
     */
    private static function syncToRepository(
        MikrotikRouter $router,
        string $resourceType,
        string $itemId,
        string $itemName,
        array $configData,
        ?int $userId,
    ): void {
        try {
            ConfigRepositoryService::createVersion(
                $router,
                $resourceType,
                $itemId,
                $itemName,
                $configData,
                'api',
                $userId,
            );
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->warning('Failed to sync to repository', [
                'router_id' => $router->id,
                'resource' => $resourceType,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create an audit log entry.
     */
    private static function audit(
        MikrotikRouter $router,
        string $resourceType,
        string $itemId,
        string $itemName,
        string $action,
        ?array $beforeData,
        ?array $afterData,
        string $status,
        ?int $userId,
        ?string $error = null,
    ): void {
        try {
            $summary = self::generateAuditSummary($action, $beforeData, $afterData);

            NetworkConfigAuditLog::create([
                'tenant_id' => $router->tenant_id,
                'mikrotik_router_id' => $router->id,
                'resource_type' => $resourceType,
                'item_id' => $itemId,
                'item_name' => $itemName,
                'action' => $action,
                'before_data' => $beforeData,
                'after_data' => $afterData,
                'summary' => $summary,
                'status' => $status,
                'user_id' => $userId,
                'api_error' => $error,
            ]);
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->warning('Audit log failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate human-readable audit summary.
     */
    private static function generateAuditSummary(string $action, ?array $before, ?array $after): string
    {
        return match ($action) {
            'create' => 'Item created',
            'delete' => 'Item deleted',
            'update' => self::diffSummary($before, $after),
            'enable' => 'Item enabled',
            'disable' => 'Item disabled',
            default => ucfirst($action),
        };
    }

    private static function diffSummary(?array $before, ?array $after): string
    {
        if (! $before || ! $after) {
            return 'Item updated';
        }

        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allKeys as $key) {
            if (str_starts_with($key, '.')) {
                continue;
            }
            $inOld = array_key_exists($key, $before);
            $inNew = array_key_exists($key, $after);
            if ($inOld && $inNew && $before[$key] !== $after[$key]) {
                $changed[] = "{$key}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changed ? implode(', ', array_slice($changed, 0, 5)) : 'Item updated';
    }

    /**
     * Get resource definitions.
     */
    public static function getResourceDef(string $resourceType): ?array
    {
        return self::RESOURCES[$resourceType] ?? null;
    }

    public static function allResourceTypes(): array
    {
        return array_keys(self::RESOURCES);
    }
}
