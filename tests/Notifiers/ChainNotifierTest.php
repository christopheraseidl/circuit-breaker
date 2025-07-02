<?php

namespace christopheraseidl\CircuitBreaker\Tests\Notifiers;

use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;
use christopheraseidl\CircuitBreaker\Notifiers\ChainNotifier;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestLoggerAdapter;
use christopheraseidl\CircuitBreaker\Tests\Helpers\TestNotifierAdapter;

beforeEach(function () {
    $this->logger = new TestLoggerAdapter;
});

it('sends notifications to all notifiers', function () {

    $notifier1 = new TestNotifierAdapter;
    $notifier2 = new TestNotifierAdapter;

    $chainNotifier = new ChainNotifier($this->logger, [$notifier1, $notifier2]);

    $message = 'Test notification';
    $context = ['key' => 'value'];

    $chainNotifier->notify($message, $context);

    expect($notifier1->count())->toBe(1);
    expect($notifier1->getLastNotification()['message'])->toBe($message);
    expect($notifier1->getLastNotification()['context'])->toBe($context);

    expect($notifier2->count())->toBe(1);
    expect($notifier2->getLastNotification()['message'])->toBe($message);
    expect($notifier2->getLastNotification()['context'])->toBe($context);
});

it('continues after individual notifier failure', function () {
    $failingNotifier = new class implements NotifierContract
    {
        public function notify(string $message, array $context = []): void
        {
            throw new \Exception('Notifier failed');
        }
    };

    $workingNotifier = new TestNotifierAdapter;

    $chainNotifier = new ChainNotifier($this->logger, [$failingNotifier, $workingNotifier]);

    $message = 'Test notification';

    $chainNotifier->notify($message);

    // Verify the working notifier was still called
    expect($workingNotifier->count())->toBe(1);
    expect($workingNotifier->getLastNotification()['message'])->toBe($message);

    // Verify the failure was logged
    expect($this->logger->hasMessage('Notifier failed: Notifier failed'))->toBeTrue();
    expect($this->logger->getLogsByLevel('error'))->toHaveCount(1);
});

it('handles empty notifier chain', function () {
    $chainNotifier = new ChainNotifier($this->logger, []);

    // This should not throw any exceptions
    $chainNotifier->notify('Test message', ['context' => 'data']);

    // No errors should be logged
    expect($this->logger->getLogsByLevel('error'))->toHaveCount(0);
    expect($this->logger->logs)->toHaveCount(0);
});
