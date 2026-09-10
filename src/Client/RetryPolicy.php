<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client;

use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;

/**
 * Retry budget for idempotent requests: how many attempts, and how long to wait between them.
 *
 * Delays grow exponentially from baseDelaySeconds, are capped at maxDelaySeconds, and use
 * equal jitter so a delay for a given attempt lands anywhere in [d/2, d].
 */
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public float $baseDelaySeconds = 1.0,
        public float $maxDelaySeconds = 8.0,
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidFieldConfigException('Retry maxAttempts must be at least 1');
        }

        if ($this->baseDelaySeconds <= 0) {
            throw new InvalidFieldConfigException('Retry baseDelaySeconds must be greater than 0');
        }

        if ($this->maxDelaySeconds < $this->baseDelaySeconds) {
            throw new InvalidFieldConfigException('Retry maxDelaySeconds must not be lower than baseDelaySeconds');
        }
    }

    /**
     * A single attempt and no waiting: the caller owns every retry decision.
     */
    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    public function isRetryableStatus(int $statusCode): bool
    {
        return $statusCode === 429 || $statusCode >= 500;
    }

    /**
     * Seconds to wait after the given (1-based) failed attempt before the next one.
     */
    public function delayBeforeRetry(int $attempt): float
    {
        $ceiling = min($this->baseDelaySeconds * (2 ** max(0, $attempt - 1)), $this->maxDelaySeconds);
        $unit = random_int(0, 1_000_000) / 1_000_000;

        return $ceiling / 2 + ($ceiling / 2) * $unit;
    }
}
