<?php

namespace christopheraseidl\CircuitBreaker\Tests\Helpers;

use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;

/**
 * Test implementation of NotifierContract for unit testing.
 */
class TestNotifierAdapter implements NotifierContract
{
    public array $notifications = [];

    public function notify(string $message, array $context = []): void
    {
        $this->notifications[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => time(),
        ];
    }

    /**
     * Clear all notifications.
     */
    public function clear(): void
    {
        $this->notifications = [];
    }

    /**
     * Get the last notification sent.
     */
    public function getLastNotification(): ?array
    {
        return end($this->notifications) ?: null;
    }

    /**
     * Check if any notification contains the given message.
     */
    public function hasMessage(string $message): bool
    {
        return ! empty(array_filter($this->notifications, fn ($notification) => str_contains($notification['message'], $message)));
    }

    /**
     * Get notifications count.
     */
    public function count(): int
    {
        return count($this->notifications);
    }
}
