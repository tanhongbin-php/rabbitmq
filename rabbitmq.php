<?php
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'heartbeat' => 60,
        'heartbeat_callback' => function () {
        },
        'prefix' => '',
    ],
];
