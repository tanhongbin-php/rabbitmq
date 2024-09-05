<?php
declare(strict_types=1);
/**
 * rabbitmq队列
 * 长连接
 * User: thb
 * Date: 2024/9/4
 */

namespace Thb\Rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitmqClient
{
    private $connection = null;
    private $channel = null;
    public function __construct($host, $port, $user, $password, $vhost){
        if(!$this->connection){
            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost,
                false,
                'AMQPLAIN',
                null,
                'en_US',
                3.0,
                6.0,
                null,
                true,
                30
            );
        }
        if(!$this->channel){
            $this->channel = $this->connection->channel();
            // 声明一个具有延迟插件的自定义交换机
            $args = new AMQPTable([
                'x-delayed-type' => \PhpAmqpLib\Exchange\AMQPExchangeType::DIRECT // 这里假设我们使用 direct 类型的交换机
            ]);

            //第4个参数设置为true，表示让消息队列持久化
            $this->channel->exchange_declare('delayed_exchange', 'x-delayed-message', false, true, false, false, false, $args);
        }
    }

    /**
     * 数据入队
     * @param $queue   string    队列名称
     * @param $data array  入队数据
     * @param $delay int  延迟
     * @return bool
     * @Datetime: 2024/09/04
     * @Username: thb
     */
    public function send(string $queue = '', array $data = [], int $delay = 0) : bool
    {
        // 声明延迟队列
        $this->channel->queue_declare($queue, false, true, false, false);

        // 绑定队列到交换机
        $this->channel->queue_bind($queue, 'delayed_exchange', $queue);

        $messageBody = json_encode($data);

        $message = new AMQPMessage($messageBody, ['delivery_mode' => 2]);

        if($delay > 0){
            $message->set('application_headers', new AMQPTable(['x-delay' => $delay * 1000]));
        }

        // 发布消息到交换机
        $this->channel->basic_publish($message, 'delayed_exchange', $queue);
        return true;
    }
    /**
     * 消费
     * @param $queue   string    队列名称
     * @param $callback callable  回调闭包
     * @Datetime: 2024/09/05
     * @Username: thb
     */
    public function consume(string $queue,callable $callback)
    {
        // 声明延迟队列
        $this->channel->queue_declare($queue, false, true, false, false);

        // 绑定队列到交换机
        $this->channel->queue_bind($queue, 'delayed_exchange', $queue);

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
            pcntl_signal_dispatch();
        }
    }

    public function close(){
        $this->channel->close();
        $this->connection->close();
    }
}