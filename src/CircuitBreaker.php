<?php

namespace christopheraseidl\CircuitBreaker;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\CircuitBreakerContract;
use christopheraseidl\CircuitBreaker\Contracts\FailureStrategyContract;
use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;
use christopheraseidl\CircuitBreaker\Strategies\TimeWindowStrategy;

/**
 * Prevents cascading failures by monitoring failure rates and blocking requests when thresholds are exceeded.
 */
class CircuitBreaker implements CircuitBreakerContract
{
    const STATE_CLOSED = 'closed';

    const STATE_OPEN = 'open';

    const STATE_HALF_OPEN = 'half_open';

    private FailureStrategyContract $strategy;

    private int $halfOpenDelay;

    public function __construct(
        private string $name,
        private CacheContract $cache,
        private LoggerContract $logger,
        private NotifierContract $notifier,
        array $config = []
    ) {
        $this->strategy = $config['strategy'] ?? new TimeWindowStrategy($config);
        $this->halfOpenDelay = $config['half_open_delay'] ?? 500;
    }

    /**
     * Check if circuit is in closed state.
     */
    public function isClosed(): bool
    {
        return $this->getState() === self::STATE_CLOSED;
    }

    /**
     * Check if circuit is in open state.
     */
    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    /**
     * Check if circuit is in half-open state.
     */
    public function isHalfOpen(): bool
    {
        return $this->getState() === self::STATE_HALF_OPEN;
    }

