<?php

namespace App\Services\Mikrotik;

/**
 * Configurable retry policy with exponential backoff and jitter.
 *
 * Controls how many times a failed operation is retried, how long to wait
 * between attempts, and which error types are eligible for retry.
 *
 * Usage:
 *  $policy = RouterRetryPolicy::default();
 *  $policy = RouterRetryPolicy::aggressive();  // 5 attempts, longer delays
 *  $policy = RouterRetryPolicy::none();         // no retries
 *
 *  // Custom:
 *  $policy = new RouterRetryPolicy(
 *      maxAttempts: 4,
 *      baseDelayMs: 1000,
 *      maxDelayMs: 10000,
 *      backoffMultiplier: 2.0,
 *      jitter: true,
 *  );
 */
class RouterRetryPolicy
{
    /**
     * Default error types eligible for retry.
     */
    private const RETRYABLE_ERRORS = [
        RouterErrorHandler::TIMEOUT,
        RouterErrorHandler::CONNECTION_RESET,
        RouterErrorHandler::CONNECTION_CLOSED,
    ];

    /**
     * @param  int  $maxAttempts  Total attempts (1 = no retry, 2 = 1 retry, etc.)
     * @param  int  $baseDelayMs  Initial delay between retries in ms
     * @param  int  $maxDelayMs  Cap on delay in ms
     * @param  float  $backoffMultiplier  Multiplier for exponential backoff
     * @param  bool  $jitter  Add random jitter to prevent thundering herd
     * @param  array  $retryableErrors  Error types that trigger a retry
     */
    public function __construct(
        private readonly int $maxAttempts = 2,
        private readonly int $baseDelayMs = 500,
        private readonly int $maxDelayMs = 10000,
        private readonly float $backoffMultiplier = 2.0,
        private readonly bool $jitter = true,
        private readonly array $retryableErrors = self::RETRYABLE_ERRORS,
    ) {}

    /**
     * Default policy: 2 attempts, 500ms base delay, exponential backoff.
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Aggressive policy: 4 attempts, 1s base, up to 10s delay.
     * Useful for critical operations that must succeed.
     */
    public static function aggressive(): self
    {
        return new self(
            maxAttempts: 4,
            baseDelayMs: 1000,
            maxDelayMs: 10000,
            backoffMultiplier: 2.0,
        );
    }

    /**
     * Minimal policy: 2 attempts, 300ms base delay.
     * Fast failure for dashboard/monitoring.
     */
    public static function minimal(): self
    {
        return new self(
            maxAttempts: 2,
            baseDelayMs: 300,
            maxDelayMs: 3000,
            backoffMultiplier: 2.0,
        );
    }

    /**
     * No retry at all: 1 attempt only.
     */
    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * Check if a given error type is eligible for retry under this policy.
     */
    public function shouldRetry(string $errorType, int $currentAttempt): bool
    {
        if ($currentAttempt >= $this->maxAttempts) {
            return false;
        }

        return in_array($errorType, $this->retryableErrors);
    }

    /**
     * Calculate the delay in milliseconds before the next retry attempt.
     *
     * Uses exponential backoff: baseDelay * (backoffMultiplier ^ (attempt - 1))
     * Capped at maxDelayMs.  Jitter adds +/- 25% randomness.
     */
    public function getDelayMs(int $attempt): int
    {
        $delay = $this->baseDelayMs * pow($this->backoffMultiplier, $attempt - 1);
        $delay = min((int) $delay, $this->maxDelayMs);

        if ($this->jitter) {
            $jitterRange = $delay * 0.25;
            $delay += (int) (mt_rand((int) (-$jitterRange), (int) $jitterRange));
            $delay = max(0, $delay);
        }

        return $delay;
    }

    /**
     * Sleep for the calculated delay before the next attempt.
     */
    public function waitBeforeRetry(int $attempt): void
    {
        $delayMs = $this->getDelayMs($attempt);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getBaseDelayMs(): int
    {
        return $this->baseDelayMs;
    }

    public function getMaxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    public function isJitterEnabled(): bool
    {
        return $this->jitter;
    }

    public function getRetryableErrors(): array
    {
        return $this->retryableErrors;
    }
}
