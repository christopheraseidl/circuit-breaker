<?php

namespace christopheraseidl\CircuitBreaker\Tests\CircuitBreaker;

use christopheraseidl\CircuitBreaker\CircuitBreaker;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestCacheAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestLoggerAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestNotifierAdapter;

beforeEach(function () {
    $this->cache = new TestCacheAdapter;
    $this->logger = new TestLoggerAdapter;
    $this->notifier = new TestNotifierAdapter;
});

it('handles cache failures gracefully', function () {
    $cache = $this->mock(CacheContract::class);
    $cache->shouldReceive('get')->andThrow(new \Exception('Cache failure'));

    $breaker = new CircuitBreaker(
        'test',
        $cache,
        $this->logger,
        $this->notifier
    );

    // Should return false when cache fails on canAttempt
    expect($breaker->canAttempt())->toBeFalse();

    // Should not throw on recordSuccess
    $breaker->recordSuccess();

    // Should not throw on recordFailure
    $breaker->recordFailure();

    // Should not throw on reset
    $breaker->reset();
});

it('handles missing cache keys', function () {
    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $this->logger,
        $this->notifier
    );

    // Should default to closed state
    expect($breaker->getState())->toBe('closed');
    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->canAttempt())->toBeTrue();

    // Should return 0 for missing failure count
    expect($breaker->getFailureCount())->toBe(0);

    // Stats should handle missing values
    $stats = $breaker->getStats();
    expect($stats['opened_at'])->toBeNull();
});

it('handles corrupted cache data', function () {
    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $this->logger,
        $this->notifier
    );

    // Put invalid state
    $this->cache->put('circuit_breaker:test:state', 'invalid');
    expect($breaker->getState())->toBe('invalid');

    // Should handle invalid state gracefully in canAttempt
    expect($breaker->canAttempt())->toBeFalse();
});

it('handles concurrent state changes', function () {
    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $this->logger,
        $this->notifier,
        ['failure_threshold' => 2]
    );

    // Record one failure
    $breaker->recordFailure();

    // Simulate another process opening the circuit
    $this->cache->put('circuit_breaker:test:state', 'open');
    $this->cache->put('circuit_breaker:test:opened_at', time());

    // Should respect the state change
    expect($breaker->isOpen())->toBeTrue();
    expect($breaker->canAttempt())->toBeFalse();

    // Should handle state changes during operations
    $breaker->recordFailure(); // Should not crash
});

it('sends notifications on state changes', function () {
    $notifier = $this->mock(NotifierContract::class);
    $notifier->shouldReceive('notify')->twice();

    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $this->logger,
        $notifier,
        ['failure_threshold' => 2]
    );

    // Trigger circuit opening
    $breaker->recordFailure();
    $breaker->recordFailure();

    // Reset and trigger again to test multiple notifications
    $breaker->reset();
    $breaker->recordFailure();
    $breaker->recordFailure();
});

it('logs state transitions', function () {
    $logger = $this->mock(LoggerContract::class);
    $logger->shouldReceive('warning')->once()->with(
        \Mockery::pattern('/transitioned to OPEN state/'),
        \Mockery::any()
    );
    $logger->shouldReceive('info')->once()->with(
        \Mockery::pattern('/Circuit breaker notification sent to admin/'),
        \Mockery::any()
    );
    $logger->shouldReceive('info')->once()->with(
        \Mockery::pattern('/transitioned to CLOSED state/'),
        \Mockery::any()
    );

    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $logger,
        $this->notifier,
        ['failure_threshold' => 2]
    );

    // Trigger open transition
    $breaker->recordFailure();
    $breaker->recordFailure();

    // Trigger close transition
    $breaker->reset();
});

it('provides accurate statistics', function () {
    $breaker = new CircuitBreaker(
        'test',
        $this->cache,
        $this->logger,
        $this->notifier,
        [
            'failure_threshold' => 3,
            'window_seconds' => 60,
            'recovery_timeout_seconds' => 300,
        ]
    );

    // Check initial stats
    $stats = $breaker->getStats();
    expect($stats['name'])->toBe('test');
    expect($stats['state'])->toBe('closed');
    expect($stats['failure_count'])->toBe(0);
    expect($stats['failure_threshold'])->toBe(3);
    expect($stats['window_seconds'])->toBe(60);
    expect($stats['recovery_timeout_seconds'])->toBe(300);

    // Check stats after failures
    $breaker->recordFailure();
    $stats = $breaker->getStats();
    expect($stats['failure_count'])->toBe(1);

    // Check stats after opening
    $breaker->recordFailure();
    $breaker->recordFailure();
    $stats = $breaker->getStats();
    expect($stats['state'])->toBe('open');
    expect($stats['opened_at'])->toBeInt();

    // Check stats after reset
    $breaker->reset();
    $stats = $breaker->getStats();
    expect($stats['state'])->toBe('closed');
    expect($stats['failure_count'])->toBe(0);
    expect($stats['opened_at'])->toBeNull();
});
