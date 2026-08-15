<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level RouterOS REST API communication service.
 *
 * Handles HTTP transport, URL construction, request/response formatting,
 * retry logic (via RouterRetryPolicy), connection management (via
 * RouterConnectionManager), and timeout management.  This is the single
 * class that physically talks to MikroTik devices via REST API.
 *
 * IMPORTANT: This class is intentionally low-level.  Use RouterCommandService
 * for typed, high-level operations.
 *
 * Connection model:
 *  - Each call creates a fresh HTTP request (Laravel HTTP client).
 *  - Connections are NOT persistent across calls (REST API is stateless).
 *  - Retry is handled by RouterRetryPolicy with exponential backoff + jitter.
 *  - Connection lifecycle is managed by RouterConnectionManager.
 *  - All errors are classified by RouterErrorHandler.
 */
class RouterOSApiService
{
    private MikrotikRouter $router;

    private RouterErrorHandler $errorHandler;

    private RouterRetryPolicy $retryPolicy;

    private RouterConnectionManager $connectionManager;

    private ?string $activeHost = null;

    private ?int $localPort = null;

    private bool $localMode = false;

    /**
     * @param  MikrotikRouter  $router  The router model with connection credentials
     * @param  RouterRetryPolicy|null  $retryPolicy  Retry policy (default: RouterRetryPolicy::default())
     * @param  RouterConnectionManager|null  $connectionManager  Connection manager (auto-created if null)
     */
    public function __construct(
        MikrotikRouter $router,
        ?RouterRetryPolicy $retryPolicy = null,
        ?RouterConnectionManager $connectionManager = null,
    ) {
        $this->router = $router;
        $this->errorHandler = new RouterErrorHandler;
        $this->retryPolicy = $retryPolicy ?? RouterRetryPolicy::default();
        $this->connectionManager = $connectionManager ?? new RouterConnectionManager($router);
        $this->localPort = $router->local_port ? (int) $router->local_port : null;
        $this->localMode = ($router->connection_mode ?? 'tunnel') === 'local_ip';
    }

    /**
     * TTL cache "active host = local" agar percobaan host utama yang mati
     * tidak diulang pada tiap request (menghemat ~2-3 detik per call).
     */
    private const LOCAL_HOST_CACHE_TTL = 600;

    private function localHostCacheKey(): string
    {
        return "mikrotik:active_local:{$this->router->id}";
    }

    private function cachedLocalActive(): bool
    {
        return (bool) Cache::get($this->localHostCacheKey(), false);
    }

    private function usingCachedLocal(): bool
    {
        return $this->activeHost === null && ! $this->localMode && $this->cachedLocalActive();
    }

    private function isUsingLocalIp(): bool
    {
        return $this->localMode || $this->activeHost === $this->router->local_ip || $this->usingCachedLocal();
    }

    private function rememberWorkingHost(): void
    {
        if ($this->isUsingLocalIp()) {
            Cache::put($this->localHostCacheKey(), true, now()->addSeconds(self::LOCAL_HOST_CACHE_TTL));
        } else {
            Cache::forget($this->localHostCacheKey());
        }
    }

    private function clearLocalHostCache(): void
    {
        Cache::forget($this->localHostCacheKey());
    }

    /**
     * Build a configured HTTP client for this router.
     *
     * Uses Basic Auth, disables SSL verification (MikroTik self-signed certs),
     * and applies the per-router timeout from the model.
     */
    public function client(): PendingRequest
    {
        return Http::withBasicAuth($this->router->username, $this->router->password)
            ->withoutVerifying()
            ->timeout($this->router->timeout ?? 10);
    }

    /**
     * Build the full REST API URL for a given path.
     *
     * @param  string  $path  RouterOS REST path (e.g. "/ppp/active")
     * @return string Full URL (e.g. "http://10.0.0.1:80/rest/ppp/active")
     */
    public function restUrl(string $path): string
    {
        $isLocal = $this->localMode || $this->activeHost !== null || $this->usingCachedLocal();
        $defaultPort = $this->router->api_ssl_port ?? $this->router->port;
        $port = $isLocal ? ($this->localPort ?? 80) : $defaultPort;
        $scheme = $isLocal
            ? ($port == 443 ? 'https' : 'http')
            : (($port == 443 || $this->router->connection_type === 'api_ssl') ? 'https' : 'http');
        $host = $this->activeHost ?? ($this->usingCachedLocal() ? $this->router->local_ip : $this->router->host);

        return "{$scheme}://{$host}:{$port}/rest{$path}";
    }

    /**
     * Pindahkan koneksi ke IP lokal jika host utama tidak reachable.
     */
    public function switchToLocalIp(): bool
    {
        if (! $this->router->hasLocalIpFallback() || $this->activeHost === $this->router->local_ip) {
            return false;
        }

        $this->activeHost = $this->router->local_ip;
        Cache::put($this->localHostCacheKey(), true, now()->addSeconds(self::LOCAL_HOST_CACHE_TTL));
        Log::channel('mikrotik')->warning('RouterOS fallback ke IP lokal', [
            'router_id' => $this->router->id,
            'router_host' => $this->router->host,
            'local_ip' => $this->router->local_ip,
        ]);

        return true;
    }

