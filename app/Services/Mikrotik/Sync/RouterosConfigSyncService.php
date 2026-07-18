<?php

namespace App\Services\Mikrotik\Sync;

use App\Models\MikrotikRouter;
use App\Models\RouterosSyncedConfig;
use App\Models\RouterosSyncLog;
use App\Services\Mikrotik\Config\ConfigRepositoryService;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RouterOS Configuration Synchronization Engine.
 *
 * Pulls configuration from MikroTik routers and stores a local snapshot.
 * Detects new, changed, and removed items by comparing checksums.
 *
 * All MikroTik communication goes through RouterOS API Service.
 * No direct API connections are created.
 */
class RouterosConfigSyncService
{
    private const LOG_CHANNEL = 'mikrotik';

    private ?int $userId = null;

    private array $modules = [];

    /**
     * Run a sync operation.
     *
     * @param  MikrotikRouter|null  $router  Specific router, or null for all active
     * @param  array|null  $moduleKeys  Specific modules, or null for all enabled
     * @param  string  $syncType  'manual' or 'scheduled'
     */
    public function sync(
        ?MikrotikRouter $router = null,
        ?array $moduleKeys = null,
        string $syncType = 'manual',
        ?int $userId = null,
    ): array {
        $this->userId = $userId;
        $this->modules = $moduleKeys ?? ConfigSyncModuleRegistry::enabledKeys();

        $routers = $router ? [$router] : MikrotikRouter::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            return ['success' => true, 'message' => 'No active routers found', 'results' => []];
        }

        $results = [];
        foreach ($routers as $r) {
            $results[$r->id] = $this->syncRouter($r, $syncType);
        }

        $totalSuccess = collect($results)->where('success', true)->count();

