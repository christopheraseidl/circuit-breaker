<?php

namespace christopheraseidl\CircuitBreaker\Tests\Support;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;

/**
 * Test implementation of CacheContract for unit testing with TTL support.
 */
class TestCacheAdapter implements CacheContract
{
    public array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            return $default;
        }

        $item = $this->cache[$key];

        // Check if item has TTL and is expired
        if (isset($item['expires_at']) && Carbon::now()->timestamp > $item['expires_at']) {
            unset($this->cache[$key]);

            return $default;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $item = ['value' => $value];

        if ($ttl !== null) {
            // TTL is in hours, convert to timestamp
            $item['expires_at'] = Carbon::now()->addHours($ttl)->timestamp;
        }

        $this->cache[$key] = $item;

        return true;
    }

    public function forget(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

    public function increment(string $key, int $value = 1): int
    {
        $current = $this->get($key, 0);
        $newValue = $current + $value;

        // Preserve existing TTL if item exists
        $ttl = null;
        if (array_key_exists($key, $this->cache) && isset($this->cache[$key]['expires_at'])) {
            $hoursLeft = Carbon::createFromTimestamp($this->cache[$key]['expires_at'])->diffInHours(Carbon::now(), false);
            $ttl = max(0, ceil($hoursLeft));
        }

        $this->put($key, $newValue, $ttl);

        return $newValue;
    }

    /**
     * Clear all cached data.
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Check if key exists in cache and is not expired.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Check if key exists in raw cache (ignoring expiration).
     */
    public function hasRaw(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Get raw cache item with TTL info.
     */
    public function getRaw(string $key): ?array
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * Check if a key is expired.
     */
    public function isExpired(string $key): bool
    {
        if (! array_key_exists($key, $this->cache)) {
            return false;
        }

        $item = $this->cache[$key];

        return isset($item['expires_at']) && Carbon::now()->timestamp > $item['expires_at'];
    }
}
