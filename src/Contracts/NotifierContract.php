<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

interface NotifierContract
{
    public function notify(string $message, array $context = []);
}
