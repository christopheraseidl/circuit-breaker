<?php

namespace christopheraseidl\CircuitBreaker;

use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

/**
 * Creates circuit breaker instances with injected dependencies.
 */
class CircuitBreakerFactory
{
    /**
     * Create factory with required dependencies.
     */
    public function __construct(
        private CacheContract $cache,
        private LoggerContract $logger,
        private NotifierContract $notifier
    ) {}

    /**
     * Create circuit breaker with name and configuration.
     */
    public function make(string $name, array $config = []): CircuitBreaker
    {
        return new CircuitBreaker(
            name: $name,
            cache: $this->cache,
            logger: $this->logger,
            notifier: $this->notifier,
            config: $config
        );
    }
}