    /**
     * Determine if operation can be attempted in current state.
     */
    public function canAttempt(): bool
    {
        try {
            if ($this->isClosed()) {
                return true;
            }

            if ($this->isOpen()) {
                if ($this->strategy->shouldHalfOpenFromOpen(
                    $this->cache,
                    $this->getKey('opened_at'))
                ) {
                    $this->transitionToHalfOpen();

                    return true;
                }

                return false;
            }

            if ($this->isHalfOpen()) {
                return ! $this->strategy->shouldOpenFromHalfOpen(
                    $this->cache,
                    $this->getKey('half_open_attempts')
                );
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->warning('CircuitBreaker cache failure, failing open', [
                'breaker' => $this->name,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Record successful operation.
     */
    public function recordSuccess(): void
    {
        try {
            if ($this->isHalfOpen()) {
                $this->transitionToClosed();
                $this->logger->info("CircuitBreaker '{$this->name}' recovered and transitioned to CLOSED state at {$this->getTimestamp()}.");
            }

            $this->cache->forget($this->getKey('failures'));
        } catch (\Throwable $e) {
            $this->logger->warning('CircuitBreaker cache failure on recordSuccess', [
                'breaker' => $this->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record failed operation.
     */
    public function recordFailure(): void
    {
        try {
            $failures = $this->strategy->recordFailure($this->cache, $this->getKey('failures'));

            if ($this->isClosed() && $this->strategy->shouldOpenFromClosed($this->cache, $this->getKey('failures'))) {
                $this->transitionToOpen();
                $this->notify("Circuit breaker opened after {$failures} failures.");
            } elseif ($this->isHalfOpen()) {
                $this->setKey('last_half_open_attempt', microtime(true));
                $this->cache->increment($this->getKey('half_open_attempts'));
                $halfOpenAttempts = $this->cache->get($this->getKey('half_open_attempts'), 0);

                if ($this->strategy->shouldOpenFromHalfOpen($this->cache, $this->getKey('half_open_attempts'))) {
                    $this->transitionToOpen();
                    $this->notify("Circuit breaker reopened after {$halfOpenAttempts} half-open attempts.");
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('CircuitBreaker cache failure during recordFailure', [
                'breaker' => $this->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset circuit to closed state.
     */
    public function reset(): void
    {
        try {
            $this->transitionToClosed();
            $this->logger->info("CircuitBreaker '{$this->name}' manually reset to CLOSED state at {$this->getTimestamp()}.");
        } catch (\Throwable $e) {
            $this->logger->warning('CircuitBreaker cache failure during reset', [
                'breaker' => $this->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Return current state as string.
     */
    public function getState(): string
    {
        return $this->cache->get($this->getKey('state'), self::STATE_CLOSED);
    }

    /**
     * Return current failure count.
     */
    public function getFailureCount(): int
    {
        return $this->strategy->getCurrentFailureCount($this->cache, $this->getKey('failures'));
    }

    /**
     * Return circuit breaker statistics.
     */
    public function getStats(): array
    {
        $breakerStats = [
            'name' => $this->name,
            'state' => $this->getState(),
            'failure_count' => $this->getFailureCount(),
            'opened_at' => $this->cache->get($this->getKey('opened_at')),
        ];

        $strategyStats = $this->strategy->getStats();

        return array_merge($breakerStats, $strategyStats);
    }

    /**
     * Return the wait time before retrying.
     */
    public function getWaitTime(): int
    {
        if (! $this->isHalfOpen()) {
            return 0;
        }

        $lastAttempt = $this->cache->get($this->getKey('last_half_open_attempt'), 0);
        $timeSinceLastAttempt = (microtime(true) - $lastAttempt) * 1000; // Convert to ms
        $minDelay = $this->halfOpenDelay;

        if ($timeSinceLastAttempt < $minDelay) {
            return (int) ($minDelay - $timeSinceLastAttempt);
        }

        return 0;
    }

    /**
     * Transition circuit to open state when failure threshold is exceeded.
     */
    protected function transitionToOpen(): void
    {
        $this->setKey('state', self::STATE_OPEN);
        $this->setKey('opened_at', Carbon::now()->timestamp);
        $this->cache->forget($this->getKey('half_open_attempts'));
        $this->cache->forget($this->getKey('last_half_open_attempt'));

        $this->logger->warning("CircuitBreaker '{$this->name}' transitioned to OPEN state at {$this->getTimestamp()}.", $this->getStats());
    }

    /**
     * Transition circuit to half-open state for recovery testing.
     */
    protected function transitionToHalfOpen(): void
    {
        $this->setKey('state', self::STATE_HALF_OPEN);
        $this->setKey('half_open_attempts', 0);
        $this->setKey('last_half_open_attempt', microtime(true));

        $this->logger->warning("CircuitBreaker '{$this->name}' transitioned to HALF_OPEN state at {$this->getTimestamp()}.", $this->getStats());
    }

    /**
     * Transition circuit to closed state after successful recovery.
     */
    protected function transitionToClosed(): void
    {
        $this->cache->forget($this->getKey('state'));
        $this->cache->forget($this->getKey('failures'));
        $this->cache->forget($this->getKey('opened_at'));
        $this->cache->forget($this->getKey('half_open_attempts'));
        $this->cache->forget($this->getKey('last_half_open_attempt'));

        $this->logger->info("CircuitBreaker '{$this->name}' transitioned to CLOSED state at {$this->getTimestamp()}.", $this->getStats());
    }

    /**
     * Generate cache key for storing circuit breaker state data.
     */
    protected function getKey(string $name): string
    {
        return "circuit_breaker:{$this->name}:$name";
    }

    /**
     * Store value in cache with TTL.
     */
    protected function setKey(string $name, mixed $value, ?int $ttl = null): void
    {
        $this->cache->put($this->getKey($name), $value, $ttl);
    }

    /**
     * Return current timestamp in standardized format.
     */
    protected function getTimestamp(): string
    {
        return Carbon::now()->format('Y-m-d H:i:s T');
    }

    /**
     * Send admin notification about circuit breaker state changes.
     */
    protected function notify(string $message): void
    {
        try {
            $subject = "Circuit breaker alert: {$this->name}";
            $stats = $this->getStats();
            $content = $this->buildNotificationContent($message, $stats);

            $this->notifier->notify($content, [
                'subject' => $subject,
            ]);

            $this->logger->info('Circuit breaker notification sent to admin.', [
                'breaker' => $this->name,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send circuit breaker notification.', [
                'breaker' => $this->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build email content for admin notifications with circuit breaker details.
     */
    protected function buildNotificationContent(string $message, array $stats): string
    {
        return "
        Circuit breaker alert
        Time: {$this->getTimestamp()}

        {$message}

        Details:
        - Name: {$stats['name']} 
        - Current state: {$stats['state']}
        - Failure count: {$stats['failure_count']} / {$stats['failure_threshold']}
        - Recovery timeout: {$stats['recovery_timeout']} seconds

        This is an automatic notification. Please check the application logs for more details.
        ";
    }
}
