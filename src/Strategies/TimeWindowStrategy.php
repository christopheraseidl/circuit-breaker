<?php

namespace christopheraseidl\CircuitBreaker\Strategies;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\FailureStrategyContract;

class TimeWindowStrategy implements FailureStrategyContract
{
    private int $failureThreshold;

    private int $windowSeconds;

    private int $recoveryTimeout;

    private int $halfOpenMaxAttempts;

    public function __construct(array $config = [])
    {
        $this->failureThreshold = $config['failure_threshold'] ?? 5;
        $this->windowSeconds = $config['window_seconds'] ?? 60;
        $this->recoveryTimeout = $config['recovery_timeout'] ?? 300;
        $this->halfOpenMaxAttempts = $config['half_open_attempts'] ?? 3;
    }

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

    public function shouldOpenFromClosed(CacheContract $cache, string $key): bool
    {
        $windowFailures = $this->getFailuresInWindow($cache, $key);

        return $windowFailures >= $this->failureThreshold;
    }

    public function shouldOpenFromHalfOpen(CacheContract $cache, string $key): bool
    {
        $halfOpenAttempts = $cache->get($key);

        return $halfOpenAttempts >= $this->halfOpenMaxAttempts;
    }

    public function shouldHalfOpenFromOpen(CacheContract $cache, string $key): bool
    {
        $openedAt = $cache->get($key);

        if ($openedAt && (Carbon::now()->timestamp - $openedAt) >= $this->recoveryTimeout) {
            return true;
        }

        return false;
    }

    /**
     * Get a count of the number of failures within the time window.
     */
    public function getCurrentFailureCount(CacheContract $cache, string $key): int
    {
        return $this->getFailuresInWindow($cache, $key);
    }

    /**
     * Get an array of statistics for the strategy.
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

    private function getFailuresInWindow(CacheContract $cache, string $key): int
    {
        $failures = $cache->get($key.':timeline', []);
        $recentFailures = $this->filterOldFailures($failures);

        return count($recentFailures);
    }

    private function filterOldFailures(array $failures): array
    {
        $now = time();
        $recentFailures = array_filter($failures, fn ($time) => $time > ($now - $this->windowSeconds));

        return $recentFailures;
    }
}
