<?php
return [
    'consumer' => [
        'handler' => Thb\Rabbitmq\Process\Consumer::class,
        'count' => 4,
        // workerman/rabbitmq 协程客户端需 Fiber 事件循环
        'eventLoop' => \Workerman\Events\Fiber::class,
        'constructor' => [
            // 消费者类目录（递归扫描，须继承 Thb\Rabbitmq\Consumer）
            'consumer_dir' => app_path() . '/queue/rabbitmq',
            // 可选：只消费该连接；空=目录内全部（多连接时仅启动第一个）
            'connection' => 'default',
        ],
    ],
];
