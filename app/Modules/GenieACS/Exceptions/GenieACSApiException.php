<?php

namespace App\Modules\GenieACS\Exceptions;

use RuntimeException;

/**
 * Thrown when a GenieACS API request returns an unexpected HTTP status.
 */
class GenieACSApiException extends RuntimeException
{
    /** @var array<string, mixed>|null */
    protected ?array $responseBody;

    /**
     * @param  array<string, mixed>|null  $responseBody  Raw JSON response body
     */
    public function __construct(string $message, ?array $responseBody = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->responseBody = $responseBody;
    }

    /**
     * Create an exception for a failed API request.
     */
    public static function failed(int $status, string $endpoint, ?string $detail = null): static
    {
        $message = "API request failed: HTTP {$status} on {$endpoint}";

        if ($detail !== null) {
            $message .= " — {$detail}";
        }

        return new static($message);
    }

    /**
     * Create an exception for a resource not found.
     */
    public static function notFound(string $resource, string $id): static
    {
        return new static("Resource not found: {$resource} [{$id}]");
    }

    /**
     * Create an exception for an invalid query parameter.
     */
    public static function invalidQuery(string $detail): static
    {
        return new static("Invalid query: {$detail}");
    }

    /**
     * Get the raw response body that triggered this exception.
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
