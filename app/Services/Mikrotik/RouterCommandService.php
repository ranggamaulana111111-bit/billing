<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;

/**
 * High-level command facade for RouterOS operations.
 *
 * This is the PRIMARY entry point for all MikroTik modules.
 * Provides typed methods for common RouterOS operations, command logging,
 * and explicit read/write mode indicators.
 *
 * Usage:
 *  $cmd = new RouterCommandService($router);
 *  $result = $cmd->getSystemResource();
 *  if ($result->isSuccess()) { ... }
 *
 *  // Write operations (create/update/delete):
 *  $result = $cmd->disconnectPppSession('1A');
 *
 * All methods return RouterResult.  Callers MUST check isSuccess() before
 * using getData().
 *
 * Command Logging:
 *  Every command execution is logged to the 'mikrotik' channel with
 *  structured context: method, path, router_id, latency, success/fail.
 *  This is controlled by the RouterErrorHandler::logCommand() method.
 *
 * Read/Write Modes:
 *  Methods are categorized by their nature:
 *  - READ:  get*, test*, count* — safe, no side effects
 *  - WRITE: put*, patch*, delete*, disconnect*, add*, set* — modifies router state
 *  The mode is recorded in the trace meta for audit trails.
 */
class RouterCommandService
{
    private RouterOSApiService $api;

    private RouterErrorHandler $errorHandler;

    public function __construct(MikrotikRouter $router)
    {
        $this->api = new RouterOSApiService($router);
        $this->errorHandler = new RouterErrorHandler;
    }

    /**
     * Build from an existing API service (for testing or composition).
     */
    public static function fromApi(RouterOSApiService $api): self
    {
        $instance = new static(new MikrotikRouter);
        $instance->api = $api;

        return $instance;
    }

    // ═══════════════════════════════════════════
    //  CONNECTION & SYSTEM (READ)
    // ═══════════════════════════════════════════

    /**
     * Test connection to the router.
     */
    public function testConnection(): RouterResult
    {
        return $this->api->testConnection();
    }

    /**
     * Measure latency in milliseconds.
     */
    public function getLatency(): RouterResult
    {
        $latencyMs = $this->api->getLatency();

        if ($latencyMs === null) {
            return RouterResult::fail('Tidak dapat mengukur latency', RouterErrorHandler::TIMEOUT);
        }

        return RouterResult::ok('', ['latency_ms' => $latencyMs], ['latency_ms' => $latencyMs]);
    }

    /**
     * Get system resource info (CPU, memory, disk, uptime, version).
     */
    public function getSystemResource(): RouterResult
    {
        return $this->execute('GET', '/system/resource', 'read');
    }

    /**
     * Ping a host FROM the MikroTik router (source = router, target = host).
     *
     * Used to determine the REAL reachability of devices that are not directly
     * reachable from the application server (e.g. the OLT management IP lives on
     * a network only the MikroTik can reach). The ICMP echo is sent to the OLT
     * itself — the MikroTik is merely the source because the app server is remote.
     *
     * @param  string  $address  Target IP (e.g. OLT management IP)
     * @param  int  $count  Number of echo requests
     * @param  string  $interval  Interval between requests (RouterOS format, e.g. "1s")
     * @return RouterResult ok with ['reachable'=>bool,'replies'=>int,'sent'=>int]
     */
    public function pingHost(string $address, int $count = 3, string $interval = '1s'): RouterResult
    {
        $result = $this->api->get('/ping', [
            'address' => $address,
            'count' => (string) $count,
            'interval' => $interval,
        ]);

        if (! $result->isSuccess()) {
            return $result;
        }

        $data = $result->toArray();
        $replies = 0;
        $sent = 0;

        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sent++;
            $status = (string) ($row['status'] ?? '');
            if ($status === 'reply' || isset($row['time'])) {
                $replies++;
            }
        }

