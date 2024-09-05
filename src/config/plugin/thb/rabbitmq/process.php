<?php

return [
    'rabbitmq'  => [
        'handler' => Thb\Rabbitmq\Process\Consumer::class,
        // 进程数 （可选，默认1）
        'count' => 2,
        // 进程类构造函数参数，这里为 process\Pusher::class 类的构造函数参数 （可选）
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => app_path() . '/queue/rabbitmq/Ceshi.php'
        ],
    ],
];