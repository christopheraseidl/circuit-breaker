<?php

namespace christopheraseidl\CircuitBreaker\Notifiers;

use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

class EmailNotifier implements NotifierContract
{
    public function __construct(
        private MailerContract $mailer,
        private string $to
    ) {}

    public function notify(string $message, array $context = [])
    {
        if (! $this->isValidEmail($this->to)) {
            return;
        }

        $this->mailer->send(
            $this->to,
            $context['subject'] ?? 'Circuit Breaker Alert',
            $message,
        );
    }

    protected function isValidEmail(string $email): bool
    {
        if (! $email) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
