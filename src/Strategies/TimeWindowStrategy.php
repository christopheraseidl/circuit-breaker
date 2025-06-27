<?php

namespace christopheraseidl\CircuitBreaker\Strategies;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\FailureStrategyContract;

/**
 * Tracks failures within sliding time windows for circuit breaker decisions.
 */
class TimeWindowStrategy implements FailureStrategyContract
{
    private int $failureThreshold;

    private int $windowSeconds;

    private int $recoveryTimeout;

    private int $halfOpenMaxAttempts;

    /**
     * Create strategy with time window configuration.
     */
    public function __construct(array $config = [])
    {
        $this->failureThreshold = $config['failure_threshold'] ?? 5;
        $this->windowSeconds = $config['window_seconds'] ?? 60;
        $this->recoveryTimeout = $config['recovery_timeout'] ?? 300;
        $this->halfOpenMaxAttempts = $config['half_open_attempts'] ?? 3;
    }

    /**
     * Record failure and return current failure count.
     */
    public function recordFailure(CacheContract $cache, string $key): int
    {
        // Store failure with timestamp
        $now = time();
        $failures = $cache->get($key.':timeline', []);
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

        if ($openedAt && (Carbon::now()->timestamp - $openedAt) >= $this->recoveryTimeout) {
            return true;
        }

        return false;
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
            'recovery_timeout' => $this->recoveryTimeout,
            'half_open_max_attempts' => $this->halfOpenMaxAttempts,
        ];
    }

    /**
     * Get failure count within current time window.
     */
    private function getFailuresInWindow(CacheContract $cache, string $key): int
    {
        $failures = $cache->get($key.':timeline', []);
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
        $recentFailures = array_filter($failures, fn ($time) => $time > ($now - $this->windowSeconds));

        return $recentFailures;
    }
}
