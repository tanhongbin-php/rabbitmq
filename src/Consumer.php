<?php
declare(strict_types=1);

namespace Thb\Rabbitmq;

/**
 * RabbitMQ 消费者基类
 * 业务消费者必须继承本类，并实现 consume 方法
 */
abstract class Consumer
{
    /** 队列名（不含 prefix，进程会自动加配置前缀） */
    public string $queue = '';

    /** 连接名，对应 config/plugin/thb/rabbitmq/rabbitmq.php */
    public string $connection = 'default';

    /**
     * 消费消息
     * @param mixed $data json_decode 后的消息体
     */
    abstract public function consume($data);
}
