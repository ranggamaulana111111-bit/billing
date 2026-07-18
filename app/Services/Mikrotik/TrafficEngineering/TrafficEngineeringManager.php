<?php

namespace App\Services\Mikrotik\TrafficEngineering;

use App\Models\MikrotikRouter;
use App\Models\NetworkConfigAuditLog;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Support\Collection;

class TrafficEngineeringManager
{
    private const RESOURCES = [
        'simple_queue' => [
            'path' => '/queue/simple',
            'label' => 'Simple Queue',
            'nameField' => 'name',
            'createFields' => ['name', 'target', 'parent', 'max-limit', 'limit-at', 'queue', 'priority', 'burst-limit', 'burst-threshold', 'burst-time', 'min-limit', 'comment', 'disabled', 'time', 'total-queue-limit-threshold'],
            'allFields' => ['name', 'target', 'parent', 'max-limit', 'limit-at', 'queue', 'priority', 'burst-limit', 'burst-threshold', 'burst-time', 'min-limit', 'comment', 'disabled', 'time', 'total-queue-limit-threshold', 'bytes', 'rate', 'packets'],
        ],
        'queue_tree' => [
            'path' => '/queue/tree',
            'label' => 'Queue Tree',
            'nameField' => 'name',
            'createFields' => ['name', 'parent', 'packet-mark', 'queue', 'limit-at', 'max-limit', 'priority', 'comment', 'disabled', 'burst-limit', 'burst-threshold', 'burst-time'],
            'allFields' => ['name', 'parent', 'packet-mark', 'queue', 'limit-at', 'max-limit', 'priority', 'comment', 'disabled', 'burst-limit', 'burst-threshold', 'burst-time', 'bytes', 'rate', 'packets'],
        ],
        'queue_type' => [
            'path' => '/queue/type',
            'label' => 'Queue Type',
            'nameField' => 'name',
            'createFields' => ['name', 'kind', 'pfifo-limit', 'pfifo-packet-limit', 'red-avg-packet', 'red-max-threshold', 'red-min-threshold', 'sfq-allot', 'sfq-perturb', 'cake-diffserv', 'cake-flowmode', 'cake-nat', 'cake-memlimit', 'cake-fail-reason'],
            'allFields' => ['name', 'kind', 'pfifo-limit', 'pfifo-packet-limit', 'red-avg-packet', 'red-max-threshold', 'red-min-threshold', 'sfq-allot', 'sfq-perturb', 'cake-diffserv', 'cake-flowmode', 'cake-nat', 'cake-memlimit', 'cake-fail-reason'],
        ],
    ];

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

