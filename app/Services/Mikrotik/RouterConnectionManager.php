<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages the lifecycle of a connection to a single MikroTik router.
 *
 * Responsibilities:
 *  - Open (build HTTP client with credentials).
 *  - Validate (test connectivity + authentication).
 *  - Close (release resources / mark as disconnected).
 *  - Reconnect (validate + retry with backoff on failure).
 *  - Track connection status (connected, disconnected, degraded).
 *
 * This class is instantiated per-router.  For multi-router management,
 * see RouterConnectionPool.
 *
 * Usage:
 *  $mgr = new RouterConnectionManager($router);
 *  $result = $mgr->open();
 *  if ($result->isSuccess()) { $mgr->validate(); }
 *  $mgr->close();
 */
class RouterConnectionManager
{
    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DEGRADED = 'degraded';

    private MikrotikRouter $router;

    private RouterErrorHandler $errorHandler;

    private ?PendingRequest $httpClient = null;

    private string $status = self::STATUS_DISCONNECTED;

    private ?string $activeHost = null;

    private ?int $localPort = null;

    private bool $localMode = false;

    private ?float $connectedAt = null;

    private ?float $lastActivityAt = null;

    private ?string $lastError = null;

    private int $consecutiveFailures = 0;

    public function __construct(MikrotikRouter $router)
    {
        $this->router = $router;
        $this->errorHandler = new RouterErrorHandler;
        $this->localPort = $router->local_port ? (int) $router->local_port : null;
        $this->localMode = ($router->connection_mode ?? 'tunnel') === 'local_ip';
    }

    /**
     * Open a connection (build and cache the HTTP client).
     *
     * Does NOT actually hit the network — it prepares the client.
     * Call validate() afterwards to confirm connectivity.
     */
    public function open(): RouterResult
    {
        try {
            $this->httpClient = Http::withBasicAuth($this->router->username, $this->router->password)
                ->withoutVerifying()
                ->timeout($this->router->timeout ?? 10);

            $this->status = self::STATUS_CONNECTED;
            $this->connectedAt = microtime(true);
            $this->lastActivityAt = microtime(true);
            $this->lastError = null;
            $this->consecutiveFailures = 0;

            $this->errorHandler->logConnectionSuccess(
                'open',
                $this->router->id,
                $this->router->host,
            );

            Log::debug('RouterOS connection opened', [
                'router_id' => $this->router->id,
                'router_host' => $this->router->host,
            ]);

            return RouterResult::ok('Koneksi dibuka', meta: [
                'router_id' => $this->router->id,
                'status' => self::STATUS_CONNECTED,
            ]);
        } catch (\Exception $e) {
            $this->status = self::STATUS_DISCONNECTED;
            $this->lastError = $e->getMessage();

            return $this->errorHandler->handle($e, 'open', $this->router->id, $this->router->host);
        }
    }

    /**
     * Validate the connection by issuing a lightweight request.
     *
     * This is the "ping" operation — hits /system/resource to confirm
     * the router is reachable and credentials are valid.
     */
    public function validate(): RouterResult
    {
        $start = microtime(true);

        try {
            $client = $this->httpClient ?? $this->buildClient();
            $url = $this->restUrl('/system/resource');
            $res = $client->get($url)->json();
            $latencyMs = round((microtime(true) - $start) * 1000, 1);

            $this->lastActivityAt = microtime(true);
            $this->consecutiveFailures = 0;
            $this->status = self::STATUS_CONNECTED;

            $this->errorHandler->logConnectionSuccess(
                'validate',
                $this->router->id,
                $this->router->host,
                $latencyMs,
            );

            return RouterResult::ok(
                'Validasi OK — '.($res['board-name'] ?? 'MikroTik'),
                $res,
                ['latency_ms' => $latencyMs, 'router_id' => $this->router->id],
            );
        } catch (\Exception $e) {
            $this->consecutiveFailures++;
            $this->lastError = $e->getMessage();

            $errorType = $this->errorHandler->classify($e);

            if (in_array($errorType, [RouterErrorHandler::DNS_ERROR, RouterErrorHandler::CONNECTION_REFUSED, RouterErrorHandler::TIMEOUT, RouterErrorHandler::CONNECTION_RESET, RouterErrorHandler::CONNECTION_CLOSED], true)
                && $this->switchToLocalIp()) {
                return $this->validateViaLocalIp();
            }

            if ($this->consecutiveFailures >= 3) {
                $this->status = self::STATUS_DEGRADED;
            }

            return $this->errorHandler->handle($e, 'validate', $this->router->id, $this->router->host);
        }
    }

