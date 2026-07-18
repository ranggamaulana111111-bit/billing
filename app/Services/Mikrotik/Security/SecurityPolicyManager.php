<?php

namespace App\Services\Mikrotik\Security;

use App\Models\MikrotikRouter;
use App\Models\NetworkConfigAuditLog;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Support\Collection;

class SecurityPolicyManager
{
    private const RESOURCES = [
        'firewall_filter' => [
            'path' => '/ip/firewall/filter',
            'label' => 'Firewall Filter',
            'nameField' => 'comment',
            'createFields' => ['chain', 'action', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'src-address-list', 'dst-address-list', 'connection-state', 'comment', 'disabled', 'place-before'],
            'allFields' => ['chain', 'action', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'src-address-list', 'dst-address-list', 'connection-state', 'jump-target', 'log', 'log-prefix', 'comment', 'disabled', 'place-before'],
        ],
        'firewall_nat' => [
            'path' => '/ip/firewall/nat',
            'label' => 'NAT',
            'nameField' => 'comment',
            'createFields' => ['chain', 'action', 'to-addresses', 'to-ports', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'connection-type', 'comment', 'disabled', 'place-before'],
            'allFields' => ['chain', 'action', 'to-addresses', 'to-ports', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'connection-type', 'log', 'log-prefix', 'comment', 'disabled', 'place-before'],
        ],
        'mangle' => [
            'path' => '/ip/firewall/mangle',
            'label' => 'Mangle',
            'nameField' => 'comment',
            'createFields' => ['chain', 'action', 'new-packet-marks', 'new-connection-mark', 'new-routing-mark', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'passthrough', 'dscp', 'ttl', 'comment', 'disabled', 'place-before'],
            'allFields' => ['chain', 'action', 'new-packet-marks', 'new-connection-mark', 'new-routing-mark', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'passthrough', 'dscp', 'ttl', 'connection-mark', 'packet-mark', 'comment', 'disabled', 'place-before'],
        ],
        'address_list' => [
            'path' => '/ip/firewall/address-list',
            'label' => 'Address List',
            'nameField' => 'list',
            'createFields' => ['list', 'address', 'timeout', 'comment'],
            'allFields' => ['list', 'address', 'timeout', 'comment'],
        ],
        'raw' => [
            'path' => '/ip/firewall/raw',
            'label' => 'Raw Firewall',
            'nameField' => 'comment',
            'createFields' => ['chain', 'action', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'comment', 'disabled', 'place-before'],
            'allFields' => ['chain', 'action', 'src-address', 'dst-address', 'src-port', 'dst-port', 'protocol', 'in-interface', 'out-interface', 'log', 'log-prefix', 'comment', 'disabled', 'place-before'],
        ],
        'layer7' => [
            'path' => '/ip/firewall/l7',
            'label' => 'Layer7 Protocol',
            'nameField' => 'name',
            'createFields' => ['name', 'regexp', 'comment'],
            'allFields' => ['name', 'regexp', 'comment'],
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

        if (isset($filtered['place-before']) && $filtered['place-before'] === '') {
            unset($filtered['place-before']);
        }

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
        unset($data['.id'], $data['bytes'], $data['packets'], $data['rate'], $data['bytes-in'], $data['bytes-out']);
        $data['comment'] = ($data['comment'] ?? '').' [copy]';
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

    // ── Dashboard & Stats ──

    public static function getDashboardStats(MikrotikRouter $router): array
    {
        $stats = [];
        $counts = [];

        foreach (self::RESOURCES as $key => $def) {
            $result = self::list($router, $key);
            $items = $result['items'] ?? [];
            $counts[$key] = count($items);
            $stats[$key] = [
                'label' => $def['label'],
                'total' => count($items),
                'disabled' => count(array_filter($items, fn ($i) => ($i['disabled'] ?? 'false') === 'true')),
                'dynamic' => count(array_filter($items, fn ($i) => ($i['dynamic'] ?? 'false') === 'true')),
            ];
        }

        return [
            'counts' => $counts,
            'stats' => $stats,
            'total_rules' => array_sum($counts),
            'total_disabled' => array_sum(array_column($stats, 'disabled')),
        ];
    }

    public static function validatePolicies(MikrotikRouter $router): array
    {
        $recommendations = [];

        // ── Check for unused rules (counter = 0) ──
        foreach (['firewall_filter', 'firewall_nat', 'mangle'] as $key) {
            $result = self::list($router, $key);
            $items = $result['items'] ?? [];
            foreach ($items as $item) {
                $packets = (int) ($item['packets'] ?? '0');
                $bytes = (int) ($item['bytes'] ?? '0');
                $comment = $item['comment'] ?? '';
                $id = $item['.id'] ?? '';
                if ($packets === 0 && $bytes === 0 && ! empty($comment) && ! str_contains(strtolower($comment), 'fasttrack')) {
                    $recommendations[] = [
                        'type' => 'unused',
                        'severity' => 'info',
                        'resource' => self::RESOURCES[$key]['label'],
                        'item_id' => $id,
                        'message' => "Rule \"{$comment}\" has 0 hits (packets/bytes). Consider reviewing if still needed.",
                    ];
                }
            }
        }

        // ── Check for duplicate rules ──
        foreach (['firewall_filter', 'firewall_nat', 'mangle', 'raw'] as $key) {
            $result = self::list($router, $key);
            $items = $result['items'] ?? [];
            $seen = [];
            foreach ($items as $item) {
                $sig = self::ruleSignature($item, $key);
                if (isset($seen[$sig])) {
                    $comment = $item['comment'] ?? '(no comment)';
                    $recommendations[] = [
                        'type' => 'duplicate',
                        'severity' => 'warning',
                        'resource' => self::RESOURCES[$key]['label'],
                        'item_id' => $item['.id'] ?? '',
                        'message' => "Possible duplicate rule: \"{$comment}\" (similar to {$seen[$sig]}).",
                    ];
                }
                $seen[$sig] = $item['.id'] ?? '';
            }
        }

        // ── Check for unused address lists ──
        $addrResult = self::list($router, 'address_list');
        $addrItems = $addrResult['items'] ?? [];
        $addrLists = [];
        foreach ($addrItems as $item) {
            $listName = $item['list'] ?? '';
            if (! isset($addrLists[$listName])) {
                $addrLists[$listName] = 0;
            }
            $addrLists[$listName]++;
        }

        $filterResult = self::list($router, 'firewall_filter');
        $natResult = self::list($router, 'firewall_nat');
        $referencedLists = [];
        foreach (array_merge($filterResult['items'] ?? [], $natResult['items'] ?? []) as $rule) {
            foreach (['src-address-list', 'dst-address-list'] as $field) {
                if (! empty($rule[$field])) {
                    $referencedLists[$rule[$field]] = true;
                }
            }
        }
        foreach (array_keys($addrLists) as $listName) {
            if (! empty($listName) && ! isset($referencedLists[$listName])) {
                $recommendations[] = [
                    'type' => 'unreferenced',
                    'severity' => 'info',
                    'resource' => 'Address List',
                    'item_id' => $listName,
                    'message' => "Address list \"{$listName}\" ({$addrLists[$listName]} entries) is not referenced by any firewall rule.",
                ];
            }
        }

        // ── Check for unused Layer7 protocols ──
        $l7Result = self::list($router, 'layer7');
        $l7Items = $l7Result['items'] ?? [];
        $filterItems = $filterResult['items'] ?? [];
        $mangleResult = self::list($router, 'mangle');
        $mangleItems = $mangleResult['items'] ?? [];
        foreach ($l7Items as $l7) {
            $l7Name = $l7['name'] ?? '';
            $referenced = false;
            foreach (array_merge($filterItems, $mangleItems) as $rule) {
                if (isset($rule['layer7-protocol']) && $rule['layer7-protocol'] === $l7Name) {
                    $referenced = true;
                    break;
                }
            }
            if (! $referenced) {
                $recommendations[] = [
                    'type' => 'unreferenced',
                    'severity' => 'info',
                    'resource' => 'Layer7 Protocol',
                    'item_id' => $l7['.id'] ?? '',
                    'message' => "Layer7 protocol \"{$l7Name}\" is not referenced by any firewall or mangle rule.",
                ];
            }
        }

        return $recommendations;
    }

    private static function ruleSignature(array $item, string $resourceType): string
    {
        $key = match ($resourceType) {
            'firewall_filter', 'raw' => $item['chain'].$item['action'].($item['src-address'] ?? '').($item['dst-address'] ?? '').($item['src-port'] ?? '').($item['dst-port'] ?? '').($item['protocol'] ?? '').($item['in-interface'] ?? '').($item['out-interface'] ?? ''),
            'firewall_nat' => $item['chain'].$item['action'].($item['to-addresses'] ?? '').($item['to-ports'] ?? '').($item['src-address'] ?? '').($item['dst-address'] ?? '').($item['dst-port'] ?? '').($item['protocol'] ?? ''),
            'mangle' => $item['chain'].$item['action'].($item['new-packet-marks'] ?? '').($item['new-connection-mark'] ?? '').($item['src-address'] ?? '').($item['dst-address'] ?? '').($item['dst-port'] ?? ''),
            default => json_encode($item),
        };

        return md5($key);
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
            'resource_type' => 'security_policy.'.$resourceType,
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
        $query = NetworkConfigAuditLog::where('resource_type', 'like', 'security_policy.%')
            ->orderByDesc('created_at');

        if ($routerId) {
            $query->where('mikrotik_router_id', $routerId);
        }
        if ($resourceType) {
            $query->where('resource_type', 'security_policy.'.$resourceType);
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
}
