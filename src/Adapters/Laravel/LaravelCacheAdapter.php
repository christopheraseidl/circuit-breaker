<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use Illuminate\Support\Facades\Cache;

class LaravelCacheAdapter implements CacheContract
{
    public function put(string $key, mixed $value, ?int $ttl = 1): bool
    {
        return Cache::put($key, $value, $ttl * 60 * 60);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return Cache::increment($key, $value);
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }
}
