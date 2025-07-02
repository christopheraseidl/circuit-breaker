<?php

namespace christopheraseidl\CircuitBreaker\Tests\Helpers;

use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;

/**
 * Test implementation of LoggerContract for unit testing.
 */
class TestLoggerAdapter implements LoggerContract
{
    public array $logs = [];

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
            'timestamp' => time(),
        ];
    }

    /**
     * Clear all logged messages.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Get logs by level.
     */
    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->logs, fn ($log) => $log['level'] === $level);
    }

    /**
     * Check if any logs contain the given message.
     */
    public function hasMessage(string $message): bool
    {
        return ! empty(array_filter($this->logs, fn ($log) => str_contains($log['message'], $message)));
    }
}
