<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use Illuminate\Support\Facades\Mail;

class LaravelMailAdapter implements MailerContract
{
    public function send(string $to, string $subject, string $body): void
    {
        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->to($to)->subject($subject);
        });
    }
}
