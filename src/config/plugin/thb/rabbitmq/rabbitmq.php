<?php
return [
    //默认redis队列配置
    'default' => [
        'host' => '127.0.0.1',
        'port' => '5672',
        'user' => 'thb',
        'password' => 'thb910626',
        'vhost' => '/',
        'options' => [
            'prefix' => '',       // key 前缀
            'max_attempts'  => 3, // 消费失败后，重试次数
            'retry_seconds' => 5, // 重试间隔，单位秒
        ]
    ],
];
