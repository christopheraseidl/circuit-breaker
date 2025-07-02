<?php

namespace christopheraseidl\CircuitBreaker\Tests\CircuitBreaker;

use christopheraseidl\CircuitBreaker\CircuitBreaker;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestCacheAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestLoggerAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestNotifierAdapter;

beforeEach(function () {
    $this->cache = new TestCacheAdapter;
    $this->logger = new TestLoggerAdapter;
    $this->notifier = new TestNotifierAdapter;
});

it('records failures in closed state', function () {
    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 3,
        'window_seconds' => 60,
    ]);

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(0);

    $breaker->recordFailure();

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(1);

    $breaker->recordFailure();

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(2);
});

it('counts failures correctly', function () {
    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 5,
        'window_seconds' => 60,
    ]);

    // Record multiple failures
    for ($i = 0; $i < 4; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->getFailureCount())->toBe(4);
    expect($breaker->isClosed())->toBeTrue();
});

it('respects failure threshold', function () {
    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 3,
        'window_seconds' => 60,
    ]);

    // Record failures up to but not exceeding threshold
    $breaker->recordFailure();
    $breaker->recordFailure();

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(2);

    // This should trigger the transition to open
    $breaker->recordFailure();

    expect($breaker->isOpen())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(3);
});

it('resets failure count on success', function () {
    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 5,
        'window_seconds' => 60,
    ]);

    // Record some failures
    $breaker->recordFailure();
    $breaker->recordFailure();

    expect($breaker->getFailureCount())->toBe(2);

    // Record success should clear failures
    $breaker->recordSuccess();

    expect($breaker->getFailureCount())->toBe(0);
    expect($breaker->isClosed())->toBeTrue();
});

it('handles concurrent failure recording', function () {
    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 10,
        'window_seconds' => 60,
    ]);

    // Simulate concurrent failure recording
    $failures = [];
    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
        $failures[] = $breaker->getFailureCount();
    }

    // Each failure should increment the count
    expect($failures)->toBe([1, 2, 3, 4, 5]);
    expect($breaker->getFailureCount())->toBe(5);
    expect($breaker->isClosed())->toBeTrue();
});
