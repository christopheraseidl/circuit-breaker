<?php

namespace christopheraseidl\CircuitBreaker\Tests\Adapters\Laravel;

use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelCacheAdapter;
use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelLogAdapter;
use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelMailAdapter;
use christopheraseidl\CircuitBreaker\CircuitBreakerFactory;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;
use christopheraseidl\CircuitBreaker\Notifiers\ChainNotifier;

it('registers singleton cache adapter binding', function () {
    $firstCacheAdapter = app(CacheContract::class);
    $secondCacheAdapter = app(CacheContract::class);

    expect($firstCacheAdapter)->toBe($secondCacheAdapter)
        ->and($firstCacheAdapter)->toBeInstanceOf(LaravelCacheAdapter::class);
});

it('registers singleton log adapter binding', function () {
    $firstLogAdapter = app(LoggerContract::class);
    $secondLogAdapter = app(LoggerContract::class);

    expect($firstLogAdapter)->toBe($secondLogAdapter)
        ->and($firstLogAdapter)->toBeInstanceOf(LaravelLogAdapter::class);
});

it('registers singleton mail adapter binding', function () {
    $firstMailAdapter = app(MailerContract::class);
    $secondMailAdapter = app(MailerContract::class);

    expect($firstMailAdapter)->toBe($secondMailAdapter)
        ->and($firstMailAdapter)->toBeInstanceOf(LaravelMailAdapter::class);
});

it('registers singleton notifier binding', function () {
    config()->set('circuit-breaker.notifiers', [
        'email' => [
            'recipients' => ['admin@example.com', 'superviser@example.com'],
            'from_address' => 'admin@example.com',
            'from_name' => 'Administrator',
        ],
    ]);

    $firstNotifier = app(NotifierContract::class);
    $secondNotifier = app(NotifierContract::class);

    expect($firstNotifier)->toBe($secondNotifier)
        ->and($firstNotifier)->toBeInstanceOf(ChainNotifier::class);
});

it('registers singleton circuit breaker factory', function () {
    $firstFactory = app(CircuitBreakerFactory::class);
    $secondFactory = app(CircuitBreakerFactory::class);

    expect($firstFactory)->toBe($secondFactory)
        ->and($firstFactory)->toBeInstanceOf(CircuitBreakerFactory::class);
});

it('merges config correctly', function () {
    config()->set('circuit-breaker.some_key', 'value');

    expect(config('circuit-breaker.some_key'))->toBe('value');
    expect(config('circuit-breaker.notifiers'))->toBe([
        'email' => [
            'recipients' => [env('MAIL_FROM_ADDRESS')],
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
        ],
    ]);
});

it('publishes config file', function () {
    $configFilePath = config_path('circuit-breaker.php');

    expect($configFilePath)->not->toBeFile();

    $this->artisan('vendor:publish --tag=circuit-breaker-config')
        ->assertSuccessful();

    expect($configFilePath)->toBeFile();

    unlink($configFilePath);
});
