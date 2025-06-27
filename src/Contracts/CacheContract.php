<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

/**
 * Defines cache operations for circuit breaker implementations.
 */
interface CacheContract
{
    /**
     * Store item in cache.
     */
    public function put(string $key, mixed $value, ?int $ttl = 1): bool;

    /**
     * Retrieve item from cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Remove item from cache.
     */
    public function forget(string $key): bool;

    /**
     * Increment cache item value.
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Check if item exists in cache.
     */
    public function has(string $key): bool;
}
