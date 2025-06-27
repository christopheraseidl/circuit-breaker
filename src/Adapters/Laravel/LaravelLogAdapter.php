<?php

namespace christopheraseidl\CircuitBreaker\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

/**
 * Adapts Laravel Log facade to circuit breaker logger contract.
 */
class LaravelLogAdapter implements LoggerContract
{
    /**
     * Log emergency message.
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        Log::emergency($message, $context);
    }

    /**
     * Log alert message.
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        Log::alert($message, $context);
    }

    /**
     * Log critical message.
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        Log::critical($message, $context);
    }

    /**
     * Log error message.
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * Log warning message.
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    /**
     * Log notice message.
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        Log::notice($message, $context);
    }

    /**
     * Log info message.
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * Log debug message.
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    /**
     * Log message with arbitrary level.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Validate the log level
        if (! in_array($level, [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ])) {
            throw new \Psr\Log\InvalidArgumentException("Invalid log level: {$level}");
        }

        Log::log($level, $message, $context);
    }
}
