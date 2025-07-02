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

it('starts in closed state', function () {
    $circuitBreaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier);

    expect($circuitBreaker->getState())->toBe('closed');
    expect($circuitBreaker->isClosed())->toBeTrue();
    expect($circuitBreaker->isOpen())->toBeFalse();
    expect($circuitBreaker->isHalfOpen())->toBeFalse();
    expect($circuitBreaker->canAttempt())->toBeTrue();
});

it('transitions from closed to open when failure threshold exceeded', function () {
    $circuitBreaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 3,
    ]);

    // Initially closed
    expect($circuitBreaker->isClosed())->toBeTrue();

    // Record failures up to threshold
    $circuitBreaker->recordFailure();
    $circuitBreaker->recordFailure();
    expect($circuitBreaker->isClosed())->toBeTrue();

    // Exceed threshold - should transition to open
    $circuitBreaker->recordFailure();
    expect($circuitBreaker->isOpen())->toBeTrue();
    expect($circuitBreaker->canAttempt())->toBeFalse();
});

it('transitions from open to half-open after recovery timeout', function () {
    $circuitBreaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 2,
        'recovery_timeout_seconds' => 1,
    ]);

    // Force to open state
    $circuitBreaker->recordFailure();
    $circuitBreaker->recordFailure();
    expect($circuitBreaker->isOpen())->toBeTrue();

    // Should not transition immediately
    expect($circuitBreaker->canAttempt())->toBeFalse();

    // Travel forward in time past recovery timeout
    $this->travelTo(now()->addSeconds(2));

    // Should transition to half-open when attempting
    expect($circuitBreaker->canAttempt())->toBeTrue();
    expect($circuitBreaker->isHalfOpen())->toBeTrue();
});

it('transitions from half-open to closed on success', function () {
    $circuitBreaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 1,
        'recovery_timeout_seconds' => 10,
    ]);

    // Set to half-open state by transitioning through open first
    $circuitBreaker->recordFailure();
    expect($circuitBreaker->isOpen())->toBeTrue();

    $this->travelTo(now()->addSeconds(11));
    $circuitBreaker->canAttempt(); // This will transition to half-open

    expect($circuitBreaker->isHalfOpen())->toBeTrue();

    // Record success - should transition to closed
    $circuitBreaker->recordSuccess();
    expect($circuitBreaker->isClosed())->toBeTrue();
    expect($circuitBreaker->getFailureCount())->toBe(0);
});

it('transitions from half-open to open on failure', function () {
    $circuitBreaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 1,
        'recovery_timeout_seconds' => 10,
        'half_open_max_attempts' => 1,
    ]);

    // Set to half-open state by transitioning through open first
    $circuitBreaker->recordFailure();
    $this->travelTo(now()->addSeconds(11));
    $circuitBreaker->canAttempt(); // This will transition to half-open

    expect($circuitBreaker->isHalfOpen())->toBeTrue();

    // Record failure in half-open state - should transition back to open
    $circuitBreaker->recordFailure();
    expect($circuitBreaker->isOpen())->toBeTrue();
    expect($circuitBreaker->canAttempt())->toBeFalse();
});

it('maintains state through cache', function () {
    $circuitBreaker1 = new CircuitBreaker('persistent-service', $this->cache, $this->logger, $this->notifier, [
        'failure_threshold' => 2,
    ]);

    // Transition to open state
    $circuitBreaker1->recordFailure();
    $circuitBreaker1->recordFailure();
    expect($circuitBreaker1->isOpen())->toBeTrue();

    // Create new instance with same cache and name
    $circuitBreaker2 = new CircuitBreaker('persistent-service', $this->cache, $this->logger, $this->notifier);

    // Should maintain the open state from cache
    expect($circuitBreaker2->isOpen())->toBeTrue();
    expect($circuitBreaker2->getState())->toBe('open');
    expect($circuitBreaker2->canAttempt())->toBeFalse();

    // Verify state persisted in cache
    expect($this->cache->get('circuit_breaker:persistent-service:state'))->toBe('open');
    expect($this->cache->get('circuit_breaker:persistent-service:opened_at'))->not->toBeNull();
});
