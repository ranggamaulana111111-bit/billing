<?php

namespace App\Services\Mikrotik\Config;

/**
 * Registry of all RouterOS Configuration Center modules.
 *
 * Each module defines:
 *  - key:         Unique identifier
 *  - label:       Human-readable name
 *  - icon:        Font Awesome icon class
 *  - category:    Grouping for sidebar navigation
 *  - path:        RouterOS REST API path
 *  - keyField:    Field used as unique identifier (default: '.id')
 *  - nameField:   Field used as human-readable name
 *  - writable:    Whether the module supports CRUD operations (default: true)
 *  - createFields: Array of field definitions for the Create/Edit form
 */
class ConfigModuleRegistry
{
    private const MODULES = [
        // ── NETWORK ──
        'interface' => [
            'label' => 'Interface List',
            'icon' => 'fa-solid fa-network-wired',
            'category' => 'Network',
            'path' => '/interface',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => false,
        ],
        'bridge' => [
            'label' => 'Bridge',
            'icon' => 'fa-solid fa-bridge',
            'category' => 'Network',
            'path' => '/interface/bridge',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'bridge1'],
                ['name' => 'mtu', 'label' => 'MTU', 'type' => 'number', 'required' => false, 'placeholder' => '1500'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'vlan' => [
            'label' => 'VLAN',
            'icon' => 'fa-solid fa-tag',
            'category' => 'Network',
            'path' => '/interface/vlan',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'vlan100'],
                ['name' => 'vlan-id', 'label' => 'VLAN ID', 'type' => 'number', 'required' => true, 'placeholder' => '100'],
                ['name' => 'interface', 'label' => 'Interface', 'type' => 'text', 'required' => true, 'placeholder' => 'bridge1'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'arp' => [
            'label' => 'ARP',
            'icon' => 'fa-solid fa-address-book',
            'category' => 'Network',
            'path' => '/ip/arp',
            'keyField' => '.id',
            'nameField' => 'address',
            'writable' => true,
            'createFields' => [
                ['name' => 'address', 'label' => 'IP Address', 'type' => 'text', 'required' => true, 'placeholder' => '192.168.1.1'],
                ['name' => 'interface', 'label' => 'Interface', 'type' => 'text', 'required' => true, 'placeholder' => 'ether1'],
                ['name' => 'mac-address', 'label' => 'MAC Address', 'type' => 'text', 'required' => false, 'placeholder' => 'AA:BB:CC:DD:EE:FF'],
            ],
        ],
        'neighbor' => [
            'label' => 'Neighbor Discovery',
            'icon' => 'fa-solid fa-magnifying-glass',
            'category' => 'Network',
            'path' => '/ip/neighbor',
            'keyField' => '.id',
            'nameField' => 'identity',
            'writable' => false,
        ],

        // ── IP & ROUTING ──
        'ip_address' => [
            'label' => 'IP Address',
            'icon' => 'fa-solid fa-location-dot',
            'category' => 'IP & Routing',
            'path' => '/ip/address',
            'keyField' => '.id',
            'nameField' => 'address',
            'writable' => true,
            'createFields' => [
                ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'required' => true, 'placeholder' => '192.168.1.1/24'],
                ['name' => 'interface', 'label' => 'Interface', 'type' => 'text', 'required' => true, 'placeholder' => 'ether1'],
                ['name' => 'network', 'label' => 'Network', 'type' => 'text', 'required' => false, 'placeholder' => '192.168.1.0'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'ip_route' => [
            'label' => 'Routing',
            'icon' => 'fa-solid fa-route',
            'category' => 'IP & Routing',
            'path' => '/ip/route',
            'keyField' => '.id',
            'nameField' => 'dst-address',
            'writable' => true,
            'createFields' => [
                ['name' => 'dst-address', 'label' => 'Destination', 'type' => 'text', 'required' => true, 'placeholder' => '0.0.0.0/0'],
                ['name' => 'gateway', 'label' => 'Gateway', 'type' => 'text', 'required' => true, 'placeholder' => '192.168.1.1'],
                ['name' => 'distance', 'label' => 'Distance', 'type' => 'number', 'required' => false, 'placeholder' => '1'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'dns' => [
            'label' => 'DNS',
            'icon' => 'fa-solid fa-globe',
            'category' => 'IP & Routing',
            'path' => '/ip/dns',
            'keyField' => '__singleton__',
            'nameField' => '__label__',
            'writable' => true,
            'createFields' => [],
        ],
        'ip_pool' => [
            'label' => 'IP Pool',
            'icon' => 'fa-solid fa-layer-group',
            'category' => 'IP & Routing',
            'path' => '/ip/pool',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'pppoe-pool'],
                ['name' => 'ranges', 'label' => 'Ranges (one per line)', 'type' => 'textarea', 'required' => true, 'placeholder' => '192.168.1.10-192.168.1.100'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],

        // ── DHCP ──
        'dhcp_server' => [
            'label' => 'DHCP Server',
            'icon' => 'fa-solid fa-server',
            'category' => 'DHCP',
            'path' => '/ip/dhcp-server',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'dhcp-lan'],
                ['name' => 'interface', 'label' => 'Interface', 'type' => 'text', 'required' => true, 'placeholder' => 'bridge1'],
                ['name' => 'address-pool', 'label' => 'Address Pool', 'type' => 'text', 'required' => false, 'placeholder' => 'dhcp-pool1'],
                ['name' => 'lease-time', 'label' => 'Lease Time', 'type' => 'text', 'required' => false, 'placeholder' => '1d'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'dhcp_lease' => [
            'label' => 'DHCP Lease',
            'icon' => 'fa-solid fa-list-check',
            'category' => 'DHCP',
            'path' => '/ip/dhcp-server/lease',
            'keyField' => '.id',
            'nameField' => 'address',
            'writable' => false,
        ],

        // ── PPP ──
        'ppp_secret' => [
            'label' => 'PPPoE / PPP Secret',
            'icon' => 'fa-solid fa-key',
            'category' => 'PPP',
            'path' => '/ppp/secret',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Username', 'type' => 'text', 'required' => true, 'placeholder' => 'customer01'],
                ['name' => 'password', 'label' => 'Password', 'type' => 'text', 'required' => true, 'placeholder' => '••••••••'],
                ['name' => 'service', 'label' => 'Service', 'type' => 'select', 'required' => true, 'options' => ['pppoe' => 'PPPoE', 'l2tp' => 'L2TP', 'pptp' => 'PPTP', 'ovpn' => 'OpenVPN', 'any' => 'Any']],
                ['name' => 'profile', 'label' => 'Profile', 'type' => 'text', 'required' => false, 'placeholder' => 'default'],
                ['name' => 'local-address', 'label' => 'Local Address', 'type' => 'text', 'required' => false, 'placeholder' => '10.0.0.1'],
                ['name' => 'remote-address', 'label' => 'Remote Address', 'type' => 'text', 'required' => false, 'placeholder' => '192.168.1.10'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'ppp_profile' => [
            'label' => 'PPP Profile',
            'icon' => 'fa-solid fa-id-badge',
            'category' => 'PPP',
            'path' => '/ppp/profile',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'profile-10m'],
                ['name' => 'local-address', 'label' => 'Local Address', 'type' => 'text', 'required' => false, 'placeholder' => '10.0.0.1'],
                ['name' => 'remote-address', 'label' => 'Remote Address', 'type' => 'text', 'required' => false, 'placeholder' => '192.168.1.10'],
                ['name' => 'rate-limit', 'label' => 'Rate Limit', 'type' => 'text', 'required' => false, 'placeholder' => '10M/5M'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],

        // ── HOTSPOT ──
        'hotspot_server' => [
            'label' => 'Hotspot Server',
            'icon' => 'fa-solid fa-wifi',
            'category' => 'Hotspot',
            'path' => '/ip/hotspot',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'hs-pool1'],
                ['name' => 'interface', 'label' => 'Interface', 'type' => 'text', 'required' => true, 'placeholder' => 'wlan1'],
                ['name' => 'address-pool', 'label' => 'Address Pool', 'type' => 'text', 'required' => false, 'placeholder' => 'hs-pool1'],
                ['name' => 'profile', 'label' => 'Profile', 'type' => 'text', 'required' => false, 'placeholder' => 'default'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'hotspot_user' => [
            'label' => 'Hotspot User',
            'icon' => 'fa-solid fa-users',
            'category' => 'Hotspot',
            'path' => '/ip/hotspot/user',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Username', 'type' => 'text', 'required' => true, 'placeholder' => 'guest01'],
                ['name' => 'password', 'label' => 'Password', 'type' => 'text', 'required' => true, 'placeholder' => '••••••••'],
                ['name' => 'server', 'label' => 'Server', 'type' => 'text', 'required' => false, 'placeholder' => 'all'],
                ['name' => 'profile', 'label' => 'Profile', 'type' => 'text', 'required' => false, 'placeholder' => 'default'],
                ['name' => 'limit-uptime', 'label' => 'Limit Uptime', 'type' => 'text', 'required' => false, 'placeholder' => '1d'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'hotspot_profile' => [
            'label' => 'Hotspot Profile',
            'icon' => 'fa-solid fa-id-card',
            'category' => 'Hotspot',
            'path' => '/ip/hotspot/user/profile',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'profile-gold'],
                ['name' => 'rate-limit', 'label' => 'Rate Limit', 'type' => 'text', 'required' => false, 'placeholder' => '5M/2M'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],

        // ── FIREWALL ──
        'firewall_filter' => [
            'label' => 'Firewall Filter',
            'icon' => 'fa-solid fa-shield-halved',
            'category' => 'Firewall',
            'path' => '/ip/firewall/filter',
            'keyField' => '.id',
            'nameField' => 'comment',
            'writable' => true,
            'createFields' => [
                ['name' => 'chain', 'label' => 'Chain', 'type' => 'select', 'required' => true, 'options' => ['input' => 'Input', 'forward' => 'Forward', 'output' => 'Output']],
                ['name' => 'action', 'label' => 'Action', 'type' => 'select', 'required' => true, 'options' => ['accept' => 'Accept', 'drop' => 'Drop', 'reject' => 'Reject', 'log' => 'Log', 'tarpit' => 'Tarpit']],
                ['name' => 'src-address', 'label' => 'Source Address', 'type' => 'text', 'required' => false, 'placeholder' => '192.168.1.0/24'],
                ['name' => 'dst-address', 'label' => 'Destination Address', 'type' => 'text', 'required' => false, 'placeholder' => '10.0.0.0/8'],
                ['name' => 'src-port', 'label' => 'Source Port', 'type' => 'text', 'required' => false],
                ['name' => 'dst-port', 'label' => 'Destination Port', 'type' => 'text', 'required' => false, 'placeholder' => '80,443'],
                ['name' => 'protocol', 'label' => 'Protocol', 'type' => 'select', 'required' => false, 'options' => ['' => '-- Any --', 'tcp' => 'TCP', 'udp' => 'UDP', 'icmp' => 'ICMP']],
                ['name' => 'in-interface', 'label' => 'In Interface', 'type' => 'text', 'required' => false],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => true, 'placeholder' => 'Block HTTP'],
            ],
        ],
        'firewall_nat' => [
            'label' => 'NAT',
            'icon' => 'fa-solid fa-right-left',
            'category' => 'Firewall',
            'path' => '/ip/firewall/nat',
            'keyField' => '.id',
            'nameField' => 'comment',
            'writable' => true,
            'createFields' => [
                ['name' => 'chain', 'label' => 'Chain', 'type' => 'select', 'required' => true, 'options' => ['srcnat' => 'SrcNAT', 'dstnat' => 'DstNAT']],
                ['name' => 'action', 'label' => 'Action', 'type' => 'select', 'required' => true, 'options' => ['masquerade' => 'Masquerade', 'src-nat' => 'SrcNAT', 'dst-nat' => 'DstNAT', 'netmap' => 'Netmap']],
                ['name' => 'to-addresses', 'label' => 'To Addresses', 'type' => 'text', 'required' => false, 'placeholder' => '10.0.0.1'],
                ['name' => 'to-ports', 'label' => 'To Ports', 'type' => 'text', 'required' => false, 'placeholder' => '8080'],
                ['name' => 'src-address', 'label' => 'Source Address', 'type' => 'text', 'required' => false],
                ['name' => 'dst-address', 'label' => 'Destination Address', 'type' => 'text', 'required' => false],
                ['name' => 'dst-port', 'label' => 'Destination Port', 'type' => 'text', 'required' => false, 'placeholder' => '80'],
                ['name' => 'protocol', 'label' => 'Protocol', 'type' => 'select', 'required' => false, 'options' => ['' => '-- Any --', 'tcp' => 'TCP', 'udp' => 'UDP']],
                ['name' => 'in-interface', 'label' => 'In Interface', 'type' => 'text', 'required' => false],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => true, 'placeholder' => 'NAT to server'],
            ],
        ],
        'mangle' => [
            'label' => 'Mangle',
            'icon' => 'fa-solid fa-sliders',
            'category' => 'Firewall',
            'path' => '/ip/firewall/mangle',
            'keyField' => '.id',
            'nameField' => 'comment',
            'writable' => true,
            'createFields' => [
                ['name' => 'chain', 'label' => 'Chain', 'type' => 'select', 'required' => true, 'options' => ['prerouting' => 'Prerouting', 'input' => 'Input', 'forward' => 'Forward', 'output' => 'Output', 'postrouting' => 'Postrouting']],
                ['name' => 'action', 'label' => 'Action', 'type' => 'select', 'required' => true, 'options' => ['mark-packet' => 'Mark Packet', 'mark-connection' => 'Mark Connection', 'mark-routing' => 'Mark Routing', 'passthrough' => 'Passthrough', 'accept' => 'Accept', 'drop' => 'Drop']],
                ['name' => 'new-packet-marks', 'label' => 'New Packet Mark', 'type' => 'text', 'required' => false],
                ['name' => 'new-connection-mark', 'label' => 'New Connection Mark', 'type' => 'text', 'required' => false],
                ['name' => 'src-address', 'label' => 'Source Address', 'type' => 'text', 'required' => false],
                ['name' => 'dst-address', 'label' => 'Destination Address', 'type' => 'text', 'required' => false],
                ['name' => 'dst-port', 'label' => 'Destination Port', 'type' => 'text', 'required' => false],
                ['name' => 'protocol', 'label' => 'Protocol', 'type' => 'select', 'required' => false, 'options' => ['' => '-- Any --', 'tcp' => 'TCP', 'udp' => 'UDP', 'icmp' => 'ICMP']],
                ['name' => 'in-interface', 'label' => 'In Interface', 'type' => 'text', 'required' => false],
                ['name' => 'passthrough', 'label' => 'Passthrough', 'type' => 'select', 'required' => false, 'options' => ['yes' => 'Yes', 'no' => 'No']],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => true, 'placeholder' => 'Mark download traffic'],
            ],
        ],
        'address_list' => [
            'label' => 'Address List',
            'icon' => 'fa-solid fa-address-card',
            'category' => 'Firewall',
            'path' => '/ip/firewall/address-list',
            'keyField' => '.id',
            'nameField' => 'list',
            'writable' => true,
            'createFields' => [
                ['name' => 'list', 'label' => 'List Name', 'type' => 'text', 'required' => true, 'placeholder' => 'blocked-ips'],
                ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'required' => true, 'placeholder' => '192.168.1.100'],
                ['name' => 'timeout', 'label' => 'Timeout', 'type' => 'text', 'required' => false, 'placeholder' => '1d'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],

        // ── SYSTEM ──
        'queue_simple' => [
            'label' => 'Queue',
            'icon' => 'fa-solid fa-bars-progress',
            'category' => 'System',
            'path' => '/queue/simple',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'queue-10m'],
                ['name' => 'target', 'label' => 'Target', 'type' => 'text', 'required' => true, 'placeholder' => '192.168.1.0/24'],
                ['name' => 'max-limit', 'label' => 'Max Limit (up/down)', 'type' => 'text', 'required' => false, 'placeholder' => '10M/5M'],
                ['name' => 'burst-limit', 'label' => 'Burst Limit', 'type' => 'text', 'required' => false],
                ['name' => 'burst-threshold', 'label' => 'Burst Threshold', 'type' => 'text', 'required' => false],
                ['name' => 'burst-time', 'label' => 'Burst Time', 'type' => 'text', 'required' => false],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'scheduler' => [
            'label' => 'Scheduler',
            'icon' => 'fa-solid fa-calendar-check',
            'category' => 'System',
            'path' => '/system/scheduler',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'backup-daily'],
                ['name' => 'start-time', 'label' => 'Start Time', 'type' => 'text', 'required' => false, 'placeholder' => '03:00:00'],
                ['name' => 'interval', 'label' => 'Interval', 'type' => 'text', 'required' => false, 'placeholder' => '1d'],
                ['name' => 'on-event', 'label' => 'On Event', 'type' => 'textarea', 'required' => true, 'placeholder' => '/system backup save name=daily-backup;'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'script' => [
            'label' => 'Script',
            'icon' => 'fa-solid fa-code',
            'category' => 'System',
            'path' => '/system/script',
            'keyField' => '.id',
            'nameField' => 'name',
            'writable' => true,
            'createFields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'my-script'],
                ['name' => 'source', 'label' => 'Source', 'type' => 'textarea', 'required' => true, 'placeholder' => ':log info "Hello World";'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
        'netwatch' => [
            'label' => 'Netwatch',
            'icon' => 'fa-solid fa-eye',
            'category' => 'System',
            'path' => '/tool/netwatch',
            'keyField' => '.id',
            'nameField' => 'host',
            'writable' => true,
            'createFields' => [
                ['name' => 'host', 'label' => 'Host', 'type' => 'text', 'required' => true, 'placeholder' => '8.8.8.8'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => false, 'options' => ['icmp' => 'ICMP Ping', 'tcp' => 'TCP Port']],
                ['name' => 'port', 'label' => 'Port', 'type' => 'text', 'required' => false, 'placeholder' => '443'],
                ['name' => 'interval', 'label' => 'Interval', 'type' => 'text', 'required' => false, 'placeholder' => '1m'],
                ['name' => 'timeout', 'label' => 'Timeout', 'type' => 'text', 'required' => false, 'placeholder' => '1s'],
                ['name' => 'comment', 'label' => 'Comment', 'type' => 'text', 'required' => false],
            ],
        ],
    ];

    private const CATEGORIES = [
        'Network' => 'fa-solid fa-network-wired',
        'IP & Routing' => 'fa-solid fa-route',
        'DHCP' => 'fa-solid fa-server',
        'PPP' => 'fa-solid fa-key',
        'Hotspot' => 'fa-solid fa-wifi',
        'Firewall' => 'fa-solid fa-shield-halved',
        'System' => 'fa-solid fa-gear',
    ];

    public static function all(): array
    {
        return self::MODULES;
    }

    public static function get(string $module): ?array
    {
        return self::MODULES[$module] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function getByCategory(): array
    {
        $grouped = [];
        foreach (self::MODULES as $key => $module) {
            $cat = $module['category'];
            $grouped[$cat][] = array_merge(['key' => $key], $module);
        }

        return $grouped;
    }

    public static function getPath(string $module): ?string
    {
        return self::MODULES[$module]['path'] ?? null;
    }

    public static function isWritable(string $module): bool
    {
        $def = self::MODULES[$module] ?? null;

        return $def && ($def['writable'] ?? false);
    }

    public static function getCreateFields(string $module): array
    {
        $def = self::MODULES[$module] ?? null;

        return $def['createFields'] ?? [];
    }

    public static function extractItemId(array $item, string $module): string
    {
        $def = self::MODULES[$module] ?? null;
        if (! $def) {
            return '';
        }
        $keyField = $def['keyField'];

        if ($keyField === '__singleton__') {
            return '__singleton__';
        }

        return $item[$keyField] ?? '';
    }

    public static function extractItemName(array $item, string $module): string
    {
        $def = self::MODULES[$module] ?? null;
        if (! $def) {
            return '(unknown)';
        }
        $nameField = $def['nameField'];

        if ($nameField === '__label__') {
            return $def['label'];
        }

        return $item[$nameField] ?? '(unnamed)';
    }
}