    private function isConnectionError(string $errorType): bool
    {
        return in_array($errorType, [
            RouterErrorHandler::DNS_ERROR,
            RouterErrorHandler::CONNECTION_REFUSED,
            RouterErrorHandler::TIMEOUT,
            RouterErrorHandler::CONNECTION_RESET,
            RouterErrorHandler::CONNECTION_CLOSED,
        ], true);
    }

    // ═══════════════════════════════════════════
    //  PUBLIC EXECUTION METHODS
    // ═══════════════════════════════════════════

    /**
     * Execute a GET request and return parsed JSON.
     *
     * @param  string  $path  REST path (e.g. "/system/resource")
     * @param  array  $query  Query parameters
     * @return RouterResult Always returns a RouterResult (data is [] on failure)
     */
    public function get(string $path, array $query = []): RouterResult
    {
        return $this->executeWithRetry('GET', $path, $query);
    }

    /**
     * Execute a PUT request (create a resource).
     *
     * @param  string  $path  REST path (e.g. "/ppp/secret")
     * @param  array  $data  Request body
     */
    public function put(string $path, array $data = []): RouterResult
    {
        return $this->executeWithRetry('PUT', $path, $data);
    }

    /**
     * Execute a PATCH request (update a resource).
     *
     * @param  string  $path  REST path with ID (e.g. "/ppp/secret/*1A")
     * @param  array  $data  Fields to update
     */
    public function patch(string $path, array $data = []): RouterResult
    {
        return $this->executeWithRetry('PATCH', $path, $data);
    }

    /**
     * Execute a DELETE request.
     *
     * @param  string  $path  REST path with ID (e.g. "/ppp/secret/*1A")
     */
    public function delete(string $path): RouterResult
    {
        return $this->executeWithRetry('DELETE', $path);
    }

    /**
     * Execute a POST request.
     *
     * @param  string  $path  REST path (e.g. "/system/backup")
     * @param  array  $data  Request body
     */
    public function post(string $path, array $data = []): RouterResult
    {
        return $this->executeWithRetry('POST', $path, $data);
    }

    /**
     * Test connection to the router.
     *
     * Returns success with board-name on success, or a classified error.
     */
    public function testConnection(): RouterResult
    {
        $start = microtime(true);

        try {
            $res = $this->client()->get($this->restUrl('/system/resource'))->json();
            $latencyMs = round((microtime(true) - $start) * 1000, 1);

            $this->errorHandler->logConnectionSuccess('testConnection', $this->router->id, $this->router->host, $latencyMs);

            return RouterResult::ok(
                'Terhubung ke '.($res['board-name'] ?? 'MikroTik'),
                $res,
                ['latency_ms' => $latencyMs],
            );
        } catch (\Exception $e) {
            if ($this->isConnectionError($this->errorHandler->classify($e)) && $this->switchToLocalIp()) {
                return $this->testConnectionViaLocalIp();
            }

            return $this->errorHandler->handle(
                $e,
                'testConnection',
                $this->router->id,
                $this->router->host,
            );
        }
    }

    private function testConnectionViaLocalIp(): RouterResult
    {
        $start = microtime(true);

        try {
            $res = $this->client()->get($this->restUrl('/system/resource'))->json();
            $latencyMs = round((microtime(true) - $start) * 1000, 1);

            $this->errorHandler->logConnectionSuccess('testConnection (IP lokal)', $this->router->id, $this->router->local_ip, $latencyMs);

            return RouterResult::ok(
                'Terhubung ke '.($res['board-name'] ?? 'MikroTik').' (via IP lokal)',
                $res,
                ['latency_ms' => $latencyMs, 'via_local_ip' => true],
            );
        } catch (\Exception $e) {
            return $this->errorHandler->handle(
                $e,
                'testConnection (IP lokal)',
                $this->router->id,
                $this->router->local_ip,
            );
        }
    }

    /**
     * Measure round-trip latency to the router (in milliseconds).
     *
     * @return float|null Latency in ms, or null if unreachable
     */
    public function getLatency(): ?float
    {
        $start = microtime(true);

        try {
            $this->client()->get($this->restUrl('/system/resource'));

            return round((microtime(true) - $start) * 1000, 1);
        } catch (\Exception $e) {
            if ($this->isConnectionError($this->errorHandler->classify($e)) && $this->switchToLocalIp()) {
                return $this->getLatencyViaLocalIp();
            }

            $this->errorHandler->logError(
                $e,
                'getLatency',
                $this->errorHandler->classify($e),
                $this->router->id,
                $this->router->host,
            );

            return null;
        }
    }

