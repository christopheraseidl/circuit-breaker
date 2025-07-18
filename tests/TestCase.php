<?php

namespace christopheraseidl\CircuitBreaker\Tests;

use christopheraseidl\CircuitBreaker\Laravel\CircuitBreakerServiceProvider;
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
}