        return RouterResult::ok('', [
            'reachable' => $replies > 0,
            'replies' => $replies,
            'sent' => $sent,
        ]);
    }

    /**
     * Get system identity (router name).
     */
    public function getSystemIdentity(): RouterResult
    {
        $result = $this->execute('GET', '/system/identity', 'read');

        if ($result->isSuccess()) {
            $data = $result->toArray();
            $identity = $data[0] ?? $data;

            return RouterResult::ok($result->getMessage(), $identity, $result->getMeta());
        }

        return $result;
    }

    /**
     * Get system health (temperature, voltage, fan).
     */
    public function getSystemHealth(): RouterResult
    {
        return $this->execute('GET', '/system/health', 'read');
    }

    /**
     * Get router packages (installed software).
     */
    public function getPackages(): RouterResult
    {
        return $this->execute('GET', '/system/package', 'read');
    }

    /**
     * Get router resources (disk, memory details).
     */
    public function getStorage(): RouterResult
    {
        return $this->execute('GET', '/system/storage', 'read');
    }

    /**
     * Get router clock info.
     */
    public function getClock(): RouterResult
    {
        return $this->execute('GET', '/system/clock', 'read');
    }

    /**
     * Get router NTP client status.
     */
    public function getNtpClient(): RouterResult
    {
        return $this->execute('GET', '/system/ntp-client', 'read');
    }

    // ═══════════════════════════════════════════
    //  INTERFACES (READ)
    // ═══════════════════════════════════════════

    /**
     * Get all interfaces.
     */
    public function getInterfaces(): RouterResult
    {
        return $this->execute('GET', '/interface', 'read');
    }

    /**
     * Get a single interface by name.
     */
    public function getInterfaceByName(string $name): RouterResult
    {
        $result = $this->api->get('/interface', ['name' => $name]);

        if ($result->isSuccess()) {
            $items = $result->toArray();

            return RouterResult::ok('', $items[0] ?? null, $result->getMeta());
        }

        return $result;
    }

    /**
     * Monitor traffic on a specific interface (one-shot snapshot).
     *
     * @param  string  $interface  Interface name (e.g. "ether1")
     */
    public function getInterfaceTraffic(string $interface): RouterResult
    {
        return $this->execute('GET', '/interface/monitor-traffic', 'read', [
            'interface' => $interface,
            'once' => '',
        ]);
    }

    /**
     * Monitor traffic on all interfaces in a single batch.
     *
     * Returns an array of [interface_name => RouterResult].
     */
    public function getAllInterfaceTraffic(): array
    {
        $interfaces = $this->getInterfaces();

        if (! $interfaces->isSuccess()) {
            return ['_error' => $interfaces];
        }

        $results = [];

        foreach ($interfaces->toArray() as $iface) {
            $name = $iface['name'] ?? $iface['.name'] ?? null;
            if ($name) {
                $results[$name] = $this->getInterfaceTraffic($name);
            }
        }

        return $results;
    }

    // ═══════════════════════════════════════════
    //  PPP — READ
    // ═══════════════════════════════════════════

    /**
     * Get all active PPP sessions.
     */
    public function getPppActive(): RouterResult
    {
        return $this->execute('GET', '/ppp/active', 'read');
    }

    /**
     * Get active PPP session by username.
     */
    public function getPppActiveByUsername(string $username): RouterResult
    {
        $result = $this->api->get('/ppp/active', ['name' => $username]);

        if ($result->isSuccess()) {
            $items = $result->toArray();

            return RouterResult::ok('', $items[0] ?? null, $result->getMeta());
        }

        return $result;
    }

    /**
     * Get all PPP secrets (user configs).
     */
    public function getPppSecrets(): RouterResult
    {
        return $this->execute('GET', '/ppp/secret', 'read');
    }

    /**
     * Get a PPP secret by username.
     */
    public function getPppSecretByUsername(string $username): RouterResult
    {
        $result = $this->api->get('/ppp/secret', ['name' => $username]);

        if ($result->isSuccess()) {
            $items = $result->toArray();

            return RouterResult::ok('', $items[0] ?? null, $result->getMeta());
        }

        return $result;
    }

    /**
     * Get all PPP profiles.
     */
    public function getPppProfiles(): RouterResult
    {
        return $this->execute('GET', '/ppp/profile', 'read');
    }

    // ═══════════════════════════════════════════
    //  PPP — WRITE
    // ═══════════════════════════════════════════

    /**
     * Disconnect an active PPP session.
     */
    public function disconnectPppSession(string $sessionId): RouterResult
    {
        return $this->execute('DELETE', "/ppp/active/{$sessionId}", 'write');
    }

    // ═══════════════════════════════════════════
    //  HOTSPOT — READ
    // ═══════════════════════════════════════════

    /**
     * Get all active hotspot sessions.
     */
    public function getHotspotActive(): RouterResult
    {
        return $this->execute('GET', '/ip/hotspot/active', 'read');
    }

    /**
     * Get all hotspot users.
     */
    public function getHotspotUsers(): RouterResult
    {
        return $this->execute('GET', '/ip/hotspot/user', 'read');
    }

    /**
     * Get a hotspot user by username.
     */
    public function getHotspotUserByUsername(string $username): RouterResult
    {
        $result = $this->api->get('/ip/hotspot/user', ['name' => $username]);

        if ($result->isSuccess()) {
            $items = $result->toArray();

            return RouterResult::ok('', $items[0] ?? null, $result->getMeta());
        }

        return $result;
    }

    /**
     * Get all hotspot servers.
     */
    public function getHotspotServers(): RouterResult
    {
        return $this->execute('GET', '/ip/hotspot', 'read');
    }

    /**
     * Get all hotspot profiles.
     */
    public function getHotspotProfiles(): RouterResult
    {
        return $this->execute('GET', '/ip/hotspot/user/profile', 'read');
    }

    // ═══════════════════════════════════════════
    //  HOTSPOT — WRITE
    // ═══════════════════════════════════════════

    /**
     * Disconnect an active hotspot session.
     */
    public function disconnectHotspotSession(string $sessionId): RouterResult
    {
        return $this->execute('DELETE', "/ip/hotspot/active/{$sessionId}", 'write');
    }

    // ═══════════════════════════════════════════
    //  FIREWALL (READ)
    // ═══════════════════════════════════════════

    /**
     * Get firewall address list entries.
     */
    public function getFirewallAddressList(?string $list = null): RouterResult
    {
        $query = $list ? ['list' => $list] : [];

        return $this->execute('GET', '/ip/firewall/address-list', 'read', $query);
    }

    /**
     * Get firewall filter rules.
     */
    public function getFirewallFilterRules(): RouterResult
    {
        return $this->execute('GET', '/ip/firewall/filter', 'read');
    }

    /**
     * Get firewall NAT rules.
     */
    public function getFirewallNatRules(): RouterResult
    {
        return $this->execute('GET', '/ip/firewall/nat', 'read');
    }

    // ═══════════════════════════════════════════
    //  FIREWALL (WRITE)
    // ═══════════════════════════════════════════

    /**
     * Add a firewall address list entry.
     */
    public function addFirewallAddressList(string $list, string $address, ?string $comment = null): RouterResult
    {
        $data = ['list' => $list, 'address' => $address];
        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        return $this->execute('PUT', '/ip/firewall/address-list', 'write', $data);
    }

    /**
     * Remove a firewall address list entry by ID.
     */
    public function removeFirewallAddressList(string $entryId): RouterResult
    {
        return $this->execute('DELETE', "/ip/firewall/address-list/{$entryId}", 'write');
    }

    // ═══════════════════════════════════════════
    //  QUEUES (READ)
    // ═══════════════════════════════════════════

    /**
     * Get all simple queues.
     */
    public function getSimpleQueues(): RouterResult
    {
        return $this->execute('GET', '/queue/simple', 'read');
    }

    // ═══════════════════════════════════════════
    //  DHCP (READ)
    // ═══════════════════════════════════════════

    /**
     * Get DHCP leases.
     */
    public function getDhcpLeases(): RouterResult
    {
        return $this->execute('GET', '/ip/dhcp-server/lease', 'read');
    }

    /**
     * Get DHCP server configs.
     */
    public function getDhcpServers(): RouterResult
    {
        return $this->execute('GET', '/ip/dhcp-server', 'read');
    }

    // ═══════════════════════════════════════════
    //  LOG (READ)
    // ═══════════════════════════════════════════

    /**
     * Get router logs.
     *
     * @param  int  $count  Number of log entries to retrieve
     */
    public function getLog(int $count = 50): RouterResult
    {
        return $this->execute('GET', '/log', 'read', ['.top' => $count]);
    }

    // ═══════════════════════════════════════════
    //  GENERIC OPERATIONS (RAW)
    // ═══════════════════════════════════════════

    /**
     * Execute a raw GET on any RouterOS REST path.
     *
     * Use this for paths not covered by typed methods above.
     *
     * @param  string  $path  REST path (e.g. "/ip/route")
     * @param  array  $query  Query parameters
     */
    public function rawGet(string $path, array $query = []): RouterResult
    {
        return $this->api->get($path, $query);
    }

    /**
     * Execute a raw PUT (create) on any RouterOS REST path.
     */
    public function rawPut(string $path, array $data = []): RouterResult
    {
        return $this->api->put($path, $data);
    }

    /**
     * Execute a raw PATCH (update) on any RouterOS REST path.
     */
    public function rawPatch(string $path, array $data = []): RouterResult
    {
        return $this->api->patch($path, $data);
    }

    /**
     * Execute a raw DELETE on any RouterOS REST path.
     */
    public function rawDelete(string $path): RouterResult
    {
        return $this->api->delete($path);
    }

    /**
     * Get the underlying API service (for advanced use cases).
     */
    public function getApi(): RouterOSApiService
    {
        return $this->api;
    }

    // ═══════════════════════════════════════════
    //  PRIVATE EXECUTION HELPER
    // ═══════════════════════════════════════════

    /**
     * Execute a command with logging and mode tracking.
     *
     * Delegates to the low-level API service, then logs the result.
     *
     * @param  string  $method  HTTP method (GET, PUT, PATCH, DELETE, POST)
     * @param  string  $path  RouterOS REST path
     * @param  string  $mode  'read' or 'write'
     * @param  array  $payload  Query params or request body
     */
    private function execute(string $method, string $path, string $mode, array $payload = []): RouterResult
    {
        $start = microtime(true);

        $result = match ($method) {
            'GET' => $this->api->get($path, $payload),
            'PUT' => $this->api->put($path, $payload),
            'PATCH' => $this->api->patch($path, $payload),
            'DELETE' => $this->api->delete($path),
            'POST' => $this->api->post($path, $payload),
            default => RouterResult::fail("Unsupported method: {$method}", 'unknown'),
        };

        $latencyMs = round((microtime(true) - $start) * 1000, 1);

        $this->errorHandler->logCommand(
            $method,
            $path,
            $this->api->getRouter()->id,
            $this->api->getRouter()->host,
            $latencyMs,
            $result->isSuccess(),
        );

        return $result;
    }
}
