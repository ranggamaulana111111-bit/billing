<?php

namespace App\Modules\GenieACS\Exceptions;

use RuntimeException;

/**
 * Thrown when the GenieACS NBI server is unreachable or the connection times out.
 */
class GenieACSConnectionException extends RuntimeException
{
    /**
     * Create an exception for a general connection failure.
     */
    public static function failed(string $message, ?float $responseTime = null): static
    {
        $detail = $responseTime !== null ? " ({$responseTime}ms)" : '';

        return new static("Connection failed: {$message}{$detail}");
    }

    /**
     * Create an exception for a connection timeout.
     */
    public static function timeout(int $timeout): static
    {
        return new static("Connection timed out after {$timeout}s");
    }

    /**
     * Create an exception when the server host is unreachable.
     */
    public static function unreachable(string $baseUrl): static
    {
        return new static("GenieACS server unreachable: {$baseUrl}");
    }
}
