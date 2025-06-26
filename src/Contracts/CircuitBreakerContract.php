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

    /**
     * Transition circuit to open state when failure threshold is exceeded.
     */
    public function transitionToOpen(): void;

    /**
     * Transition circuit to half-open state for recovery testing.
     */
    public function transitionToHalfOpen(): void;

    /**
     * Transition circuit to closed state after successful recovery.
     */
    public function transitionToClosed(): void;

    /**
     * Generate cache key for storing circuit breaker state data.
     */
    public function getKey(string $name): string;

    /**
     * Store value in cache with TTL.
     */
    public function setKey(string $name, mixed $value, ?int $ttl = null): void;

    /**
     * Return current timestamp in standardized format.
     */
    public function getTimestamp(): string;

    /**
     * Send admin notification about circuit breaker state changes.
     */
    public function notify(string $message): void;

    /**
     * Build email content for admin notifications with circuit breaker details.
     */
    public function buildNotificationContent(string $message, array $stats): string;
}
