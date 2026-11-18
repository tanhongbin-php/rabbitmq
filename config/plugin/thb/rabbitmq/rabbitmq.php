<?php
return [
    'default' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int)env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_NAME', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'heartbeat' => (int)env('RABBITMQ_HEARTBEAT', 60),
        'heartbeat_callback' => function () {
        },
        'prefix' => env('RABBITMQ_PREFIX', ''),
    ],
];