    private function validateViaLocalIp(): RouterResult
    {
        $start = microtime(true);

        try {
            $client = $this->httpClient ?? $this->buildClient();
            $url = $this->restUrl('/system/resource');
            $res = $client->get($url)->json();
            $latencyMs = round((microtime(true) - $start) * 1000, 1);

            $this->lastActivityAt = microtime(true);
            $this->consecutiveFailures = 0;
            $this->status = self::STATUS_CONNECTED;

            $this->errorHandler->logConnectionSuccess(
                'validate (IP lokal)',
                $this->router->id,
                $this->router->local_ip,
                $latencyMs,
            );

            return RouterResult::ok(
                'Validasi OK via IP lokal — '.($res['board-name'] ?? 'MikroTik'),
                $res,
                ['latency_ms' => $latencyMs, 'router_id' => $this->router->id, 'via_local_ip' => true],
            );
        } catch (\Exception $e) {
            $this->consecutiveFailures++;
            $this->lastError = $e->getMessage();

            if ($this->consecutiveFailures >= 3) {
                $this->status = self::STATUS_DEGRADED;
            }

            return $this->errorHandler->handle($e, 'validate (IP lokal)', $this->router->id, $this->router->local_ip);
        }
    }

    /**
     * Close the connection and release resources.
     */
    public function close(): RouterResult
    {
        $this->httpClient = null;
        $this->status = self::STATUS_DISCONNECTED;
        $this->connectedAt = null;

        Log::debug('RouterOS connection closed', [
            'router_id' => $this->router->id,
            'router_host' => $this->router->host,
        ]);

        return RouterResult::ok('Koneksi ditutup', meta: [
            'router_id' => $this->router->id,
            'status' => self::STATUS_DISCONNECTED,
        ]);
    }

    /**
     * Attempt to reconnect after a failure.
     *
     * Closes the existing connection, then opens + validates with retry.
     *
     * @param  int  $maxAttempts  Maximum reconnect attempts (default 2)
     */
    public function reconnect(int $maxAttempts = 2): RouterResult
    {
        $this->close();

        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info('RouterOS reconnect attempt', [
                'router_id' => $this->router->id,
                'router_host' => $this->router->host,
                'attempt' => $attempt,
            ]);

            $openResult = $this->open();

            if (! $openResult->isSuccess()) {
                $lastResult = $openResult;

                if ($attempt < $maxAttempts) {
                    usleep(500 * 1000 * $attempt); // Linear backoff: 500ms, 1000ms...
                }

                continue;
            }

            $validateResult = $this->validate();

            if ($validateResult->isSuccess()) {
                return RouterResult::ok(
                    'Reconnect berhasil (attempt '.$attempt.')',
                    $validateResult->getData(),
                    array_merge($validateResult->getMeta(), ['reconnect_attempt' => $attempt]),
                );
            }

            $lastResult = $validateResult;

            if ($attempt < $maxAttempts) {
                usleep(500 * 1000 * $attempt);
            }
        }

        return $lastResult ?? RouterResult::fail('Reconnect gagal', 'connection_refused');
    }

    /**
     * Get the cached HTTP client (or build a fresh one).
     */
    public function getClient(): PendingRequest
    {
        return $this->httpClient ?? $this->buildClient();
    }

    /**
     * Build the REST API URL for a given path.
     */
    public function restUrl(string $path): string
    {
        $isLocal = $this->localMode || $this->activeHost !== null;
        $defaultPort = $this->router->api_ssl_port ?? $this->router->port;
        $port = $isLocal ? ($this->localPort ?? 80) : $defaultPort;
        $scheme = $isLocal
            ? ($port == 443 ? 'https' : 'http')
            : (($port == 443 || $this->router->connection_type === 'api_ssl') ? 'https' : 'http');
        $host = $this->activeHost ?? $this->router->host;

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
        Log::channel('mikrotik')->warning('RouterOS connection manager fallback ke IP lokal', [
            'router_id' => $this->router->id,
            'router_host' => $this->router->host,
            'local_ip' => $this->router->local_ip,
        ]);

        return true;
    }

    // ═══════════════════════════════════════════
    //  STATUS & METRICS
    // ═══════════════════════════════════════════

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function getConnectedAt(): ?float
    {
        return $this->connectedAt;
    }

    public function getLastActivityAt(): ?float
    {
        return $this->lastActivityAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function getRouter(): MikrotikRouter
    {
        return $this->router;
    }

    /**
     * Get elapsed seconds since connection was opened.
     */
    public function getConnectionAge(): ?float
    {
        return $this->connectedAt ? round(microtime(true) - $this->connectedAt, 1) : null;
    }

    /**
     * Get elapsed seconds since last activity.
     */
    public function getIdleTime(): ?float
    {
        return $this->lastActivityAt ? round(microtime(true) - $this->lastActivityAt, 1) : null;
    }

    /**
     * Check if the connection has been idle longer than the given timeout.
     */
    public function isIdle(int $timeoutSeconds = 300): bool
    {
        $idle = $this->getIdleTime();

        return $idle !== null && $idle > $timeoutSeconds;
    }

    /**
     * Record that activity happened (called after successful operations).
     */
    public function touch(): void
    {
        $this->lastActivityAt = microtime(true);
    }

    private function buildClient(): PendingRequest
    {
        return Http::withBasicAuth($this->router->username, $this->router->password)
            ->withoutVerifying()
            ->timeout($this->router->timeout ?? 10);
    }
}
