<?php

namespace App\Services\Mikrotik;

/**
 * Immutable result object returned by every RouterOS API operation.
 *
 * Provides a consistent response format for all MikroTik modules.
 * Callers should always check $result->isSuccess() before using $result->getData().
 *
 * Response structure:
 *  - success:    bool     Whether the operation succeeded
 *  - message:    string   Human-readable message
 *  - data:       mixed    Arbitrary payload (null on failure)
 *  - errorType:  string   Machine-readable error code (empty on success)
 *  - statusCode: int      HTTP status code (0 if not applicable)
 *  - operation:  string   The operation that was performed
 *  - traceId:    string   Unique trace ID for debugging
 *  - timestamp:  string   ISO-8601 timestamp of the result
 *  - meta:       array    Extra metadata (latency_ms, attempts, router_id, etc.)
 */
final class RouterResult
{
    /**
     * @param  bool  $success  Whether the operation succeeded
     * @param  string  $message  Human-readable message (always set)
     * @param  mixed  $data  Arbitrary payload (null on failure)
     * @param  string  $errorType  Machine-readable error code (empty on success)
     * @param  int  $statusCode  HTTP status code (0 if not applicable)
     * @param  string  $operation  The operation name (e.g. "GET /ppp/active")
     * @param  string  $traceId  Unique trace ID for debugging
     * @param  string  $timestamp  ISO-8601 timestamp
     * @param  array  $meta  Extra metadata (latency_ms, attempts, router_id, etc.)
     */
    private function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly mixed $data = null,
        private readonly string $errorType = '',
        private readonly int $statusCode = 0,
        private readonly string $operation = '',
        private readonly string $traceId = '',
        private readonly string $timestamp = '',
        private readonly array $meta = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  string  $message  Human-readable success message
     * @param  mixed  $data  Response payload from RouterOS
     * @param  array  $meta  Optional metadata (latency_ms, router_id, etc.)
     */
    public static function ok(string $message = '', mixed $data = null, array $meta = []): self
    {
        return new self(
            true,
            $message,
            $data,
            '',
            $meta['status_code'] ?? 0,
            $meta['operation'] ?? '',
            $meta['trace_id'] ?? self::generateTraceId(),
            $meta['timestamp'] ?? now()->toIso8601String(),
            $meta,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  string  $message  Human-readable error message
     * @param  string  $errorType  Machine-readable error code (dns_error, timeout, etc.)
     * @param  mixed  $data  Optional partial data (may be null)
     * @param  array  $meta  Optional metadata
     */
    public static function fail(string $message, string $errorType = 'unknown', mixed $data = null, array $meta = []): self
    {
        return new self(
            false,
            $message,
            $data,
            $errorType,
            $meta['status_code'] ?? 0,
            $meta['operation'] ?? '',
            $meta['trace_id'] ?? self::generateTraceId(),
            $meta['timestamp'] ?? now()->toIso8601String(),
            $meta,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the response payload.
     * Returns null when the operation failed, or data on success.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get data as an array, useful when callers always expect array|null.
     */
    public function toArray(): ?array
    {
        return is_array($this->data) ? $this->data : null;
    }

    /**
     * Get the first item from a list result, or null.
     * Convenience for operations that return a single object.
     */
    public function first(): ?array
    {
        $items = $this->toArray();

        return $items[0] ?? null;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Convert to legacy array format for backward compatibility.
     *
     * @return array{success: bool, message: string, data?: mixed, error_type?: string}
     */
    public function toLegacyArray(): array
    {
        $result = [
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if ($this->errorType !== '') {
            $result['error_type'] = $this->errorType;
        }

        return $result;
    }

    /**
     * Convert to a full structured array (for API responses / JSON).
     */
    public function toArrayFull(): array
    {
        $result = [
            'success' => $this->success,
            'message' => $this->message,
            'trace_id' => $this->traceId,
            'timestamp' => $this->timestamp,
            'operation' => $this->operation,
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if ($this->errorType !== '') {
            $result['error_type'] = $this->errorType;
        }

        if ($this->statusCode > 0) {
            $result['status_code'] = $this->statusCode;
        }

        if ($this->meta !== []) {
            $result['meta'] = $this->meta;
        }

        return $result;
    }

    /**
     * Generate a unique trace ID for debugging.
     */
    private static function generateTraceId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
