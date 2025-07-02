<?php

namespace christopheraseidl\CircuitBreaker\Tests\CircuitBreaker;

use christopheraseidl\CircuitBreaker\CircuitBreaker;
use christopheraseidl\CircuitBreaker\CircuitBreakerFactory;
use christopheraseidl\CircuitBreaker\Contracts\FailureStrategyContract;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestCacheAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestLoggerAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestNotifierAdapter;

beforeEach(function () {
    $this->cache = new TestCacheAdapter;
    $this->logger = new TestLoggerAdapter;
    $this->notifier = new TestNotifierAdapter;
    $this->factory = new CircuitBreakerFactory($this->cache, $this->logger, $this->notifier);
});

it('creates circuit breaker with default config', function () {
    $breaker = $this->factory->make('test-breaker');

    expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    expect($breaker->getState())->toBe(CircuitBreaker::STATE_CLOSED);
    expect($breaker->getFailureCount())->toBe(0);
    expect($breaker->canAttempt())->toBeTrue();

    // Verify it uses default TimeWindowStrategy
    $stats = $breaker->getStats();
    expect($stats)->toHaveKey('name', 'test-breaker');
    expect($stats)->toHaveKey('state', CircuitBreaker::STATE_CLOSED);
    expect($stats)->toHaveKey('failure_count', 0);
});

it('creates circuit breaker with custom config', function () {
    $config = [
        'failure_threshold' => 10,
        'recovery_timeout_seconds' => 120,
        'window_seconds' => 300,
    ];

    $breaker = $this->factory->make('custom-breaker', $config);

    expect($breaker)->toBeInstanceOf(CircuitBreaker::class);

    // Verify custom config is passed through by checking stats
    $stats = $breaker->getStats();
    expect($stats['failure_threshold'])->toBe(10);
    expect($stats['recovery_timeout_seconds'])->toBe(120);
    expect($stats['window_seconds'])->toBe(300);
});

it('creates circuit breaker with custom strategy', function () {
    $customStrategy = $this->mock(FailureStrategyContract::class);
    $customStrategy->shouldReceive('getStats')->once()->andReturn([
        'custom_strategy' => true,
        'failure_threshold' => 20,
        'recovery_timeout_seconds' => 60,
    ]);
    $customStrategy->shouldReceive('getCurrentFailureCount')->once()->andReturn(5);

    $config = ['strategy' => $customStrategy];

    $breaker = $this->factory->make('strategy-breaker', $config);

    expect($breaker)->toBeInstanceOf(CircuitBreaker::class);

    // Verify custom strategy is being used
    $stats = $breaker->getStats();
    expect($stats)->toHaveKey('custom_strategy', true);
    expect($stats['failure_threshold'])->toBe(20);
    expect($stats['failure_count'])->toBe(5);
});

it('does not cache created instances', function () {
    $breaker1 = $this->factory->make('same-name');
    $breaker2 = $this->factory->make('same-name');

    // Each call creates a new instance
    expect($breaker1)->not->toBe($breaker2);

    // But they should have the same configuration
    expect($breaker1->getStats()['name'])->toBe('same-name');
    expect($breaker2->getStats()['name'])->toBe('same-name');

    // They share the same dependencies
    $breaker1->recordFailure();
    expect($breaker1->getFailureCount())->toBe(1);
    expect($breaker2->getFailureCount())->toBe(1); // Same cache key
});
