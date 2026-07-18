<?php

namespace App\Services\Mikrotik\Sync;

/**
 * Registry of all RouterOS configuration modules that can be synchronized.
 *
 * Each module defines:
 *  - path:       RouterOS REST API path
 *  - label:      Human-readable name
 *  - keyField:   Field used as unique identifier (default: '.id')
 *  - nameField:  Field used as human-readable name (default: 'name')
 *  - enabled:    Whether this module is active for sync
 */
class ConfigSyncModuleRegistry
{
    private const MODULES = [
        'interface' => [
            'path' => '/interface',
            'label' => 'Interfaces',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'bridge' => [
            'path' => '/interface/bridge',
            'label' => 'Bridge',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'vlan' => [
            'path' => '/interface/vlan',
            'label' => 'VLAN',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'arp' => [
            'path' => '/ip/arp',
            'label' => 'ARP',
            'keyField' => '.id',
            'nameField' => 'address',
            'enabled' => true,
        ],
        'neighbor' => [
            'path' => '/ip/neighbor',
            'label' => 'Neighbor Discovery',
            'keyField' => '.id',
            'nameField' => 'identity',
            'enabled' => true,
        ],
        'ip_address' => [
            'path' => '/ip/address',
            'label' => 'IP Address',
            'keyField' => '.id',
            'nameField' => 'address',
            'enabled' => true,
        ],
        'ip_route' => [
            'path' => '/ip/route',
            'label' => 'Routing',
            'keyField' => '.id',
            'nameField' => 'dst-address',
            'enabled' => true,
        ],
        'dns' => [
            'path' => '/ip/dns',
            'label' => 'DNS',
            'keyField' => '__singleton__',
            'nameField' => '__label__',
            'enabled' => true,
        ],
        'ip_pool' => [
            'path' => '/ip/pool',
            'label' => 'IP Pool',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'dhcp_server' => [
            'path' => '/ip/dhcp-server',
            'label' => 'DHCP Server',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'dhcp_lease' => [
            'path' => '/ip/dhcp-server/lease',
            'label' => 'DHCP Lease',
            'keyField' => '.id',
            'nameField' => 'address',
            'enabled' => true,
        ],
        'ppp_secret' => [
            'path' => '/ppp/secret',
            'label' => 'PPPoE Secret',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'ppp_profile' => [
            'path' => '/ppp/profile',
            'label' => 'PPP Profile',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'hotspot_server' => [
            'path' => '/ip/hotspot',
            'label' => 'Hotspot Server',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'hotspot_user' => [
            'path' => '/ip/hotspot/user',
            'label' => 'Hotspot User',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'hotspot_profile' => [
            'path' => '/ip/hotspot/user/profile',
            'label' => 'Hotspot Profile',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'firewall_filter' => [
            'path' => '/ip/firewall/filter',
            'label' => 'Firewall Filter',
            'keyField' => '.id',
            'nameField' => 'comment',
            'enabled' => true,
        ],
        'firewall_nat' => [
            'path' => '/ip/firewall/nat',
            'label' => 'Firewall NAT',
            'keyField' => '.id',
            'nameField' => 'comment',
            'enabled' => true,
        ],
        'address_list' => [
            'path' => '/ip/firewall/address-list',
            'label' => 'Address List',
            'keyField' => '.id',
            'nameField' => 'list',
            'enabled' => true,
        ],
        'mangle' => [
            'path' => '/ip/firewall/mangle',
            'label' => 'Mangle',
            'keyField' => '.id',
            'nameField' => 'comment',
            'enabled' => true,
        ],
        'queue_simple' => [
            'path' => '/queue/simple',
            'label' => 'Simple Queue',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'script' => [
            'path' => '/system/script',
            'label' => 'Script',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
        'netwatch' => [
            'path' => '/tool/netwatch',
            'label' => 'Netwatch',
            'keyField' => '.id',
            'nameField' => 'host',
            'enabled' => true,
        ],
        'scheduler' => [
            'path' => '/system/scheduler',
            'label' => 'Scheduler',
            'keyField' => '.id',
            'nameField' => 'name',
            'enabled' => true,
        ],
    ];

    /**
     * Get all registered modules.
     */
    public static function all(): array
    {
        return self::MODULES;
    }

    /**
     * Get only enabled modules.
     */
    public static function enabled(): array
    {
        return array_filter(self::MODULES, fn (array $m) => $m['enabled']);
    }

    /**
     * Get a specific module by key.
     */
    public static function get(string $module): ?array
    {
        return self::MODULES[$module] ?? null;
    }

    /**
     * Check if a module is registered and enabled.
     */
    public static function isEnabled(string $module): bool
    {
        return isset(self::MODULES[$module]) && self::MODULES[$module]['enabled'];
    }

    /**
     * Get all module keys.
     */
    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * Get enabled module keys.
     */
    public static function enabledKeys(): array
    {
        return array_keys(self::enabled());
    }

    /**
     * Get the REST API path for a module.
     */
    public static function getPath(string $module): ?string
    {
        return self::MODULES[$module]['path'] ?? null;
    }

    /**
     * Extract the unique item ID from a RouterOS response item.
     */
    public static function extractItemId(array $item, string $module): string
    {
        $keyField = self::MODULES[$module]['keyField'] ?? '.id';

        if ($keyField === '__singleton__') {
            return '__singleton__';
        }

        return $item[$keyField] ?? '';
    }

    /**
     * Extract the human-readable name from a RouterOS response item.
     */
    public static function extractItemName(array $item, string $module): string
    {
        $nameField = self::MODULES[$module]['nameField'] ?? 'name';

        if ($nameField === '__label__') {
            return self::MODULES[$module]['label'] ?? 'Config';
        }

        return $item[$nameField] ?? '(unnamed)';
    }

    /**
     * Compute a stable checksum for a config item (ignoring volatile fields).
     */
    public static function computeItemChecksum(array $item): string
    {
        $cleaned = self::cleanConfigItem($item);

        return hash('sha256', json_encode($cleaned, JSON_THROW_ON_ERROR));
    }

    /**
     * Remove volatile/dynamic fields that change on every read but are not config.
     */
    private static function cleanConfigItem(array $item): array
    {
        $volatileKeys = ['.id', 'rx-byte', 'tx-byte', 'rx-packet', 'tx-packet',
            'rx-error', 'tx-error', 'rx-drop', 'tx-drop', 'last-link-up-time',
            'last-link-down-time', 'link-downs', 'running', 'actual-mtu',
            'bytes', 'packets', 'rates', 'total-queuing-packets'];

        return array_diff_key($item, array_flip($volatileKeys));
    }
}
