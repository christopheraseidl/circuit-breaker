<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

/**
 * Defines email sending operations for circuit breaker notifications.
 */
interface MailerContract
{
    /**
     * Send email with subject and message.
     */
    public function send(array $to, string $subject, string $message);
}
