<?php

declare(strict_types=1);

namespace SharpAPI\Core\RateLimit;

/**
 * In-memory sliding window rate limiter for HTTP requests.
 *
 * Tracks request timestamps in a rolling window and blocks (via usleep)
 * when the window is full. State is held in-memory per process — in
 * long-lived processes the limiter naturally persists across calls.
 */
class SlidingWindowRateLimiter
{
    /** @var float[] Timestamps of requests within the current window */
    private array $timestamps = [];

    private int $maxRequests;

    private int $windowSeconds;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = max(1, $maxRequests);
        $this->windowSeconds = max(1, $windowSeconds);
    }

    /**
     * Block until the sliding window has capacity, then record the request.
     *
     * @return float Seconds waited (0.0 if no wait was needed)
     */
    public function waitIfNeeded(): float
    {
        $waited = 0.0;

        while (true) {
            $this->pruneExpired();

            if (count($this->timestamps) < $this->maxRequests) {
                $this->timestamps[] = microtime(true);

                return $waited;
            }

            // Calculate how long until the oldest request expires from the window
            $oldest = $this->timestamps[0];
            $expiresAt = $oldest + $this->windowSeconds;
            $sleepSeconds = $expiresAt - microtime(true) + 0.05; // 50ms buffer

            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
                $waited += $sleepSeconds;
            }
        }
    }

    /**
     * Check if a request can proceed without waiting.
     * @api
     */
    public function canProceed(): bool
    {
        $this->pruneExpired();

        return count($this->timestamps) < $this->maxRequests;
    }

    /**
     * Number of requests remaining in the current window.
     */
    public function remaining(): int
    {
        $this->pruneExpired();

        return max(0, $this->maxRequests - count($this->timestamps));
    }

    /**
     * Adopt a higher limit from the server's X-RateLimit-Limit header.
     *
     * One-way ratchet: only increases, never decreases below the configured default.
     * This handles plan upgrades where the server advertises a higher limit.
     */
    public function adaptFromServerLimit(int $serverLimit): void
    {
        if ($serverLimit > $this->maxRequests) {
            $this->maxRequests = $serverLimit;
        }
    }

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    public function setMaxRequests(int $maxRequests): void
    {
        $this->maxRequests = max(1, $maxRequests);
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /**
     * Remove timestamps that have fallen outside the sliding window.
     */
    private function pruneExpired(): void
    {
        $cutoff = microtime(true) - $this->windowSeconds;
        $this->timestamps = array_values(
            array_filter($this->timestamps, fn (float $ts) => $ts > $cutoff)
        );
    }
}
