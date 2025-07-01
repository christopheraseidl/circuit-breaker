<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

/**
 * Implements a circuit breaker to prevent cascading failures.
 *
 * Circuit breakers monitor failure rates and temporarily block operations
 * when failure thresholds are exceeded, allowing systems to recover.
 */
interface CircuitBreakerContract
{
    public function __construct(
        string $name,
        CacheContract $cache,
        LoggerContract $logger,
        NotifierContract $notifier,
        array $config = []
    );

    /**
     * Check if circuit is in closed state (normal operation).
     */
    public function isClosed(): bool;

    /**
     * Check if circuit is in open state (blocking calls).
     */
    public function isOpen(): bool;

    /**
     * Check if circuit is in half-open state (testing recovery).
     */
    public function isHalfOpen(): bool;

    /**
     * Determine if operation can be attempted in current state.
     */
    public function canAttempt(): bool;

    /**
     * Record successful operation.
     */
    public function recordSuccess(): void;

    /**
     * Record failed operation.
     */
    public function recordFailure(): void;

    /**
     * Reset circuit to closed state.
     */
    public function reset(): void;

    /**
     * Return current state as string.
     */
    public function getState(): string;

    /**
     * Return current failure count.
     */
    public function getFailureCount(): int;

    /**
     * Return circuit breaker statistics.
     */
    public function getStats(): array;
}
