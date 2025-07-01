<?php

return [
    'notifiers' => [
        'email' => [
            'recipients' => [env('MAIL_FROM_ADDRESS')],
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
        ],
    ],
];
