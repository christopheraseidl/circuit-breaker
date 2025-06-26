<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

interface CacheContract
{
    /**
     * Store an item in the cache.
     */
    public function put(string $key, mixed $value, ?int $ttl = 1): bool;

    /**
     * Retrieve an item from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Increment the value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Check if an item exists in the cache.
     */
    public function has(string $key): bool;
}
