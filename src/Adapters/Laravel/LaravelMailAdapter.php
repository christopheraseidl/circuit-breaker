<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use christopheraseidl\CircuitBreaker\Laravel\EmailAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Adapts Laravel Mail facade to circuit breaker mailer contract.
 */
class LaravelMailAdapter implements MailerContract
{
    /**
     * Send email with subject and body.
     */
    public function send(array $to, string $subject, string $body): void
    {
        try {
            Mail::to($to)->queue(new EmailAlert([
                'subject' => $subject,
                'body' => $body,
            ]));
        } catch (\Throwable $e) {
            Log::error('Failed to send Circuit Breaker email alert', [
                'mailer' => $this::class,
                'error' => $e->getMessage(),
                'to' => $to,
                'subject' => $subject,
                'message' => $body,
            ]);
        }
    }
}
