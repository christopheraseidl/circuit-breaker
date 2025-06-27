<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use Illuminate\Support\Facades\Cache;

/**
 * Adapts Laravel Cache facade to circuit breaker cache contract.
 */
class LaravelCacheAdapter implements CacheContract
{
    /**
     * Store value in cache with TTL in hours.
     */
    public function put(string $key, mixed $value, ?int $ttl = 1): bool
    {
        return Cache::put($key, $value, $ttl * 60 * 60);
    }

    /**
     * Retrieve value from cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * Remove key from cache.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Increment numeric cache value.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return Cache::increment($key, $value);
    }

    /**
     * Check if key exists in cache.
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }
}
