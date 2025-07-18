<?php

namespace christopheraseidl\CircuitBreaker\Tests\Strategies;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Strategies\TimeWindowStrategy;
use christopheraseidl\CircuitBreaker\Support\Config;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestCacheAdapter;

beforeEach(function () {
    $this->cache = new TestCacheAdapter;
});

it('tracks failures within time window', function () {
    $strategy = new TimeWindowStrategy(['window_seconds' => 60]);

    // Record multiple failures
    $count1 = $strategy->recordFailure($this->cache, 'test-key');
    expect($count1)->toBe(1);

    $count2 = $strategy->recordFailure($this->cache, 'test-key');
    expect($count2)->toBe(2);

    $count3 = $strategy->recordFailure($this->cache, 'test-key');
    expect($count3)->toBe(3);

    // Verify current failure count
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(3);
});

it('expires old failures outside window', function () {
    $strategy = new TimeWindowStrategy(['window_seconds' => 2]); // 2 second window for testing

    // Record a failure
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(1);

    // Wait for window to expire
    Carbon::setTestNow(now()->addSeconds(3));

    // Old failure should be expired
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(0);

    // Record new failure and verify it's counted
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(1);
});

it('determines when to open from closed', function () {
    $strategy = new TimeWindowStrategy(['failure_threshold' => 3]);

    // Below threshold
    $strategy->recordFailure($this->cache, 'test-key');
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->shouldOpenFromClosed($this->cache, 'test-key'))->toBeFalse();

    // At threshold
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->shouldOpenFromClosed($this->cache, 'test-key'))->toBeTrue();

    // Above threshold
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->shouldOpenFromClosed($this->cache, 'test-key'))->toBeTrue();
});

it('determines when to transition to half-open', function () {
    $strategy = new TimeWindowStrategy(['recovery_timeout_seconds' => 2]); // 2 seconds for testing

    // Set opened_at timestamp
    $openedAt = Carbon::now()->timestamp;
    $this->cache->put('test-key', $openedAt);

    // Should not transition immediately
    expect($strategy->shouldHalfOpenFromOpen($this->cache, 'test-key'))->toBeFalse();

    // Wait for recovery timeout
    Carbon::setTestNow(now()->addSeconds(3));

    // Should transition after timeout
    expect($strategy->shouldHalfOpenFromOpen($this->cache, 'test-key'))->toBeTrue();
});

it('determines when to open from half-open', function () {
    $strategy = new TimeWindowStrategy(['half_open_max_attempts' => 3]);

    // Below max attempts
    $this->cache->put('test-key', 1);
    expect($strategy->shouldOpenFromHalfOpen($this->cache, 'test-key'))->toBeFalse();

    $this->cache->put('test-key', 2);
    expect($strategy->shouldOpenFromHalfOpen($this->cache, 'test-key'))->toBeFalse();

    // At max attempts
    $this->cache->put('test-key', 3);
    expect($strategy->shouldOpenFromHalfOpen($this->cache, 'test-key'))->toBeTrue();

    // Above max attempts
    $this->cache->put('test-key', 4);
    expect($strategy->shouldOpenFromHalfOpen($this->cache, 'test-key'))->toBeTrue();
});

it('provides accurate statistics', function () {
    $config = [
        'failure_threshold' => 10,
        'window_seconds' => 120,
        'recovery_timeout_seconds' => 600,
        'half_open_max_attempts' => 5,
    ];

    $strategy = new TimeWindowStrategy($config);
    $stats = $strategy->getStats();

    expect($stats)->toMatchArray([
        'failure_threshold' => 10,
        'window_seconds' => 120,
        'recovery_timeout_seconds' => 600,
        'half_open_max_attempts' => 5,
    ]);
});

it('accepts default configuration', function () {
    // Reset config state and force standalone mode to bypass Larvel config() call
    Config::reset();
    Config::forceStandaloneMode();

    // Set default configuration values
    Config::set('defaults.failure_threshold', 1);
    Config::set('defaults.window_seconds', 5);
    Config::set('defaults.recovery_timeout_seconds', 1);
    Config::set('defaults.half_open_max_attempts', 3);
    Config::set('defaults.half_open_delay_seconds', 2);

    $strategy = new TimeWindowStrategy;

    $stats = $strategy->getStats();

    // Verify that the strategy uses the default configuration values
    expect($stats)->toMatchArray([
        'failure_threshold' => 1,
        'window_seconds' => 5,
        'recovery_timeout_seconds' => 1,
        'half_open_max_attempts' => 3,
        'half_open_delay_seconds' => 2,
    ]);

    // Test that the strategy actually uses these values
    // Should open after just 1 failure (threshold = 1)
    $strategy->recordFailure($this->cache, 'test-key');
    expect($strategy->shouldOpenFromClosed($this->cache, 'test-key'))->toBeTrue();

    // Test recovery timeout (1 second)
    $openedAt = Carbon::now()->timestamp;
    $this->cache->put('test-key', $openedAt);
    expect($strategy->shouldHalfOpenFromOpen($this->cache, 'test-key'))->toBeFalse();

    Carbon::setTestNow(now()->addSeconds(1));

    expect($strategy->shouldHalfOpenFromOpen($this->cache, 'test-key'))->toBeTrue();

    // Test that it opens from half-open after 3 attempts
    $this->cache->put('test-key', 3);
    expect($strategy->shouldOpenFromHalfOpen($this->cache, 'test-key'))->toBeTrue();
});

it('handles corrupted cache data', function () {
    $strategy = new TimeWindowStrategy;

    // Put corrupted timeline data
    $this->cache->put('test-key:timeline', 'not-an-array');

    // Should not crash when getting failure count
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(0);

    // Should handle corrupted timeline gracefully when recording failures
    $count = $strategy->recordFailure($this->cache, 'test-key');
    expect($count)->toBe(1);

    // Should handle non-numeric timestamps in timeline
    $this->cache->put('test-key:timeline', ['not-a-timestamp', 'also-invalid']);
    expect($strategy->getCurrentFailureCount($this->cache, 'test-key'))->toBe(0);
});
