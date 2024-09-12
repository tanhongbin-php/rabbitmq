<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'default' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/rabbitmq-queue/queue.log',
                    7, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
    'rabbitmq_queue' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,//app\api\logs\MyRotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/queue/rabbitmq/rabbitmq_queue.log',
                    7, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
    'rabbitmq_consumer_error' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,//app\api\logs\MyRotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/queue/rabbitmq/rabbitmq_consumer_error.log',
                    7, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
    'rabbitmq_queue_error' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,//app\api\logs\MyRotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/queue/rabbitmq/rabbitmq_queue_error.log',
                    7, //$maxFiles
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
];
