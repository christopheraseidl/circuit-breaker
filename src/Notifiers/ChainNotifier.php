<?php

namespace christopheraseidl\CircuitBreaker\Notifiers;

use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

class ChainNotifier implements NotifierContract
{
    private array $notifiers;

    public function __construct(
        private LoggerContract $logger,
        array $notifiers = []
    ) {
        $this->notifiers = $notifiers;
    }

    public function notify(string $message, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->notify($message, $context);
            } catch (\Throwable $e) {
                // Log the failure but continue with other notifiers
                $this->logger->error('Notifier failed: '.$e->getMessage());
            }
        }
    }
}
