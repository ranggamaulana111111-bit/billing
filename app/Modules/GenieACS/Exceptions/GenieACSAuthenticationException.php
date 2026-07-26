<?php

namespace App\Modules\GenieACS\Exceptions;

use RuntimeException;

/**
 * Thrown when GenieACS rejects credentials (HTTP 401/403).
 */
class GenieACSAuthenticationException extends RuntimeException
{
    /**
     * Create an exception for invalid username/password.
     */
    public static function invalidCredentials(): static
    {
        return new static('Invalid username or password');
    }

    /**
     * Create an exception for a generic auth failure with optional detail.
     */
    public static function unauthorized(?string $detail = null): static
    {
        $message = 'Authentication failed';

        if ($detail !== null) {
            $message .= ": {$detail}";
        }

        return new static($message);
    }
}
