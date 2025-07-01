<?php

namespace christopheraseidl\CircuitBreaker\Tests\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelCacheAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->cache = new LaravelCacheAdapter;
});

it('stores values with put method', function () {
    $this->cache->put('some_key', 'some_value');

    expect(Cache::get('some_key'))->toBe('some_value');
});

it('retrieves values with get method', function () {
    Cache::put('some_key', 'some_value');

    expect($this->cache->get('some_key'))->toBe('some_value');
});

it('returns default value when key missing', function () {
    expect($this->cache->get('nonexistent'))->toBeNull();
    expect($this->cache->get('nonexistent', 'default'))->toBe('default');
});

it('increments numeric values', function () {
    Cache::put('numeric_value', 0);

    $this->cache->increment('numeric_value');

    expect(Cache::get('numeric_value'))->toBe(1);

    $this->cache->increment('numeric_value', 2);

    expect(Cache::get('numeric_value'))->toBe(3);
});

it('forgets stored values', function () {
    Cache::put('forget_me', 'some_value');

    $this->cache->forget('forget_me');

    expect(Cache::get('forget_me'))->toBeNull();
});

it('handles TTL correctly', function () {
    $this->cache->put('default_ttl', 'some_value');

    expect(Cache::get('default_ttl'))->toBe('some_value');

    Carbon::setTestNow(now()->addMinutes(61));

    expect(Cache::get('default_ttl'))->toBeNull();

    $this->cache->put('custom_ttl', 'some_value', 2);

    expect(Cache::get('custom_ttl'))->toBe('some_value');

    Carbon::setTestNow(now()->addMinutes(121));

    expect(Cache::get('custom_ttl'))->toBeNull();
});
