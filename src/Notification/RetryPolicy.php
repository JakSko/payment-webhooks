<?php

declare(strict_types=1);

namespace App\Notification;

use Closure;

/**
 * Transient failures (5xx, 408, 429, timeout, connection error) are retried with exponential backoff
 * and jitter; permanent ones (other 4xx) and exhausted attempts go to the dead letter storage.
 */
final class RetryPolicy
{
    private const RETRYABLE_STATUSES = [408, 429];
    private const JITTER_RATIO = 0.2;

    /** @var Closure(int): int returns a random integer between 0 and the given max */
    private readonly Closure $random;

    public function __construct(
        private readonly int $maxAttempts = 6,
        private readonly int $baseSeconds = 2,
        private readonly int $capSeconds = 300,
        ?Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    public function shouldRetry(SendResult $result, int $attemptsMade): bool
    {
        if ($attemptsMade >= $this->maxAttempts) {
            return false;
        }

        return $result->statusCode === null
            || $result->statusCode >= 500
            || in_array($result->statusCode, self::RETRYABLE_STATUSES, true);
    }

    /** Delay before the next attempt, after attempt number $attemptsMade (1-based) failed. */
    public function delaySeconds(int $attemptsMade): int
    {
        $exponent = min(max($attemptsMade - 1, 0), 30); // bounded to avoid integer overflow
        $delay = min($this->baseSeconds * (2 ** $exponent), $this->capSeconds);

        return $delay + ($this->random)((int) floor($delay * self::JITTER_RATIO));
    }
}
