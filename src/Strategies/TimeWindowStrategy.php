<?php

namespace christopheraseidl\CircuitBreaker\Strategies;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\FailureStrategyContract;
use christopheraseidl\CircuitBreaker\Support\Config;

/**
 * Tracks failures within sliding time windows for circuit breaker decisions.
 */
class TimeWindowStrategy implements FailureStrategyContract
{
    private int $failureThreshold;

    private int $windowSeconds;

    private int $recoveryTimeout;

    private int $halfOpenMaxAttempts;

    private int $halfOpenDelaySeconds;

    /**
     * Create strategy with time window configuration.
     */
    public function __construct(array $config = [])
    {
        $this->failureThreshold = $config['failure_threshold']
            ?? Config::get('defaults.failure_threshold', 5);

        $this->windowSeconds = $config['window_seconds']
            ?? Config::get('defaults.window_seconds', 60);

        $this->recoveryTimeout = $config['recovery_timeout_seconds']
            ?? Config::get('defaults.recovery_timeout_seconds', 300);

        $this->halfOpenMaxAttempts = $config['half_open_max_attempts']
            ?? Config::get('defaults.half_open_max_attempts', 3);

        $this->halfOpenDelaySeconds = $config['half_open_delay_seconds']
            ?? Config::get('defaults.half_open_delay_seconds', 1);
    }

    /**
     * Record successful operation and clear failure data.
     */
    public function recordSuccess(CacheContract $cache, string $key): void
    {
        // Clear the failure timeline since we've had a success
        $cache->forget($key.':timeline');
    }

    /**
     * Record failure and return current failure count.
     */
    public function recordFailure(CacheContract $cache, string $key): int
    {
        // Store failure with timestamp
        $now = time();
        $failures = $cache->get($key.':timeline', []);

        // If cache data is corrupted (not an array), reset failures
        if (! is_array($failures)) {
            $failures = [];
        }

        $failures[] = $now;

        // Remove old failures outside the window
        $failures = $this->filterOldFailures($failures);

        $cache->put($key.':timeline', $failures, $this->windowSeconds * 2);

        return count($failures);
    }

    /**
     * Determine whether circuit should open from closed state.
     */
    public function shouldOpenFromClosed(CacheContract $cache, string $key): bool
    {
        $windowFailures = $this->getFailuresInWindow($cache, $key);

        return $windowFailures >= $this->failureThreshold;
    }

    /**
     * Determine whether circuit should open from half-open state.
     */
    public function shouldOpenFromHalfOpen(CacheContract $cache, string $key): bool
    {
        $halfOpenAttempts = $cache->get($key);

        return $halfOpenAttempts >= $this->halfOpenMaxAttempts;
    }

    /**
     * Determine whether circuit should half-open from open state.
     */
    public function shouldHalfOpenFromOpen(CacheContract $cache, string $key): bool
    {
        $openedAt = $cache->get($key);

        if (! $this->isValidTimestamp($openedAt)) {
            return false;
        }

        if ($openedAt && (Carbon::now()->timestamp - $openedAt) >= $this->recoveryTimeout) {
            return true;
        }

        return false;
    }

    /**
     * Return the wait time before retrying.
     */
    public function minWaitPassed(int $lastHalfOpenAttempt, int $halfOpenAttempts): bool
    {
        return $this->getWaitTime($lastHalfOpenAttempt, $halfOpenAttempts) === 0;
    }

    /**
     * Get current failure count within time window.
     */
    public function getCurrentFailureCount(CacheContract $cache, string $key): int
    {
        return $this->getFailuresInWindow($cache, $key);
    }

    /**
     * Get strategy statistics array.
     */
    public function getStats(): array
    {
        return [
            'failure_threshold' => $this->failureThreshold,
            'window_seconds' => $this->windowSeconds,
            'recovery_timeout_seconds' => $this->recoveryTimeout,
            'half_open_max_attempts' => $this->halfOpenMaxAttempts,
            'half_open_delay_seconds' => $this->halfOpenDelaySeconds,
        ];
    }

    /**
     * Forget provided cache key.
     */
    public function forget(CacheContract $cache, $key): bool
    {
        return $cache->forget($key.':timeline');
    }

    /**
     * Return the wait time in seconds before retrying.
     */
    private function getWaitTime(int $lastHalfOpenAttempt, int $halfOpenAttempts): int
    {
        $halfOpenAttempts = max($halfOpenAttempts, 1);
        $timeSinceLastAttempt = (time() - $lastHalfOpenAttempt);
        $baseDelay = $this->halfOpenDelaySeconds * (2 ** ($halfOpenAttempts - 1));
        $jitter = $baseDelay * (rand(0, 20) / 100); // 0-20% jitter
        $minDelay = $baseDelay + $jitter;

        if ($timeSinceLastAttempt < $minDelay) {
            $delay = $minDelay - $timeSinceLastAttempt;

            return min($delay, 15);
        }

        return 0;
    }

    /**
     * Get failure count within current time window.
     */
    private function getFailuresInWindow(CacheContract $cache, string $key): int
    {
        $failures = $cache->get($key.':timeline', []);

        // If cache data is corrupted, return 0
        if (! is_array($failures)) {
            return 0;
        }

        $recentFailures = $this->filterOldFailures($failures);

        return count($recentFailures);
    }

    /**
     * Filter failures to only include those within time window.
     */
    private function filterOldFailures(array $failures): array
    {
        $now = time();
        // Keep only failures within the sliding window
        $recentFailures = array_filter(
            $failures,
            fn ($time) => $time > ($now - $this->windowSeconds) && $this->isValidTimestamp($time)
        );

        return $recentFailures;
    }

    /**
     * Determine whether the provided value is a valid timestamp.
     */
    private function isValidTimestamp(mixed $timestamp): bool
    {
        return is_numeric($timestamp) && $timestamp > 0 && $timestamp <= time() + 1;
    }
}
