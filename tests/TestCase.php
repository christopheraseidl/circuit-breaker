<?php

namespace christopheraseidl\CircuitBreaker\Tests;

use christopheraseidl\CircuitBreaker\Laravel\CircuitBreakerServiceProvider;
use christopheraseidl\CircuitBreaker\Support\Config;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            CircuitBreakerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        Config::set('database.default', 'testing');
    }
}
