<?php

namespace App\Services\Mikrotik\Config;

use App\Models\ConfigVersion;
use App\Models\MikrotikRouter;
use App\Models\RouterosSyncedConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Configuration Repository — central versioning engine.
 *
 * Creates immutable version snapshots when configs change.
 * All version reads go through this service.
 */
class ConfigRepositoryService
{
    /**
     * Create a new version for a config item.
     *
     * Called by ConfigSyncModuleRegistry when a change is detected during sync,
     * or manually when a user pushes a config change.
     */
    public static function createVersion(
        MikrotikRouter $router,
        string $module,
        string $itemId,
        string $itemName,
        array $configData,
        string $changeSource = 'sync',
        ?int $userId = null,
        ?string $changeSummary = null,
        ?int $syncLogId = null,
    ): ConfigVersion {
        $tenantId = $router->tenant_id;
        $checksum = RouterosSyncedConfig::computeChecksum($configData);

        // Get previous version number for this item
        $prevVersion = ConfigVersion::where('tenant_id', $tenantId)
            ->where('mikrotik_router_id', $router->id)
            ->where('module', $module)
            ->where('item_id', $itemId)
            ->max('version');

        $newVersion = ($prevVersion ?? 0) + 1;

        // Compute diff from previous version
        $diff = null;
        if ($prevVersion) {
            $prevConfig = ConfigVersion::where('tenant_id', $tenantId)
                ->where('mikrotik_router_id', $router->id)
                ->where('module', $module)
                ->where('item_id', $itemId)
                ->where('version', $prevVersion)
                ->value('config_data');

            if ($prevConfig) {
                $diff = self::computeDiff($prevConfig, $configData);
            }
        }

        return ConfigVersion::create([
            'tenant_id' => $tenantId,
            'mikrotik_router_id' => $router->id,
            'module' => $module,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'version' => $newVersion,
            'config_data' => $configData,
            'checksum' => $checksum,
            'change_source' => $changeSource,
            'user_id' => $userId,
            'change_summary' => $changeSummary ?? self::generateSummary($diff),
            'diff_from_previous' => $diff,
            'sync_log_id' => $syncLogId,
            'created_at' => now(),
        ]);
    }

    /**
     * Get version history for a specific item (router + module + itemId).
     */
    public static function getItemVersions(
        int $routerId,
        string $module,
        string $itemId,
        int $limit = 50,
    ): LengthAwarePaginator {
        return ConfigVersion::where('mikrotik_router_id', $routerId)
            ->where('module', $module)
            ->where('item_id', $itemId)
            ->orderByDesc('version')
            ->with(['router', 'user'])
            ->paginate($limit);
    }

    /**
     * Get latest version for an item.
     */
    public static function getLatestVersion(
        int $routerId,
        string $module,
        string $itemId,
    ): ?ConfigVersion {
        return ConfigVersion::where('mikrotik_router_id', $routerId)
            ->where('module', $module)
            ->where('item_id', $itemId)
            ->orderByDesc('version')
            ->with(['router', 'user'])
            ->first();
    }

    /**
     * Compare two versions side-by-side.
     */
    public static function compareVersions(int $fromVersionId, int $toVersionId): array
    {
        $from = ConfigVersion::with(['router', 'user'])->findOrFail($fromVersionId);
        $to = ConfigVersion::with(['router', 'user'])->findOrFail($toVersionId);

        $diff = self::computeDiff($from->config_data, $to->config_data);

        return [
            'from' => $from,
            'to' => $to,
            'diff' => $diff,
        ];
    }

    /**
     * Get recent changes across all modules/routers.
     */
    public static function getRecentChanges(
        ?int $routerId = null,
        ?string $module = null,
        ?string $source = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 30,
    ): LengthAwarePaginator {
        $query = ConfigVersion::with(['router', 'user']);

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($module) {
            $query->where('module', $module);
        }
        if ($source) {
            $query->where('change_source', $source);
        }
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        return $query->orderByDesc('created_at')->paginate($limit);
    }

