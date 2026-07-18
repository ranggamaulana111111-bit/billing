<?php

namespace App\Services\Mikrotik;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centralized error classification, humanization, and logging for RouterOS operations.
 *
 * Every RouterOS API exception passes through here to produce a consistent
 * error type code and human-readable message.  Logs are written to the
 * 'mikrotik' channel with structured context.
 *
 * Error type constants are used across the codebase for machine-readable checks:
 *  - if ($result->getErrorType() === RouterErrorHandler::TIMEOUT) { ... }
 */
class RouterErrorHandler
{
    /**
     * Error type constants.
     */
    public const DNS_ERROR = 'dns_error';

    public const CONNECTION_REFUSED = 'connection_refused';

    public const TIMEOUT = 'timeout';

    public const SSL_ERROR = 'ssl_error';

    public const AUTH_FAILED = 'auth_failed';

    public const API_DISABLED = 'api_disabled';

    public const CONNECTION_RESET = 'connection_reset';

    public const CONNECTION_CLOSED = 'connection_closed';

    public const INVALID_RESPONSE = 'invalid_response';

    public const UNKNOWN = 'unknown';

    /**
     * Error severity levels for log routing.
     */
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Human-readable messages for each error type.
     */
    private const HUMAN_MESSAGES = [
        self::DNS_ERROR => 'DNS tidak dapat meresolve host. Pastikan hostname/IP benar.',
        self::CONNECTION_REFUSED => 'Koneksi ditolak. Pastikan REST API aktif di port yang benar.',
        self::TIMEOUT => 'Koneksi timeout. Periksa jaringan atau firewall.',
        self::SSL_ERROR => 'Error SSL/TLS. Coba gunakan HTTP atau periksa sertifikat.',
        self::AUTH_FAILED => 'Autentikasi gagal. Periksa username/password.',
        self::API_DISABLED => 'REST API belum diaktifkan di MikroTik RouterOS.',
        self::CONNECTION_RESET => 'Koneksi terputus. Router mungkin sedang restart atau down.',
        self::CONNECTION_CLOSED => 'Koneksi ditutup oleh server. Periksa stabilitas jaringan.',
        self::INVALID_RESPONSE => 'Respons dari router tidak valid atau tidak dapat diproses.',
        self::UNKNOWN => 'Kesalahan tidak diketahui.',
    ];

    /**
     * Error severity classification.
     */
    private const ERROR_SEVERITY = [
        self::DNS_ERROR => self::SEVERITY_HIGH,
        self::CONNECTION_REFUSED => self::SEVERITY_HIGH,
        self::TIMEOUT => self::SEVERITY_MEDIUM,
        self::SSL_ERROR => self::SEVERITY_HIGH,
        self::AUTH_FAILED => self::SEVERITY_CRITICAL,
        self::API_DISABLED => self::SEVERITY_CRITICAL,
        self::CONNECTION_RESET => self::SEVERITY_MEDIUM,
        self::CONNECTION_CLOSED => self::SEVERITY_MEDIUM,
        self::INVALID_RESPONSE => self::SEVERITY_LOW,
        self::UNKNOWN => self::SEVERITY_MEDIUM,
    ];

