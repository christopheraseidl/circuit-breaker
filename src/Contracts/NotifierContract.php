<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

/**
 * Defines notification operations for circuit breaker events.
 */
interface NotifierContract
{
    /**
     * Send notification with message and context.
     */
    public function notify(string $message, array $context = []);
}
