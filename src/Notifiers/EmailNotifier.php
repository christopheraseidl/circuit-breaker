<?php

namespace christopheraseidl\CircuitBreaker\Notifiers;

use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

/**
 * Sends notifications via email with recipient validation.
 */
class EmailNotifier implements NotifierContract
{
    /**
     * Create email notifier with mailer and recipient.
     */
    public function __construct(
        private MailerContract $mailer,
        private array $to
    ) {}

    /**
     * Send email notification with message and context.
     */
    public function notify(string $message, array $context = []): void
    {
        if (! $this->areValidEmails($this->to)) {
            throw new \InvalidArgumentException(
                "Invalid email address found in circuit breaker email notifier recipients: {$this->getRecipientsAsString()}"
            );
        }

        $this->mailer->send(
            $this->to,
            $context['subject'] ?? 'Circuit Breaker Alert',
            $message,
        );
    }

    protected function areValidEmails(array $recipients): bool
    {
        foreach ($recipients as $email) {
            if (! $this->isValidEmail($email)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate email address format.
     */
    protected function isValidEmail(string $email): bool
    {
        if (! $email) {
            return false;
        }

        // Use PHP's built-in email validation
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Return the email recipients as a comma-separated list.
     */
    private function getRecipientsAsString(): string
    {
        return implode(', ', $this->to);
    }
}
