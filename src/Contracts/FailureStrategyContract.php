<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

/**
 * Defines failure tracking strategy for circuit breaker state management.
 */
interface FailureStrategyContract
{
    /**
     * Record successful operation and clear failure data as needed.
     */
    public function recordSuccess(CacheContract $cache, string $key): void;

    /**
     * Record failure and return current failure count.
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
     * Get current failure count within time window.
     */
    public function getCurrentFailureCount(CacheContract $cache, string $key): int;

    /**
     * Get strategy statistics array.
     */
    public function getStats(): array;
}
