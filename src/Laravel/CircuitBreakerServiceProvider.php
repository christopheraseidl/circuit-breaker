<?php

namespace christopheraseidl\CircuitBreaker\Laravel;

use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelCacheAdapter;
use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelLogAdapter;
use christopheraseidl\CircuitBreaker\Adapters\Laravel\LaravelMailAdapter;
use christopheraseidl\CircuitBreaker\CircuitBreakerFactory;
use christopheraseidl\CircuitBreaker\Contracts\CacheContract;
use christopheraseidl\CircuitBreaker\Contracts\LoggerContract;
use christopheraseidl\CircuitBreaker\Contracts\MailerContract;
use christopheraseidl\CircuitBreaker\Contracts\NotifierContract;
use christopheraseidl\CircuitBreaker\Notifiers\ChainNotifier;
use christopheraseidl\CircuitBreaker\Notifiers\EmailNotifier;
use Illuminate\Support\ServiceProvider;

class CircuitBreakerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the adapters
        $this->app->singleton(CacheContract::class, LaravelCacheAdapter::class);
        $this->app->singleton(LoggerContract::class, LaravelLogAdapter::class);
        $this->app->singleton(MailerContract::class, LaravelMailAdapter::class);

        // Bind services
        $this->app->singleton(NotifierContract::class, function ($app) {
            return $this->buildNotifier();
        });

        // Bind the factory
        $this->app->singleton(CircuitBreakerFactory::class, function ($app) {
            return new CircuitBreakerFactory(
                cache: $app->make(CacheContract::class),
                logger: $app->make(LoggerContract::class),
                notifier: $app->make(NotifierContract::class)
            );
        });

        // Register config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/circuit-breaker.php',
            'circuit-breaker'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/circuit-breaker.php' => config_path('circuit-breaker.php'),

        ]);
    }

    private function buildNotifier(): NotifierContract
    {
        $notifiers = config('circuit-breaker.notifiers', []);
        $notifierInstances = [];

        foreach ($notifiers as $notifierConfig) {
            $notifierInstances[] = match ($notifierConfig['type']) {
                'email' => new EmailNotifier(
                    mailer: $this->app->make(MailerContract::class),
                    to: $notifierConfig['recipients']
                ),
                default => throw new \InvalidArgumentException("Unknown notifier type: {$notifierConfig['type']}")
            };
        }

        return new ChainNotifier($this->app->make(LoggerContract::class), $notifierInstances);
    }
}