            return ['success' => true, 'items' => is_array($items) ? $items : [], 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

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

            return ['success' => true, 'item' => $result->first(), 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'item' => null, 'error' => $e->getMessage()];
        }
    }

    public static function create(MikrotikRouter $router, string $resourceType, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $filtered = array_intersect_key($data, array_flip($def['createFields']));
        $filtered = array_filter($filtered, fn ($v) => $v !== '' && $v !== null);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut($def['path'], $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, '', $data[$def['nameField']] ?? 'new', 'create', null, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            $created = $result->first();
            $createdId = $created['.id'] ?? '';

            self::audit($router, $resourceType, $createdId, $data[$def['nameField']] ?? 'new', 'create', null, $created ?? $data, 'success', $userId);

            return ['success' => true, 'item' => $created, 'error' => null];
        } catch (\Exception $e) {
            self::audit($router, $resourceType, '', $data[$def['nameField']] ?? 'new', 'create', null, $data, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function update(MikrotikRouter $router, string $resourceType, string $itemId, array $data, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $before = self::get($router, $resourceType, $itemId);
        $beforeItem = $before['item'] ?? null;

        $filtered = array_intersect_key($data, array_flip($def['allFields']));
        $filtered = array_filter($filtered, fn ($v) => $v !== '' && $v !== null);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch($def['path'].'/'.$itemId, $filtered));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, $itemId, $data[$def['nameField']] ?? $itemId, 'update', $beforeItem, $data, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            $after = self::get($router, $resourceType, $itemId);
            $afterItem = $after['item'] ?? null;

            self::audit($router, $resourceType, $itemId, $data[$def['nameField']] ?? $itemId, 'update', $beforeItem, $afterItem ?? $data, 'success', $userId);

            return ['success' => true, 'item' => $afterItem, 'error' => null];
        } catch (\Exception $e) {
            self::audit($router, $resourceType, $itemId, $data[$def['nameField']] ?? $itemId, 'update', $beforeItem, $data, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function delete(MikrotikRouter $router, string $resourceType, string $itemId, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $before = self::get($router, $resourceType, $itemId);
        $beforeItem = $before['item'] ?? null;

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete($def['path'].'/'.$itemId));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'delete', $beforeItem, null, 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'delete', $beforeItem, null, 'success', $userId);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'delete', $beforeItem, null, 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function toggle(MikrotikRouter $router, string $resourceType, string $itemId, bool $disable, ?int $userId = null): array
    {
        return self::update($router, $resourceType, $itemId, ['disabled' => $disable ? 'true' : 'false'], $userId);
    }

    public static function move(MikrotikRouter $router, string $resourceType, string $itemId, string $putBefore, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $before = self::get($router, $resourceType, $itemId);
        $beforeItem = $before['item'] ?? null;

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch($def['path'].'/'.$itemId, ['place-before' => $putBefore]));

            if (! $result->isSuccess()) {
                self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'move', $beforeItem, ['place-before' => $putBefore], 'failed', $userId, $result->getMessage());

                return ['success' => false, 'error' => $result->getMessage()];
            }

            self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'move', $beforeItem, ['place-before' => $putBefore], 'success', $userId);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            self::audit($router, $resourceType, $itemId, $beforeItem[$def['nameField']] ?? $itemId, 'move', $beforeItem, ['place-before' => $putBefore], 'failed', $userId, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function copy(MikrotikRouter $router, string $resourceType, string $itemId, ?int $userId = null): array
    {
        $def = self::RESOURCES[$resourceType] ?? null;
        if (! $def) {
            return ['success' => false, 'error' => "Unknown resource: {$resourceType}"];
        }

        $original = self::get($router, $resourceType, $itemId);
        if (! $original['success'] || ! $original['item']) {
            return ['success' => false, 'error' => 'Original item not found'];
        }

        $data = $original['item'];
        unset($data['.id'], $data['bytes'], $data['packets'], $data['rate']);
        $data['name'] = ($data['name'] ?? 'copy').' [copy]';
        $data['disabled'] = 'true';

        return self::create($router, $resourceType, $data, $userId);
    }

    public static function bulkOperation(MikrotikRouter $router, string $resourceType, string $action, array $itemIds, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = match ($action) {
                'enable' => self::toggle($router, $resourceType, $itemId, false, $userId),
                'disable' => self::toggle($router, $resourceType, $itemId, true, $userId),
                'delete' => self::delete($router, $resourceType, $itemId, $userId),
                default => ['success' => false],
            };

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    public static function bulkComment(MikrotikRouter $router, string $resourceType, array $itemIds, string $comment, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = self::update($router, $resourceType, $itemId, ['comment' => $comment], $userId);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    public static function bulkChangePriority(MikrotikRouter $router, string $resourceType, array $itemIds, string $priority, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = self::update($router, $resourceType, $itemId, ['priority' => $priority], $userId);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    public static function bulkChangeQueueType(MikrotikRouter $router, string $resourceType, array $itemIds, string $queueType, ?int $userId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($itemIds as $itemId) {
            $result = self::update($router, $resourceType, $itemId, ['queue' => $queueType], $userId);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => count($itemIds)];
    }

    // ── Interfaces & Extra ──

    public static function getInterfaces(MikrotikRouter $router): array
    {
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface'));

            if (! $result->isSuccess()) {
                return ['success' => false, 'items' => [], 'error' => $result->getMessage()];
            }

            $items = $result->toArray();

            return ['success' => true, 'items' => is_array($items) ? $items : [], 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    public static function getInterfaceStats(MikrotikRouter $router): array
    {
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface', ['stats' => ''], []));

            if (! $result->isSuccess()) {
                return ['success' => false, 'items' => [], 'error' => $result->getMessage()];
            }

            $items = $result->toArray();

            return ['success' => true, 'items' => is_array($items) ? $items : [], 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Dashboard & Stats ──

    public static function getDashboardStats(MikrotikRouter $router): array
    {
        $stats = [];
        $counts = [];

        foreach (self::RESOURCES as $key => $def) {
            $result = self::list($router, $key);
            $items = $result['items'] ?? [];
            $counts[$key] = count($items);
            $disabled = count(array_filter($items, fn ($i) => ($i['disabled'] ?? 'false') === 'true'));
            $stats[$key] = [
                'label' => $def['label'],
                'total' => count($items),
                'disabled' => $disabled,
                'active' => count($items) - $disabled,
            ];
        }

        $interfaces = self::getInterfaces($router);
        $ifItems = $interfaces['items'] ?? [];

        $totalRx = 0;
        $totalTx = 0;
        $topInterface = '';
        $topRate = 0;
        foreach ($ifItems as $if) {
            $rate = (int) ($if['rate'] ?? '0');
            $totalRx += $rate;
            if ($rate > $topRate) {
                $topRate = $rate;
                $topInterface = $if['name'] ?? '';
            }
        }

        return [
            'counts' => $counts,
            'stats' => $stats,
            'total_queues' => array_sum($counts),
            'total_disabled' => array_sum(array_column($stats, 'disabled')),
            'total_active' => array_sum(array_column($stats, 'active')),
            'interface_count' => count($ifItems),
            'total_rate' => $totalRx,
            'top_interface' => $topInterface,
            'top_rate' => $topRate,
        ];
    }

    // ── Policy Validation ──

    public static function validatePolicies(MikrotikRouter $router): array
    {
        $recommendations = [];

        $sqResult = self::list($router, 'simple_queue');
        $sqItems = $sqResult['items'] ?? [];

        $qtResult = self::list($router, 'queue_tree');
        $qtItems = $qtResult['items'] ?? [];

        $qtypeResult = self::list($router, 'queue_type');
        $qtypeItems = $qtypeResult['items'] ?? [];

        $mangleResult = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/firewall/mangle'));
            if ($result->isSuccess()) {
                $mangleResult = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        // Check queues without target
        foreach ($sqItems as $item) {
            if (empty($item['target'])) {
                $recommendations[] = [
                    'type' => 'no_target',
                    'severity' => 'warning',
                    'resource' => 'Simple Queue',
                    'item_id' => $item['.id'] ?? '',
                    'message' => 'Queue "'.($item['name'] ?? '(unnamed)').'" has no target defined.',
                ];
            }
        }

        // Check duplicate queues
        $seen = [];
        foreach ($sqItems as $item) {
            $sig = md5(($item['target'] ?? '').($item['max-limit'] ?? '').($item['min-limit'] ?? '').($item['queue'] ?? ''));
            if (isset($seen[$sig])) {
                $recommendations[] = [
                    'type' => 'duplicate',
                    'severity' => 'warning',
                    'resource' => 'Simple Queue',
                    'item_id' => $item['.id'] ?? '',
                    'message' => 'Queue "'.($item['name'] ?? '(unnamed)').'" may duplicate '.$seen[$sig].'.',
                ];
            }
            $seen[$sig] = $item['name'] ?? '';
        }

        // Check inactive queues (0 bytes, 0 packets, enabled)
        foreach ($sqItems as $item) {
            $packets = (int) ($item['packets'] ?? '0');
            $bytes = (int) ($item['bytes'] ?? '0');
            if ($packets === 0 && $bytes === 0 && ($item['disabled'] ?? 'false') === 'false') {
                $comment = $item['comment'] ?? '';
                $recommendations[] = [
                    'type' => 'inactive',
                    'severity' => 'info',
                    'resource' => 'Simple Queue',
                    'item_id' => $item['.id'] ?? '',
                    'message' => 'Queue "'.($item['name'] ?? $comment ?: '(unnamed)').'" is enabled but has 0 traffic.',
                ];
            }
        }

        // Check unused queue types
        $usedTypes = [];
        foreach (array_merge($sqItems, $qtItems) as $item) {
            $qt = $item['queue'] ?? '';
            if (! empty($qt)) {
                $usedTypes[$qt] = true;
            }
        }
        foreach ($qtypeItems as $item) {
            $name = $item['name'] ?? '';
            if (! empty($name) && ! isset($usedTypes[$name]) && ($item['kind'] ?? '') !== 'pfifo') {
                $recommendations[] = [
                    'type' => 'unused_type',
                    'severity' => 'info',
                    'resource' => 'Queue Type',
                    'item_id' => $item['.id'] ?? '',
                    'message' => 'Queue Type "'.$name.'" is not used by any queue.',
                ];
            }
        }

        // Check missing parent queue
        $queueNames = array_column($sqItems, 'name');
        foreach ($sqItems as $item) {
            $parent = $item['parent'] ?? '';
            if (! empty($parent) && ! in_array($parent, $queueNames)) {
                $recommendations[] = [
                    'type' => 'missing_parent',
                    'severity' => 'warning',
                    'resource' => 'Simple Queue',
                    'item_id' => $item['.id'] ?? '',
                    'message' => 'Queue "'.($item['name'] ?? '(unnamed)').'" references parent "'.$parent.'" which does not exist.',
                ];
            }
        }

        // Check packet marks not found
        $usedMarks = [];
        foreach ($qtItems as $item) {
            $pm = $item['packet-mark'] ?? '';
            if (! empty($pm)) {
                $usedMarks[$pm] = true;
            }
        }
        foreach ($usedMarks as $mark => $_) {
            $found = false;
            foreach ($mangleResult as $m) {
                if (($m['new-packet-marks'] ?? '') === $mark) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $recommendations[] = [
                    'type' => 'missing_mark',
                    'severity' => 'warning',
                    'resource' => 'Queue Tree',
                    'item_id' => '',
                    'message' => 'Packet mark "'.$mark.'" is used in Queue Tree but no mangle rule creates it.',
                ];
            }
        }

        return $recommendations;
    }

    // ── Audit ──

    public static function audit(
        MikrotikRouter $router,
        string $resourceType,
        string $itemId,
        string $itemName,
        string $action,
        ?array $before,
        ?array $after,
        string $status,
        ?int $userId,
        ?string $error = null
    ): void {
        NetworkConfigAuditLog::create([
            'mikrotik_router_id' => $router->id,
            'resource_type' => 'traffic_eng.'.$resourceType,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'action' => $action,
            'before_data' => $before,
            'after_data' => $after,
            'summary' => strtoupper($action).': '.$resourceType,
            'status' => $status,
            'user_id' => $userId,
            'api_error' => $error,
        ]);
    }

    // ── Audit Logs ──

    public static function getAuditLogs(
        ?int $routerId = null,
        ?string $resourceType = null,
        ?string $action = null,
        int $limit = 30,
    ): Collection {
        $query = NetworkConfigAuditLog::where('resource_type', 'like', 'traffic_eng.%')
            ->orderByDesc('created_at');

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($resourceType) {
            $query->where('resource_type', 'traffic_eng.'.$resourceType);
        }
        if ($action) {
            $query->where('action', $action);
        }

        return $query->limit($limit)->get();
    }

    // ── Helpers ──

    public static function getResourceDefs(): array
    {
        return self::RESOURCES;
    }

    public static function getResourceDef(string $resourceType): ?array
    {
        return self::RESOURCES[$resourceType] ?? null;
    }

    private static function parseRate(string $rate): float
    {
        $rate = trim($rate);
        if ($rate === '' || $rate === '0') {
            return 0;
        }
        $multipliers = ['k' => 1000, 'm' => 1000000, 'g' => 1000000000, 'K' => 1000, 'M' => 1000000, 'G' => 1000000000];
        $unit = strtolower(substr($rate, -1));
        if (isset($multipliers[$unit])) {
            return (float) substr($rate, 0, -1) * $multipliers[$unit];
        }

        return (float) $rate;
    }

    public static function formatRate(float $bps): string
    {
        if ($bps >= 1000000000) {
            return number_format($bps / 1000000000, 2).' Gbps';
        }
        if ($bps >= 1000000) {
            return number_format($bps / 1000000, 2).' Mbps';
        }
        if ($bps >= 1000) {
            return number_format($bps / 1000, 1).' Kbps';
        }

        return number_format($bps, 0).' bps';
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes).' B';
    }
}
