<?php
return [
    //默认redis队列配置
    'default' => [
        'host' => envs('RABBITMQ_HOST','127.0.0.1'),
        'port' => envs('RABBITMQ_PORT','5672'),
        'user' => envs('RABBITMQ_USER','thb'),
        'password' => envs('RABBITMQ_PASSWORD','thb910626'),
        'vhost' => envs('RABBITMQ_VHOST','/')
    ],
];
