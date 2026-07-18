<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use Carbon\Carbon;

/**
 * Singleton registry that tracks active Mikrotik router connections.
 *
 * This is NOT a TCP connection pool (RouterOS REST API is HTTP-based and
 * stateless).  It serves as:
 *
 *  1. A central registry of MikrotikRouter models for the current request.
 *  2. A connection-state tracker (last_seen, status, latency, idle time).
 *  3. A place to store per-router metadata across modules.
 *  4. Deduplication: prevents duplicate entries for the same router.
 *  5. Idle timeout: marks connections stale after inactivity.
 *  6. Garbage collection: cleans up stale entries.
 *
 * Usage:
 *  $pool = RouterConnectionPool::getInstance();
 *  $pool->register($router, $latencyMs);
 *  $pool->gc(); // clean idle entries
 *  $info = $pool->getConnection($router->id);
 *  $pool->markFailed($router->id, 'timeout');
 */
class RouterConnectionPool
{
    private static ?self $instance = null;

    /**
     * Connection registry: router_id => [...metadata].
     *
     * @var array<int, array{router: MikrotikRouter, status: string, latency_ms: float, last_seen: string, error_type: string, meta: array}>
     */
    private array $connections = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Reset the singleton (mainly for testing or long-running processes).
     */
    public static function flush(): void
    {
        self::$instance = null;
    }

    /**
     * Register a router as connected.
     *
     * Deduplicates: if the router is already registered, updates in place.
     *
     * @param  MikrotikRouter  $router  The router model
     * @param  float  $latencyMs  Round-trip latency in milliseconds
     * @param  array  $meta  Additional metadata to store
     */
    public function register(MikrotikRouter $router, float $latencyMs = 0, array $meta = []): void
    {
        $existing = $this->connections[$router->id] ?? null;

        $this->connections[$router->id] = [
            'router' => $router,
            'status' => 'online',
            'latency_ms' => $latencyMs,
            'last_seen' => now()->toIso8601String(),
            'error_type' => '',
            'meta' => array_merge($existing['meta'] ?? [], $meta),
        ];
    }

    /**
     * Mark a router as failed.
     *
     * @param  int  $routerId  MikrotikRouter ID
     * @param  string  $errorType  Error type from RouterErrorHandler
     * @param  string  $message  Optional error message
     */
    public function markFailed(int $routerId, string $errorType = 'unknown', string $message = ''): void
    {
        if (isset($this->connections[$routerId])) {
            $this->connections[$routerId]['status'] = 'offline';
            $this->connections[$routerId]['error_type'] = $errorType;
            $this->connections[$routerId]['meta']['last_error'] = $message;
        } else {
            $this->connections[$routerId] = [
                'router' => MikrotikRouter::find($routerId),
                'status' => 'offline',
                'latency_ms' => 0,
                'last_seen' => now()->toIso8601String(),
                'error_type' => $errorType,
                'meta' => ['last_error' => $message],
            ];
        }
    }

    /**
     * Get connection info for a router.
     *
     * @return array{router: MikrotikRouter, status: string, latency_ms: float, last_seen: string, error_type: string, meta: array}|null
     */
    public function getConnection(int $routerId): ?array
    {
        return $this->connections[$routerId] ?? null;
    }

    /**
     * Get all registered connections.
     *
     * @return array<int, array{router: MikrotikRouter, status: string, latency_ms: float, last_seen: string, error_type: string, meta: array}>
     */
    public function getAll(): array
    {
        return $this->connections;
    }

    /**
     * Get only online routers.
     *
     * @return array<int, array>
     */
    public function getOnline(): array
    {
        return array_filter($this->connections, fn ($c) => $c['status'] === 'online');
    }

    /**
     * Get only offline routers.
     *
     * @return array<int, array>
     */
    public function getOffline(): array
    {
        return array_filter($this->connections, fn ($c) => $c['status'] === 'offline');
    }

    /**
     * Get routers that have been idle longer than the given timeout.
     *
     * @param  int  $timeoutSeconds  Idle threshold in seconds (default 300)
     * @return array<int, array>
     */
    public function getIdle(int $timeoutSeconds = 300): array
    {
        $cutoff = now()->subSeconds($timeoutSeconds)->toIso8601String();

        return array_filter($this->connections, fn ($c) => $c['last_seen'] < $cutoff);
    }

    /**
     * Count connections by status.
     */
    public function countByStatus(): array
    {
        $counts = ['online' => 0, 'offline' => 0, 'unknown' => 0];

        foreach ($this->connections as $conn) {
            $status = $conn['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Check if a specific router is tracked as online.
     */
    public function isOnline(int $routerId): bool
    {
        return isset($this->connections[$routerId]) && $this->connections[$routerId]['status'] === 'online';
    }

    /**
     * Remove a router from the registry.
     */
    public function unregister(int $routerId): void
    {
        unset($this->connections[$routerId]);
    }

    /**
     * Get average latency across all online connections.
     */
    public function getAverageLatency(): float
    {
        $online = $this->getOnline();

        if (empty($online)) {
            return 0;
        }

        $total = array_sum(array_column($online, 'latency_ms'));

        return round($total / count($online), 1);
    }

    /**
     * Garbage collection: remove entries that have been idle too long.
     *
     * Offline entries older than $offlineTimeout and online entries older
     * than $onlineTimeout are removed.
     *
     * @param  int  $offlineTimeout  Seconds before offline entries are removed (default 600)
     * @param  int  $onlineTimeout  Seconds before online entries are removed (default 3600)
     * @return int Number of entries removed
     */
    public function gc(int $offlineTimeout = 600, int $onlineTimeout = 3600): int
    {
        $removed = 0;
        $now = now();

        foreach ($this->connections as $routerId => $conn) {
            $lastSeen = Carbon::parse($conn['last_seen']);
            $ageSeconds = $now->diffInSeconds($lastSeen);

            $timeout = $conn['status'] === 'online' ? $onlineTimeout : $offlineTimeout;

            if ($ageSeconds > $timeout) {
                unset($this->connections[$routerId]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Get the total number of registered connections.
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * Get all router IDs currently in the pool.
     *
     * @return array<int>
     */
    public function getRouterIds(): array
    {
        return array_keys($this->connections);
    }
}
