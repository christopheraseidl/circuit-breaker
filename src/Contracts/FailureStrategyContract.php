<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

interface FailureStrategyContract
{
    /**
     * Record a failure and return the current failure count.
     */
    public function recordFailure(CacheContract $cache, string $key): int;

    /**
     * Determine whether circuit should open from closed state.
     */
    public function shouldOpenFromClosed(CacheContract $cache, string $key): bool;

    /**
     * Determine whether circuit should open from half-open state.
     */
    public function shouldOpenFromHalfOpen(CacheContract $cache, string $key): bool;

    /**
     * Determine whether circuit should half-open from open state.
     */
    public function shouldHalfOpenFromOpen(CacheContract $cache, string $key): bool;

    /**
     * Get a count of the number of failures within the time window.
     */
    public function getCurrentFailureCount(CacheContract $cache, string $key): int;

    /**
     * Get an array of statistics for the strategy.
     */
    public function getStats(): array;
}
