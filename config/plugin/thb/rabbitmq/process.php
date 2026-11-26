<?php
return [
    'consumer' => [
        'handler' => Thb\Rabbitmq\Process\Consumer::class,
        'count' => 4,
        // 同步 Bunny 自带 stream_select 阻塞循环，无需 Fiber
        'constructor' => [
            // 消费者类目录（递归扫描，须继承 Thb\Rabbitmq\Consumer）
            'consumer_dir' => app_path() . '/queue/rabbitmq',
            // 可选：只消费该连接；空=目录内全部（多连接时仅启动第一个）
            'connection' => 'default',
            // prefetch=1：处理完一条再取下一条
            'prefetch' => 1,
        ],
    ],
];
