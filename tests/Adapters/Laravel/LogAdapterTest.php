<?php

namespace christopheraseidl\CircuitBreaker\Tests\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelLogAdapter;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->logger = new LaravelLogAdapter;
});

it('logs emergency messages', function () {
    Log::spy();

    $this->logger->emergency('emergency message', ['context' => 'for emergencies']);

    Log::shouldHaveReceived('emergency')
        ->once()
        ->with('emergency message', ['context' => 'for emergencies']);
});

it('logs alert messages', function () {
    Log::spy();

    $this->logger->alert('System is under attack!', ['severity' => 'high', 'ip' => '192.168.1.1']);

    Log::shouldHaveReceived('alert')
        ->once()
        ->with('System is under attack!', ['severity' => 'high', 'ip' => '192.168.1.1']);
});

it('logs critical messages', function () {
    Log::spy();

    $this->logger->critical('Database connection lost', ['retry_count' => 5, 'service' => 'mysql']);

    Log::shouldHaveReceived('critical')
        ->once()
        ->with('Database connection lost', ['retry_count' => 5, 'service' => 'mysql']);
});

it('logs error messages', function () {
    Log::spy();

    $this->logger->error('Failed to process payment', ['transaction_id' => 'TXN-12345', 'amount' => 99.99]);

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to process payment', ['transaction_id' => 'TXN-12345', 'amount' => 99.99]);
});

it('logs warning messages', function () {
    Log::spy();

    $this->logger->warning('Memory usage high', ['usage' => '85%', 'threshold' => '80%']);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Memory usage high', ['usage' => '85%', 'threshold' => '80%']);
});

it('logs notice messages', function () {
    Log::spy();

    $this->logger->notice('User login successful', ['user_id' => 42, 'method' => '2FA']);

    Log::shouldHaveReceived('notice')
        ->once()
        ->with('User login successful', ['user_id' => 42, 'method' => '2FA']);
});

it('logs info messages', function () {
    Log::spy();

    $this->logger->info('Circuit breaker state changed', ['from' => 'closed', 'to' => 'open']);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Circuit breaker state changed', ['from' => 'closed', 'to' => 'open']);
});

it('logs debug messages', function () {
    Log::spy();

    $this->logger->debug('Request details', ['endpoint' => '/api/v1/users', 'duration_ms' => 123]);

    Log::shouldHaveReceived('debug')
        ->once()
        ->with('Request details', ['endpoint' => '/api/v1/users', 'duration_ms' => 123]);
});

it('includes context in log messages', function () {
    Log::spy();

    $complexContext = [
        'user' => ['id' => 123, 'email' => 'test@example.com'],
        'request' => ['ip' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0'],
        'metadata' => ['version' => '1.0', 'timestamp' => '2024-01-01 12:00:00'],
    ];

    $this->logger->error('Complex error occurred', $complexContext);

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Complex error occurred', $complexContext);
});

it('validates log level before logging', function () {
    Log::spy();

    $this->logger->log('error', 'Valid log message', ['key' => 'value']);

    Log::shouldHaveReceived('log')
        ->once()
        ->with('error', 'Valid log message', ['key' => 'value']);

    expect(fn () => $this->logger->log('invalid', 'This should fail', []))
        ->toThrow(\Psr\Log\InvalidArgumentException::class, 'Invalid log level: invalid');
});
