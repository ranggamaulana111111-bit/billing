<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use App\Services\MikrotikSshService;
use Illuminate\Support\Facades\Log;

/**
 * Top-level orchestrator for all MikroTik communication.
 *
 * This is the SINGLE ENTRY POINT that all modules should use.
 * It coordinates between:
 *  - RouterOSApiService       (REST API via HTTP, with retry + connection manager)
 *  - RouterCommandService     (typed command facade with logging)
 *  - RouterConnectionManager  (lifecycle: open/validate/close/reconnect)
 *  - RouterConnectionPool     (multi-router state tracker, idle timeout, GC)
 *  - RouterRetryPolicy        (exponential backoff + jitter)
 *  - RouterErrorHandler       (error classification, severity, dedicated log)
 *  - MikrotikSshService       (SSH fallback for read-only ops)
 *
 * Usage:
 *  $service = new RouterConnectionService($router);
 *  $result  = $service->run(fn (RouterCommandService $cmd) => $cmd->getPppActive());
 *
 *  // or for simple one-off calls:
 *  $result = $service->getSystemResource();
 *
 *  // or for multiple routers:
 *  $results = RouterConnectionService::forAllRouters(function (RouterCommandService $cmd) {
 *      return $cmd->getSystemResource();
 *  });
 *
 *  // or with lifecycle management:
 *  $service->withManager(function (RouterConnectionManager $mgr) {
 *      $mgr->open();
 *      $mgr->validate();
 *      // ... do work ...
 *      $mgr->close();
 *  });
 *
 * IMPORTANT: The existing MikrotikService is NOT modified.  This new service
 * runs in parallel.  New modules (NOC MikroTik Center) use this service.
 * Existing billing modules continue using MikrotikService.
 */
class RouterConnectionService
{
    private MikrotikRouter $router;

    private RouterOSApiService $api;

    private RouterCommandService $command;

    private RouterConnectionManager $connectionManager;

    private RouterConnectionPool $pool;

    private RouterErrorHandler $errorHandler;

    private ?MikrotikSshService $ssh = null;

    public function __construct(
        MikrotikRouter $router,
        ?RouterRetryPolicy $retryPolicy = null,
    ) {
        $this->router = $router;
        $this->errorHandler = new RouterErrorHandler;
        $this->connectionManager = new RouterConnectionManager($router);
        $this->pool = RouterConnectionPool::getInstance();
        $this->api = new RouterOSApiService($router, $retryPolicy, $this->connectionManager);
        $this->command = new RouterCommandService($router);

        if ($router->ssh_port) {
            try {
                $this->ssh = new MikrotikSshService($router);
            } catch (\Exception $e) {
                Log::warning('SSH init failed for router '.$router->name.': '.$e->getMessage());
            }
        }
    }

    /**
     * Create from a router ID.
     */
    public static function forRouter(int $routerId, ?RouterRetryPolicy $retryPolicy = null): ?self
    {
        $router = MikrotikRouter::find($routerId);

        return $router ? new self($router, $retryPolicy) : null;
    }

    /**
     * Create from the first active general-purpose router (fallback).
     */
    public static function default(): ?self
    {
        $router = MikrotikRouter::where('is_active', true)
            ->whereIn('type', ['general'])
            ->first();

        return $router ? new self($router) : null;
    }

    /**
     * Execute a callback against every active router.
     *
     * Runs garbage collection on the pool before processing.
     *
     * @param  callable(RouterCommandService, MikrotikRouter): mixed  $callback
     * @return array<int, array{router: MikrotikRouter, result: mixed}>
     */
    public static function forAllRouters(callable $callback): array
    {
        $pool = RouterConnectionPool::getInstance();
        $pool->gc(); // Clean stale entries before processing

        $routers = MikrotikRouter::where('is_active', true)->get();
        $results = [];

        foreach ($routers as $router) {
            try {
                $service = new self($router);
                $result = $callback($service->command, $router);

                $latencyMs = 0;
                if ($result instanceof RouterResult) {
                    $latencyMs = (float) $result->getMetaValue('latency_ms', 0);
                }

                $pool->register($router, $latencyMs);
            } catch (\Exception $e) {
                $errorHandler = new RouterErrorHandler;
                $errorType = $errorHandler->classify($e);
                $pool->markFailed($router->id, $errorType, $e->getMessage());
                $result = RouterResult::fail($e->getMessage(), $errorType);
            }

            $results[$router->id] = [
                'router' => $router,
                'result' => $result,
            ];
        }

        return $results;
    }

    /**
     * Execute a command through the high-level command service.
     *
     * This is the preferred way to run operations:
     *
     *   $result = $service->run(fn ($cmd) => $cmd->getPppActive());
     *
     * @param  callable(RouterCommandService): RouterResult  $callback
     */
    public function run(callable $callback): RouterResult
    {
        return $callback($this->command);
    }

    /**
     * Execute code with explicit connection lifecycle management.
     *
     * Opens the connection, runs the callback, and closes it.
     * Handles errors gracefully.
     *
     *   $service->withManager(function (RouterConnectionManager $mgr) {
     *       $mgr->open();
     *       $result = $mgr->validate();
     *       if ($result->isSuccess()) {
     *           // ... do work ...
     *       }
     *   });
     *
     * @param  callable(RouterConnectionManager): void  $callback
     */
    public function withManager(callable $callback): void
    {
        try {
            $callback($this->connectionManager);
        } finally {
            $this->connectionManager->close();
        }
    }