    /**
     * Classify an exception into a machine-readable error type.
     *
     * @param  Throwable  $exception  The caught exception
     * @return string One of the error type constants
     */
    public function classify(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'curl error 6') || str_contains($message, 'could not resolve host')) {
            return self::DNS_ERROR;
        }

        if (str_contains($message, 'curl error 7') || str_contains($message, 'connection refused')) {
            return self::CONNECTION_REFUSED;
        }

        if (str_contains($message, 'curl error 28') || str_contains($message, 'timed out')) {
            return self::TIMEOUT;
        }

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate') || str_contains($message, 'curl error 60')) {
            return self::SSL_ERROR;
        }

        if (str_contains($message, '401') || str_contains($message, 'unauthorized') || str_contains($message, 'login failed')) {
            return self::AUTH_FAILED;
        }

        if (str_contains($message, 'api disabled') || str_contains($message, 'api not enabled')) {
            return self::API_DISABLED;
        }

        if (str_contains($message, 'curl error 56') || str_contains($message, 'connection reset by peer')) {
            return self::CONNECTION_RESET;
        }

        if (str_contains($message, 'curl error 52') || str_contains($message, 'empty reply') || str_contains($message, 'connection closed')) {
            return self::CONNECTION_CLOSED;
        }

        if (str_contains($message, 'invalid') || str_contains($message, 'malformed') || str_contains($message, 'json')) {
            return self::INVALID_RESPONSE;
        }

        return self::UNKNOWN;
    }

    /**
     * Convert an error type code into a user-friendly Indonesian message.
     */
    public function humanize(string $errorType, string $originalMessage = ''): string
    {
        return self::HUMAN_MESSAGES[$errorType] ?? ($originalMessage ?: self::HUMAN_MESSAGES[self::UNKNOWN]);
    }

    /**
     * Get the severity level for an error type.
     */
    public function getSeverity(string $errorType): string
    {
        return self::ERROR_SEVERITY[$errorType] ?? self::SEVERITY_MEDIUM;
    }

    /**
     * Classify an exception and return a ready-to-use RouterResult.
     *
     * @param  Throwable  $exception  The caught exception
     * @param  string  $operation  Description of the operation that failed
     * @param  int|null  $routerId  MikrotikRouter ID for log context
     * @param  string  $routerHost  Router host for log context
     */
    public function handle(
        Throwable $exception,
        string $operation,
        ?int $routerId = null,
        string $routerHost = '',
    ): RouterResult {
        $errorType = $this->classify($exception);
        $humanMsg = $this->humanize($errorType, $exception->getMessage());

        $this->logError($exception, $operation, $errorType, $routerId, $routerHost);

        return RouterResult::fail($humanMsg, $errorType, meta: array_filter([
            'router_id' => $routerId,
            'router_host' => $routerHost,
            'operation' => $operation,
            'severity' => $this->getSeverity($errorType),
        ]));
    }

    /**
     * Log the error with full context to the dedicated 'mikrotik' channel.
     * Passwords and sensitive data are never logged.
     */
    public function logError(
        Throwable $exception,
        string $operation,
        string $errorType,
        ?int $routerId = null,
        string $routerHost = '',
    ): void {
        $severity = $this->getSeverity($errorType);
        $context = [
            'operation' => $operation,
            'error_type' => $errorType,
            'severity' => $severity,
            'router_id' => $routerId,
            'router_host' => $routerHost,
            'exception_class' => get_class($exception),
        ];

        match (true) {
            $severity === self::SEVERITY_CRITICAL => Log::channel('mikrotik')->critical('RouterOS critical: '.$exception->getMessage(), $context),
            $severity === self::SEVERITY_HIGH => Log::channel('mikrotik')->error('RouterOS error: '.$exception->getMessage(), $context),
            $severity === self::SEVERITY_MEDIUM => Log::channel('mikrotik')->warning('RouterOS warning: '.$exception->getMessage(), $context),
            default => Log::channel('mikrotik')->info('RouterOS notice: '.$exception->getMessage(), $context),
        };
    }

    /**
     * Log a successful connection event.
     */
    public function logConnectionSuccess(
        string $operation,
        ?int $routerId = null,
        string $routerHost = '',
        float $latencyMs = 0,
    ): void {
        Log::channel('mikrotik')->info('RouterOS connection OK', [
            'operation' => $operation,
            'router_id' => $routerId,
            'router_host' => $routerHost,
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * Log a connection attempt.
     */
    public function logConnectionAttempt(
        string $operation,
        ?int $routerId = null,
        string $routerHost = '',
        int $attempt = 1,
    ): void {
        Log::channel('mikrotik')->debug('RouterOS connection attempt', [
            'operation' => $operation,
            'router_id' => $routerId,
            'router_host' => $routerHost,
            'attempt' => $attempt,
        ]);
    }

    /**
     * Log a command execution.
     */
    public function logCommand(
        string $method,
        string $path,
        ?int $routerId = null,
        string $routerHost = '',
        float $latencyMs = 0,
        bool $success = true,
    ): void {
        $level = $success ? 'debug' : 'warning';
        Log::channel('mikrotik')->$level('RouterOS command', [
            'method' => $method,
            'path' => $path,
            'router_id' => $routerId,
            'router_host' => $routerHost,
            'latency_ms' => $latencyMs,
            'success' => $success,
        ]);
    }
}