    private function getLatencyViaLocalIp(): ?float
    {
        $start = microtime(true);

        try {
            $this->client()->get($this->restUrl('/system/resource'));

            return round((microtime(true) - $start) * 1000, 1);
        } catch (\Exception $e) {
            $this->errorHandler->logError(
                $e,
                'getLatency (IP lokal)',
                $this->errorHandler->classify($e),
                $this->router->id,
                $this->router->local_ip,
            );

            return null;
        }
    }

    /**
     * Get the router model this service is connected to.
     */
    public function getRouter(): MikrotikRouter
    {
        return $this->router;
    }

    /**
     * Get the error handler instance.
     */
    public function getErrorHandler(): RouterErrorHandler
    {
        return $this->errorHandler;
    }

    /**
     * Get the retry policy.
     */
    public function getRetryPolicy(): RouterRetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * Get the connection manager.
     */
    public function getConnectionManager(): RouterConnectionManager
    {
        return $this->connectionManager;
    }

    // ═══════════════════════════════════════════
    //  PRIVATE EXECUTION ENGINE
    // ═══════════════════════════════════════════

    /**
     * Execute an HTTP request with retry logic via RouterRetryPolicy.
     *
     * Flow:
     *  1. Attempt the request.
     *  2. On success, return RouterResult::ok() and touch the connection manager.
     *  3. On failure, classify the error.
     *  4. If retryable per policy, wait (exponential backoff) and retry.
     *  5. Otherwise, return RouterResult::fail() with classified error.
     *
     * @param  string  $method  HTTP method (GET, PUT, PATCH, DELETE, POST)
     * @param  string  $path  REST path
     * @param  array  $payload  Request body or query params
     */
    private function executeWithRetry(string $method, string $path, array $payload = []): RouterResult
    {
        $operation = "{$method} {$path}";
        $lastException = null;

        $maxAttempts = $this->retryPolicy->getMaxAttempts();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                $this->errorHandler->logConnectionAttempt($operation, $this->router->id, $this->router->host, $attempt);
                $this->retryPolicy->waitBeforeRetry($attempt - 1);
            }

            try {
                $start = microtime(true);
                $response = $this->makeRequest($method, $path, $payload);
                $latencyMs = round((microtime(true) - $start) * 1000, 1);

                $data = $response->json();

                // Touch connection manager on success
                $this->connectionManager->touch();
                $this->rememberWorkingHost();

                return RouterResult::ok('', $data, [
                    'latency_ms' => $latencyMs,
                    'router_id' => $this->router->id,
                    'attempt' => $attempt,
                ]);
            } catch (RequestException $e) {
                $lastException = $e;
                $errorType = $this->errorHandler->classify($e);

                if ($this->isConnectionError($errorType)) {
                    if ($this->usingCachedLocal()) {
                        $this->clearLocalHostCache();
                        Log::debug("RouterOS {$operation} gagal via IP lokal ({$errorType}), retry via host utama...");

                        continue;
                    }

                    if ($this->switchToLocalIp()) {
                        Log::debug("RouterOS {$operation} gagal di host utama ({$errorType}), retry via IP lokal...");

                        continue;
                    }
                }

                if ($this->retryPolicy->shouldRetry($errorType, $attempt)) {
                    Log::debug("RouterOS {$operation} attempt {$attempt} failed ({$errorType}), retrying...");

                    continue;
                }

                return $this->errorHandler->handle($e, $operation, $this->router->id, $this->router->host);
            } catch (\Exception $e) {
                $lastException = $e;
                $errorType = $this->errorHandler->classify($e);

                if ($this->isConnectionError($errorType)) {
                    if ($this->usingCachedLocal()) {
                        $this->clearLocalHostCache();
                        Log::debug("RouterOS {$operation} gagal via IP lokal ({$errorType}), retry via host utama...");

                        continue;
                    }

                    if ($this->switchToLocalIp()) {
                        Log::debug("RouterOS {$operation} gagal di host utama ({$errorType}), retry via IP lokal...");

                        continue;
                    }
                }

                if ($this->retryPolicy->shouldRetry($errorType, $attempt)) {
                    Log::debug("RouterOS {$operation} attempt {$attempt} failed ({$errorType}), retrying...");

                    continue;
                }

                return $this->errorHandler->handle($e, $operation, $this->router->id, $this->router->host);
            }
        }

        // Should not reach here, but handle defensively
        if ($lastException) {
            return $this->errorHandler->handle($lastException, $operation, $this->router->id, $this->router->host);
        }

        return RouterResult::fail('Unexpected execution failure', 'unknown');
    }

    /**
     * Make the actual HTTP request.
     *
     * @return Response
     *
     * @throws \Exception On any HTTP or network error
     */
    private function makeRequest(string $method, string $path, array $payload = [])
    {
        $url = $this->restUrl($path);
        $client = $this->client();

        return match (strtoupper($method)) {
            'GET' => $client->get($url, $payload),
            'PUT' => $client->put($url, $payload),
            'PATCH' => $client->patch($url, $payload),
            'DELETE' => $client->delete($url),
            'POST' => $client->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }
}
