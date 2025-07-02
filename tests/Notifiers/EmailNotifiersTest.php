<?php

use christopheraseidl\CircuitBreaker\Notifiers\EmailNotifier;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestMailerAdapter;

beforeEach(function () {
    $this->mailer = new TestMailerAdapter;
});

it('sends notification via email', function () {
    $recipients = ['admin@example.com', 'dev@example.com'];
    $notifier = new EmailNotifier($this->mailer, $recipients);

    $message = 'Circuit breaker has opened';
    $context = ['subject' => 'Alert: Service Down'];

    $notifier->notify($message, $context);

    expect($this->mailer->count())->toBe(1);

    $sentEmail = $this->mailer->getLastEmail();
    expect($sentEmail['to'])->toBe($recipients);
    expect($sentEmail['subject'])->toBe('Alert: Service Down');
    expect($sentEmail['message'])->toBe($message);
});

it('formats notification content', function () {
    $recipients = ['admin@example.com'];
    $notifier = new EmailNotifier($this->mailer, $recipients);

    // Test with custom subject from context
    $notifier->notify('Service is down', ['subject' => 'Custom Alert Subject']);

    $sentEmail = $this->mailer->getLastEmail();
    expect($sentEmail['subject'])->toBe('Custom Alert Subject');

    $this->mailer->clear();

    // Test with default subject when not provided in context
    $notifier->notify('Service is down', []);

    $sentEmail = $this->mailer->getLastEmail();
    expect($sentEmail['subject'])->toBe('Circuit Breaker Alert');
});

it('validates email configuration', function () {
    // Test with invalid email addresses
    $invalidRecipients = ['invalid-email', 'another@invalid', ''];
    $notifier = new EmailNotifier($this->mailer, $invalidRecipients);

    expect(fn () => $notifier->notify('Test message'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid email address found in circuit breaker email notifier recipients');
});

it('validates email configuration with mixed valid and invalid emails', function () {
    $mixedRecipients = ['valid@example.com', 'invalid-email', 'another@example.com'];
    $notifier = new EmailNotifier($this->mailer, $mixedRecipients);

    expect(fn () => $notifier->notify('Test message'))
        ->toThrow(\InvalidArgumentException::class);
});

it('handles notification failures gracefully', function () {
    $recipients = ['admin@example.com'];
    $notifier = new EmailNotifier($this->mailer, $recipients);

    // Set up mailer to throw exception
    $this->mailer->shouldThrow(new \Exception('Mail service unavailable'));

    // Exception should bubble up from EmailNotifier
    expect(fn () => $notifier->notify('Test message'))
        ->toThrow(\Exception::class, 'Mail service unavailable');

    // Verify no emails were actually sent
    expect($this->mailer->count())->toBe(0);
});
