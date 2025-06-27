<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use Illuminate\Support\Facades\Mail;

/**
 * Adapts Laravel Mail facade to circuit breaker mailer contract.
 */
class LaravelMailAdapter implements MailerContract
{
    /**
     * Send email with subject and body.
     */
    public function send(string $to, string $subject, string $body): void
    {
        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->to($to)->subject($subject);
        });
    }
}