    /**
     * Get grouped changes per module for a router (for overview).
     */
    public static function getModuleChangeSummary(?int $routerId = null): Collection
    {
        $query = ConfigVersion::select(
            'mikrotik_router_id',
            'module',
            DB::raw('COUNT(*) as total_versions'),
            DB::raw('MAX(version) as latest_version'),
            DB::raw('MAX(created_at) as last_changed_at'),
            DB::raw('MIN(created_at) as first_changed_at'),
        );

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }

        return $query->groupBy('mikrotik_router_id', 'module')
            ->with('router')
            ->orderByDesc('last_changed_at')
            ->get();
    }

    /**
     * Get items that have multiple versions (changed at least once).
     */
    public static function getChangedItems(
        ?int $routerId = null,
        ?string $module = null,
        int $limit = 50,
    ): Collection {
        $query = ConfigVersion::select(
            'mikrotik_router_id',
            'module',
            'item_id',
            'item_name',
            DB::raw('MAX(version) as latest_version'),
            DB::raw('COUNT(*) as total_versions'),
            DB::raw('MAX(created_at) as last_changed_at'),
        )
            ->groupBy('mikrotik_router_id', 'module', 'item_id', 'item_name')
            ->having('total_versions', '>', 1);

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($module) {
            $query->where('module', $module);
        }

        return $query->orderByDesc('last_changed_at')
            ->with('router')
            ->limit($limit)
            ->get();
    }

    /**
     * Get repository statistics.
     */
    public static function getStats(?int $routerId = null): array
    {
        $query = ConfigVersion::query();
        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }

        $totalVersions = (clone $query)->count();
        $uniqueItems = (clone $query)->distinct()
            ->select(['mikrotik_router_id', 'module', 'item_id'])
            ->get()
            ->count();
        $changedItems = (clone $query)
            ->select('mikrotik_router_id', 'module', 'item_id')
            ->groupBy('mikrotik_router_id', 'module', 'item_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $sourcesBreakdown = (clone $query)
            ->select('change_source', DB::raw('COUNT(*) as cnt'))
            ->groupBy('change_source')
            ->pluck('cnt', 'change_source')
            ->toArray();

        $recent24h = (clone $query)->where('created_at', '>=', now()->subHours(24))->count();

        return [
            'total_versions' => $totalVersions,
            'unique_items' => $uniqueItems,
            'changed_items' => $changedItems,
            'sources_breakdown' => $sourcesBreakdown,
            'recent_24h' => $recent24h,
        ];
    }

    /**
     * Get a specific version by ID.
     */
    public static function getVersionById(int $id): ?ConfigVersion
    {
        return ConfigVersion::with(['router', 'user', 'syncLog'])->find($id);
    }

    /**
     * Get all versions for a specific sync log.
     */
    public static function getVersionsBySyncLog(int $syncLogId): Collection
    {
        return ConfigVersion::where('sync_log_id', $syncLogId)
            ->with(['router', 'user'])
            ->orderBy('module')
            ->orderBy('item_name')
            ->get();
    }

    /**
     * Compute a structured diff between two config snapshots.
     *
     * Returns added, removed, and changed keys with before/after values.
     */
    private static function computeDiff(array $old, array $new): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $inOld = array_key_exists($key, $old);
            $inNew = array_key_exists($key, $new);

            if ($inOld && ! $inNew) {
                $removed[$key] = $old[$key];
            } elseif (! $inOld && $inNew) {
                $added[$key] = $new[$key];
            } elseif ($old[$key] !== $new[$key]) {
                $changed[$key] = ['from' => $old[$key], 'to' => $new[$key]];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'summary' => [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'changed_count' => count($changed),
            ],
        ];
    }

    /**
     * Auto-generate a human-readable summary from diff data.
     */
    private static function generateSummary(?array $diff): ?string
    {
        if (! $diff) {
            return 'Initial version';
        }

        $parts = [];
        if ($diff['summary']['added_count'] > 0) {
            $parts[] = $diff['summary']['added_count'].' added';
        }
        if ($diff['summary']['removed_count'] > 0) {
            $parts[] = $diff['summary']['removed_count'].' removed';
        }
        if ($diff['summary']['changed_count'] > 0) {
            $parts[] = $diff['summary']['changed_count'].' changed';
        }

        return $parts ? implode(', ', $parts) : 'No changes';
    }
}
