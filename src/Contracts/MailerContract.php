<?php

namespace christopheraseidl\CircuitBreaker\Contracts;

interface MailerContract
{
    public function send(string $to, string $subject, string $message);
}