        return [
            'success' => $totalSuccess === count($results),
            'message' => "{$totalSuccess}/".count($results).' routers synced successfully',
            'results' => $results,
        ];
    }

    /**
     * Sync a single router across all configured modules.
     */
    public function syncRouter(MikrotikRouter $router, string $syncType = 'manual', ?int $userId = null): array
    {
        $this->userId = $userId ?? $this->userId;
        if (empty($this->modules)) {
            $this->modules = ConfigSyncModuleRegistry::enabledKeys();
        }

        $startedAt = now();
        $syncLog = RouterosSyncLog::create([
            'tenant_id' => $router->tenant_id,
            'mikrotik_router_id' => $router->id,
            'user_id' => $this->userId,
            'sync_type' => $syncType,
            'modules_synced' => $this->modules,
            'started_at' => $startedAt,
            'status' => 'success',
        ]);

        $totals = [
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'deleted' => 0,
            'conflict' => 0,
        ];

        $failedModules = [];

        try {
            $service = new RouterConnectionService($router);

            foreach ($this->modules as $moduleKey) {
                $module = ConfigSyncModuleRegistry::get($moduleKey);
                if (! $module) {
                    continue;
                }

                $result = $this->syncModule($service, $router, $moduleKey, $module, $syncLog);

                $totals['total'] += $result['total'];
                $totals['new'] += $result['new'];
                $totals['updated'] += $result['updated'];
                $totals['deleted'] += $result['deleted'];
                $totals['conflict'] += $result['conflict'];

                if (! $result['success']) {
                    $failedModules[] = $moduleKey;
                }
            }

            $status = empty($failedModules) ? 'success' : 'partial';
            $errorMsg = empty($failedModules) ? null : 'Failed modules: '.implode(', ', $failedModules);

        } catch (\Exception $e) {
            $status = 'failed';
            $errorMsg = $e->getMessage();

            Log::channel(self::LOG_CHANNEL)->error('Config sync failed for router', [
                'router_id' => $router->id,
                'router_host' => $router->host,
                'error' => $errorMsg,
            ]);
        }

        $syncLog->update([
            'total_items' => $totals['total'],
            'new_items' => $totals['new'],
            'updated_items' => $totals['updated'],
            'deleted_items' => $totals['deleted'],
            'conflict_items' => $totals['conflict'],
            'status' => $status,
            'error_message' => $errorMsg,
            'completed_at' => now(),
            'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
        ]);

        return [
            'success' => $status !== 'failed',
            'router_id' => $router->id,
            'router_name' => $router->display_identity,
            'sync_log_id' => $syncLog->id,
            'status' => $status,
            'stats' => $totals,
            'duration_ms' => $syncLog->duration_ms,
            'failed_modules' => $failedModules,
        ];
    }

    /**
     * Sync a single module for a single router.
     */
    private function syncModule(
        RouterConnectionService $service,
        MikrotikRouter $router,
        string $moduleKey,
        array $module,
        RouterosSyncLog $syncLog,
    ): array {
        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($module['path']));

        if (! $result->isSuccess()) {
            Log::channel(self::LOG_CHANNEL)->warning("Config sync: failed to fetch {$moduleKey}", [
                'router_id' => $router->id,
                'error' => $result->getMessage(),
            ]);

            return ['success' => false, 'total' => 0, 'new' => 0, 'updated' => 0, 'deleted' => 0, 'conflict' => 0];
        }

        $remoteItems = $result->toArray();
        if (! is_array($remoteItems)) {
            $remoteItems = [];
        }

        // Handle singleton modules (like DNS) that return a single object
        if ($module['keyField'] === '__singleton__') {
            $remoteItems = [$remoteItems];
        }

        $remoteItemMap = [];
        foreach ($remoteItems as $item) {
            $itemId = ConfigSyncModuleRegistry::extractItemId($item, $moduleKey);
            if ($itemId !== '') {
                $remoteItemMap[$itemId] = $item;
            }
        }

        // Get existing local configs for this router+module
        $existingConfigs = RouterosSyncedConfig::where('tenant_id', $router->tenant_id)
            ->where('mikrotik_router_id', $router->id)
            ->where('module', $moduleKey)
            ->get()
            ->keyBy('item_id');

        $stats = ['success' => true, 'total' => count($remoteItemMap), 'new' => 0, 'updated' => 0, 'deleted' => 0, 'conflict' => 0];
        $syncedAt = now();

        DB::beginTransaction();
        try {
            // 1. Detect NEW and UPDATED items
            foreach ($remoteItemMap as $itemId => $remoteItem) {
                $checksum = ConfigSyncModuleRegistry::computeItemChecksum($remoteItem);
                $itemName = ConfigSyncModuleRegistry::extractItemName($remoteItem, $moduleKey);

                if (isset($existingConfigs[$itemId])) {
                    $existing = $existingConfigs[$itemId];

                    if ($existing->checksum !== $checksum) {
                        // Config changed — check if it was also changed locally
                        $existing->update([
                            'item_name' => $itemName,
                            'config_data' => $remoteItem,
                            'checksum' => $checksum,
                            'sync_log_id' => $syncLog->id,
                            'status' => 'active',
                            'last_synced_at' => $syncedAt,
                        ]);

                        // Create version snapshot
                        ConfigRepositoryService::createVersion(
                            $router,
                            $moduleKey,
                            $itemId,
                            $itemName,
                            $remoteItem,
                            'sync',
                            $this->userId,
                            null,
                            $syncLog->id,
                        );

                        $stats['updated']++;
                    }
                    // else: unchanged, skip

                    // Mark as seen (remove from existing so we can detect deletions)
                    $existingConfigs->forget($itemId);
                } else {
                    // New item
                    RouterosSyncedConfig::create([
                        'tenant_id' => $router->tenant_id,
                        'mikrotik_router_id' => $router->id,
                        'module' => $moduleKey,
                        'item_id' => $itemId,
                        'item_name' => $itemName,
                        'config_data' => $remoteItem,
                        'checksum' => $checksum,
                        'sync_log_id' => $syncLog->id,
                        'status' => 'active',
                        'last_synced_at' => $syncedAt,
                    ]);

                    // Create initial version snapshot
                    ConfigRepositoryService::createVersion(
                        $router,
                        $moduleKey,
                        $itemId,
                        $itemName,
                        $remoteItem,
                        'sync',
                        $this->userId,
                        null,
                        $syncLog->id,
                    );

                    $stats['new']++;
                }
            }

            // 2. Detect DELETED items (remaining in existing but not in remote)
            foreach ($existingConfigs as $existing) {
                if ($existing->status === 'active') {
                    $existing->update([
                        'status' => 'deleted',
                        'sync_log_id' => $syncLog->id,
                        'last_synced_at' => $syncedAt,
                    ]);

                    // Create deletion version snapshot
                    ConfigRepositoryService::createVersion(
                        $router,
                        $moduleKey,
                        $existing->item_id,
                        $existing->item_name,
                        $existing->config_data,
                        'sync',
                        $this->userId,
                        'Item removed from router',
                        $syncLog->id,
                    );

                    $stats['deleted']++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel(self::LOG_CHANNEL)->error("Config sync: DB error for {$moduleKey}", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'total' => $stats['total'], 'new' => 0, 'updated' => 0, 'deleted' => 0, 'conflict' => 0];
        }

        return $stats;
    }

    /**
     * Get sync summary for the dashboard.
     */
    public static function getSyncSummary(): array
    {
        $lastSyncPerRouter = RouterosSyncLog::select('mikrotik_router_id', DB::raw('MAX(id) as last_log_id'))
            ->groupBy('mikrotik_router_id')
            ->get()
            ->pluck('last_log_id')
            ->toArray();

        $lastLogs = RouterosSyncLog::whereIn('id', $lastSyncPerRouter)
            ->with('router')
            ->get()
            ->keyBy('mikrotik_router_id');

        $routers = MikrotikRouter::where('is_active', true)->get();

        $routerStatuses = [];
        foreach ($routers as $router) {
            $log = $lastLogs->get($router->id);
            $routerStatuses[] = [
                'router' => $router,
                'last_sync' => $log,
                'last_sync_ago' => $log?->completed_at?->diffForHumans() ?? 'Never',
                'last_sync_status' => $log?->status ?? 'unknown',
            ];
        }

        $conflictCount = RouterosSyncedConfig::where('status', 'conflict')->count();
        $totalSynced = RouterosSyncedConfig::where('status', 'active')->count();
        $recentFailures = RouterosSyncLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $recentManual = RouterosSyncLog::where('sync_type', 'manual')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [
            'router_statuses' => $routerStatuses,
            'conflict_count' => $conflictCount,
            'total_synced_items' => $totalSynced,
            'recent_failures_24h' => $recentFailures,
            'recent_manual_syncs_24h' => $recentManual,
        ];
    }

    /**
     * Get recent sync logs with pagination.
     */
    public static function getRecentLogs(int $limit = 25): LengthAwarePaginator
    {
        return RouterosSyncLog::with('router')
            ->latest('started_at')
            ->paginate($limit);
    }

    /**
     * Get conflict items for a specific router+module.
     */
    public static function getConflicts(?int $routerId = null, ?string $module = null): Collection
    {
        $query = RouterosSyncedConfig::where('status', 'conflict')->with('router');

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($module) {
            $query->where('module', $module);
        }

        return $query->get();
    }
}
