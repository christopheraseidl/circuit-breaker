# Circuit Breaker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/christopheraseidl/circuit-breaker.svg?style=flat-square)](https://packagist.org/packages/christopheraseidl/circuit-breaker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/christopheraseidl/circuit-breaker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/christopheraseidl/circuit-breaker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/christopheraseidl/circuit-breaker/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/christopheraseidl/circuit-breaker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/christopheraseidl/circuit-breaker.svg?style=flat-square)](https://packagist.org/packages/christopheraseidl/circuit-breaker)

A framework-agnostic circuit breaker implementation that prevents cascading failures by monitoring error rates and temporarily blocking requests when thresholds are exceeded.

## Installation

You can install the package via composer:

```bash
composer require christopheraseidl/circuit-breaker
```

### Laravel Installation

The package auto-registers its service provider and automatically wires up Laravel's cache, logger, and mailer as adapters.

You may optionally publish the configuration file:

```bash
php artisan vendor:publish --tag="circuit-breaker-config"
```

After publishing, update the config file to use Laravel's `env()` helper (recommended):

```php
'notifiers' => [
    'email' => [
        'recipients' => [env('MAIL_FROM_ADDRESS')],
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
    ],
],
```

Optionally publish the email notification view:

```bash
php artisan vendor:publish --tag="circuit-breaker-views"
```

## Usage

### Basic Example

```php
use christopheraseidl\CircuitBreaker\CircuitBreaker;

// Create circuit breaker instance
$breaker = new CircuitBreaker(
    name: 'payment-gateway',
    cache: $cache,        // Must implement CacheContract
    logger: $logger,      // Must implement LoggerContract  
    notifier: $notifier,  // Must implement NotifierContract
    config: [
        'failure_threshold' => 5,
        'window_seconds' => 60,
        'recovery_timeout_seconds' => 300,
    ]
);

// Wrap external service calls
if ($breaker->canAttempt()) {
    try {
        $result = $paymentGateway->charge($amount);
        $breaker->recordSuccess();
        return $result;
    } catch (\Exception $e) {
        $breaker->recordFailure();
        throw $e;
    }
} else {
    throw new ServiceUnavailableException('Payment service temporarily unavailable');
}
```

### Laravel Example

```php
use christopheraseidl\CircuitBreaker\CircuitBreakerFactory;

// Using dependency injection
public function __construct(
    private CircuitBreakerFactory $circuitBreakerFactory
) {}

public function processPayment($amount)
{
    $breaker = $this->circuitBreakerFactory->make('payment-service', [
        'failure_threshold' => 3,
        'recovery_timeout_seconds' => 120,
    ]);
    
    if ($breaker->canAttempt()) {
        try {
            $response = Http::timeout(5)->post('https://payments.example.com/charge', [
                'amount' => $amount,
            ]);
            $breaker->recordSuccess();
            return $response->json();
        } catch (\Exception $e) {
            $breaker->recordFailure();
            return ['error' => 'Payment service temporarily unavailable'];
        }
    }
    
    return ['error' => 'Payment service is currently down'];
}

// Or resolve from container
$factory = app(CircuitBreakerFactory::class);
$breaker = $factory->make('api-service');

if (! $breaker->canAttempt()) {
    return cache()->remember('api-fallback-data', 3600, fn() => [...]);
}
```

### Monitoring Circuit State

```php
// Check circuit state
if ($breaker->isOpen()) {
    // Circuit is open - requests are blocked
}

if ($breaker->isHalfOpen()) {
    // Circuit is testing recovery
}

// Get statistics
$stats = $breaker->getStats();
// [
//     'name' => 'payment-gateway',
//     'state' => 'open',
//     'failure_count' => 5,
//     'failure_threshold' => 5,
//     'recovery_timeout_seconds' => 300,
// ]

// Manually reset circuit
$breaker->reset();
```

## Configuration

The default configuration covers common use cases:

```php
[
    'defaults' => [
        'failure_threshold' => 5,          // Open circuit after 5 failures
        'window_seconds' => 60,            // Count failures within 60 seconds
        'recovery_timeout_seconds' => 300, // Try recovery after 5 minutes
        'half_open_max_attempts' => 3,     // Allow 3 tests before reopening
        'half_open_delay' => 1,            // Base delay, in seconds, between recovery attempts
    ],
    'notifiers' => [
        'email' => [
            'recipients' => null,      // Set email addresses for notifications
            'from_address' => null,    // Set sender email address
            'from_name' => 'Circuit Breaker',
        ],
    ],
]
```

## Implementing Adapters

To use the circuit breaker outside Laravel, implement the required contracts:

```php
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;

class RedisCache implements CacheContract
{
    public function put(string $key, mixed $value, ?int $ttl = 1): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }
    
    // ... implement remaining methods
}
```

## Testing

```bash
./vendor/bin/pest
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Chris Seidl](https://github.com/christopheraseidl)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.