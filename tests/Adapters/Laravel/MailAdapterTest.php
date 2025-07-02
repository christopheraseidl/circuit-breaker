<?php

namespace christopheraseidl\CircuitBreaker\Tests\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelMailAdapter;
use christopheraseidl\CircuitBreaker\Laravel\EmailAlert;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    config()->set('circuit-breaker.notifiers.email.from_address', 'admin@example.com');
    config()->set('circuit-breaker.notifiers.email.from_name', 'Circuit Breaker test');

    $this->mailer = new LaravelMailAdapter;
});

it('sends email to single recipient', function () {
    $this->mailer->send(['test@example.com'], 'Test Subject', 'Test Body');

    Mail::assertQueued(EmailAlert::class, function (EmailAlert $mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('sends email to multiple recipients', function () {
    $recipients = ['test1@example.com', 'test2@example.com', 'test3@example.com'];
    $this->mailer->send($recipients, 'Test Subject', 'Test Body');

    Mail::assertQueued(EmailAlert::class, function (EmailAlert $mail) use ($recipients) {
        return $mail->hasTo($recipients);
    });
});

it('includes subject in email', function () {
    $subject = 'Circuit Breaker Alert: Service Down';
    $this->mailer->send(['test@example.com'], $subject, 'Test Body');

    Mail::assertQueued(EmailAlert::class, function (EmailAlert $mail) use ($subject) {
        return $mail->subject === $subject;
    });
});

it('formats message content correctly', function () {
    $body = 'Circuit breaker has opened for service XYZ at '.now();
    $this->mailer->send(['test@example.com'], 'Test Subject', $body);

    Mail::assertQueued(EmailAlert::class, function ($mail) use ($body) {
        return $mail->body === $body;
    });
});

it('handles mail exceptions gracefully', function () {
    Mail::shouldReceive('to')
        ->andReturnSelf()
        ->shouldReceive('queue')
        ->andThrow(new \Exception('Mail service unavailable'));

    expect(fn () => $this->mailer->send(['test@example.com'], 'Test Subject', 'Test Body'))
        ->toThrow(\Exception::class);
});