    /**
     * Attempt to reconnect to the router.
     *
     * @param  int  $maxAttempts  Maximum reconnect attempts
     */
    public function reconnect(int $maxAttempts = 2): RouterResult
    {
        $result = $this->connectionManager->reconnect($maxAttempts);

        if ($result->isSuccess()) {
            $this->pool->register($this->router, (float) $result->getMetaValue('latency_ms', 0));
        } else {
            $this->pool->markFailed($this->router->id, $result->getErrorType(), $result->getMessage());
        }

        return $result;
    }

    /**
     * Execute a raw RouterOS REST operation.
     *
     * Convenience wrapper around the low-level API service.
     */
    public function rawGet(string $path, array $query = []): RouterResult
    {
        return $this->api->get($path, $query);
    }

    public function rawPut(string $path, array $data = []): RouterResult
    {
        return $this->api->put($path, $data);
    }

    public function rawPatch(string $path, array $data = []): RouterResult
    {
        return $this->api->patch($path, $data);
    }

    public function rawDelete(string $path): RouterResult
    {
        return $this->api->delete($path);
    }

    // ═══════════════════════════════════════════
    //  QUICK METHODS (shortcut for common ops)
    // ═══════════════════════════════════════════

    /**
     * Test connection and register in pool.
     */
    public function testConnection(): RouterResult
    {
        $result = $this->api->testConnection();

        if ($result->isSuccess()) {
            $this->pool->register($this->router, (float) $result->getMetaValue('latency_ms', 0));
        } else {
            $this->pool->markFailed($this->router->id, $result->getErrorType(), $result->getMessage());
        }

        return $result;
    }

    /**
     * Get system resource and register status in pool.
     */
    public function getSystemResource(): RouterResult
    {
        $result = $this->command->getSystemResource();

        $this->updatePoolStatus($result);

        return $result;
    }

    /**
     * Get system identity.
     */
    public function getSystemIdentity(): RouterResult
    {
        return $this->command->getSystemIdentity();
    }

    /**
     * Get latency.
     */
    public function getLatency(): RouterResult
    {
        return $this->command->getLatency();
    }

    /**
     * Get all interfaces.
     */
    public function getInterfaces(): RouterResult
    {
        return $this->command->getInterfaces();
    }

    /**
     * Get active PPP sessions.
     */
    public function getPppActive(): RouterResult
    {
        return $this->command->getPppActive();
    }

    /**
     * Get active hotspot sessions.
     */
    public function getHotspotActive(): RouterResult
    {
        return $this->command->getHotspotActive();
    }

    /**
     * Get simple queues.
     */
    public function getSimpleQueues(): RouterResult
    {
        return $this->command->getSimpleQueues();
    }

    // ═══════════════════════════════════════════
    //  SSH FALLBACK
    // ═══════════════════════════════════════════

    /**
     * Check if SSH fallback is available for this router.
     */
    public function hasSsh(): bool
    {
        return $this->ssh !== null;
    }

    /**
     * Get the SSH service instance (if available).
     */
    public function getSsh(): ?MikrotikSshService
    {
        return $this->ssh;
    }

    /**
     * Execute a read-only command via SSH fallback.
     *
     * @param  string  $method  MikrotikSshService method name
     * @param  mixed  ...$args  Method arguments
     */
    public function sshRead(string $method, mixed ...$args): RouterResult
    {
        if (! $this->ssh) {
            return RouterResult::fail(
                'SSH tidak tersedia untuk router ini',
                'unknown',
                meta: ['router_id' => $this->router->id],
            );
        }

        try {
            $data = $this->ssh->$method(...$args);

            return RouterResult::ok('', $data, ['transport' => 'ssh', 'router_id' => $this->router->id]);
        } catch (\Exception $e) {
            $errorHandler = new RouterErrorHandler;

            return $errorHandler->handle($e, "SSH {$method}", $this->router->id, $this->router->host);
        }
    }

    // ═══════════════════════════════════════════
    //  POOL & MANAGER ACCESSORS
    // ═══════════════════════════════════════════

    /**
     * Get the connection pool.
     */
    public function getPool(): RouterConnectionPool
    {
        return $this->pool;
    }

    /**
     * Get the connection manager for this router.
     */
    public function getConnectionManager(): RouterConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * Get aggregate pool stats.
     */
    public static function getPoolStats(): array
    {
        $pool = RouterConnectionPool::getInstance();

        return [
            'counts' => $pool->countByStatus(),
            'average_latency' => $pool->getAverageLatency(),
            'total' => count($pool->getAll()),
            'idle_count' => count($pool->getIdle()),
        ];
    }

    /**
     * Run garbage collection on the pool.
     *
     * @param  int  $offlineTimeout  Seconds before offline entries are removed
     * @param  int  $onlineTimeout  Seconds before online entries are removed
     * @return int Number of entries removed
     */
    public static function gc(int $offlineTimeout = 600, int $onlineTimeout = 3600): int
    {
        return RouterConnectionPool::getInstance()->gc($offlineTimeout, $onlineTimeout);
    }

    /**
     * Get the command service (for direct access).
     */
    public function getCommand(): RouterCommandService
    {
        return $this->command;
    }

    /**
     * Get the API service (for direct access).
     */
    public function getApi(): RouterOSApiService
    {
        return $this->api;
    }

    /**
     * Get the router model.
     */
    public function getRouter(): MikrotikRouter
    {
        return $this->router;
    }

    // ═══════════════════════════════════════════
    //  PRIVATE
    // ═══════════════════════════════════════════

    private function updatePoolStatus(RouterResult $result): void
    {
        if ($result->isSuccess()) {
            $this->pool->register($this->router, (float) $result->getMetaValue('latency_ms', 0));
        } else {
            $this->pool->markFailed($this->router->id, $result->getErrorType(), $result->getMessage());
        }
    }
}
