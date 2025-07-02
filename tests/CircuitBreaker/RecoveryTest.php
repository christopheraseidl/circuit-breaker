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

it('allows two attempts in half-open state', function () {
    // Create circuit breaker with custom config
    $config = [
        'failure_threshold' => 2,
        'recovery_timeout_seconds' => 1,
        'half_open_max_attempts' => 3,
        'half_open_delay_seconds' => 0,
    ];

    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, $config);

    // Open the circuit
    $breaker->recordFailure();
    $breaker->recordFailure();

    expect($breaker->isOpen())->toBeTrue();

    // Wait for recovery timeout
    sleep(2);

    // Should transition to half-open and allow attempt
    expect($breaker->canAttempt())->toBeTrue();
    expect($breaker->isHalfOpen())->toBeTrue();

    // First attempt fails
    $breaker->recordFailure();

    // Should still allow second attempt (under max attempts)
    expect($breaker->canAttempt())->toBeTrue();
    expect($breaker->isHalfOpen())->toBeTrue();
});

it('enforces delay between half-open attempts', function () {
    $config = [
        'failure_threshold' => 2,
        'recovery_timeout_seconds' => 1,
        'half_open_max_attempts' => 3,
        'half_open_delay_seconds' => 2,
    ];

    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, $config);

    // Open the circuit
    $breaker->recordFailure();
    $breaker->recordFailure();

    // Wait for recovery timeout
    sleep(2);

    // First attempt allowed (transitions to half-open)
    expect($breaker->canAttempt())->toBeTrue();

    // Record failure in half-open state
    $breaker->recordFailure();

    // Immediately after failure, should not allow attempt (delay not passed)
    expect($breaker->canAttempt())->toBeFalse();

    // Wait for delay to pass
    sleep(3);

    // Now should allow attempt
    expect($breaker->canAttempt())->toBeTrue();
});

it('tracks half-open attempt count', function () {
    $config = [
        'failure_threshold' => 2,
        'recovery_timeout_seconds' => 1,
        'half_open_max_attempts' => 3,
        'half_open_delay_seconds' => 0,
    ];

    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, $config);

    // Open the circuit
    $breaker->recordFailure();
    $breaker->recordFailure();

    // Wait for recovery timeout
    sleep(2);

    // Transition to half-open
    expect($breaker->canAttempt())->toBeTrue();

    // Check initial attempt count
    $attemptCount = $this->cache->get('circuit_breaker:test:half_open_attempts');
    expect($attemptCount)->toBe(0);

    // First failure in half-open
    $breaker->recordFailure();
    $attemptCount = $this->cache->get('circuit_breaker:test:half_open_attempts');
    expect($attemptCount)->toBe(1);

    // Second failure in half-open
    $breaker->recordFailure();
    $attemptCount = $this->cache->get('circuit_breaker:test:half_open_attempts');
    expect($attemptCount)->toBe(2);

    // Third failure should transition back to open
    $breaker->recordFailure();
    expect($breaker->isOpen())->toBeTrue();

    // Attempt count should be cleared
    $attemptCount = $this->cache->get('circuit_breaker:test:half_open_attempts');
    expect($attemptCount)->toBeNull();
});

it('calculates wait time correctly', function () {
    $config = [
        'failure_threshold' => 2,
        'recovery_timeout_seconds' => 1,
        'half_open_max_attempts' => 5,
        'half_open_delay_seconds' => 1,
    ];

    $breaker = new CircuitBreaker('test', $this->cache, $this->logger, $this->notifier, $config);

    // Open the circuit
    $breaker->recordFailure();
    $breaker->recordFailure();

    // Wait for recovery timeout
    sleep(2);

    // First attempt (transitions to half-open)
    expect($breaker->canAttempt())->toBeTrue();
    expect($breaker->isHalfOpen())->toBeTrue();

    // First failure - base delay is 1 second
    $breaker->recordFailure();
    expect($breaker->canAttempt())->toBeFalse();

    // Wait to pass the max delay of 1.2 seconds with jitter
    usleep(1300000); // 1.3 seconds
    expect($breaker->canAttempt())->toBeTrue();

    // Second failure - base delay should be max 2.4 seconds with jitter and exponential backoff
    $breaker->recordFailure();
    expect($breaker->canAttempt())->toBeFalse();

    // Wait for the exponential delay
    sleep(3);
    expect($breaker->canAttempt())->toBeTrue();
});
