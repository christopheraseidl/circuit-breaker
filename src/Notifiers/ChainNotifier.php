<?php

namespace christopheraseidl\CircuitBreaker\Notifiers;

use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

/**
 * Chains multiple notifiers with failure isolation.
 */
class ChainNotifier implements NotifierContract
{
    private array $notifiers;

    /**
     * Create chain notifier with logger and notifier collection.
     */
    public function __construct(
        private LoggerContract $logger,
        array $notifiers = []
    ) {
        $this->notifiers = $notifiers;
    }

    /**
     * Send notification through all notifiers.
     */
    public function notify(string $message, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->notify($message, $context);
            } catch (\Throwable $e) {
                // Log failure but continue with remaining notifiers
                $this->logger->error('Notifier failed: '.$e->getMessage());
            }
        }
    }
}
