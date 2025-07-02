<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Circuit Breaker Settings
    |--------------------------------------------------------------------------
    |
    | Global defaults for all circuit breakers. Can be overridden when
    | instantiating individual circuit breakers.
    |
    */
    'defaults' => [
        // Number of failures before opening circuit
        'failure_threshold' => 5,

        // Time window for counting failures (seconds)
        'window_seconds' => 60,

        // How long to wait before transitioning from open to half-open (seconds)
        'recovery_timeout_seconds' => 300,

        // Maximum attempts in half-open state before reopening
        'half_open_max_attempts' => 3,

        // Base delay between half-open attempts (seconds)
        'half_open_delay_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Notifiers
    |--------------------------------------------------------------------------
    |
    | Configure how circuit breaker state changes are communicated. Multiple
    | notifiers can be chained to alert through various channels.
    |
    | Laravel users: Replace null values with env() calls:
    | 'recipients' => [env('MAIL_FROM_ADDRESS')],
    | 'from_address' => env('MAIL_FROM_ADDRESS'),
    | 'from_name' => env('MAIL_FROM_NAME'),
    */
    'notifiers' => [
        'email' => [
            // Email addresses to receive circuit breaker alerts
            'recipients' => null,

            // Sender email address for notifications
            'from_address' => null,

            // Sender name for notifications
            'from_name' => 'Circuit Breaker',
        ],

        // Example: Slack notifier (requires custom implementation)
        // 'slack' => [
        //     'webhook_url' => env('SLACK_WEBHOOK_URL'),
        //     'channel' => '#alerts',
        // ],
    ],
];
