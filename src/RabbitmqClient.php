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
use support\Log;

class RabbitmqClient
{
    protected $config = [];
    protected $name;
    public $connection;
    public $channel;
    public $return = false;
    public $prefix = '';
    public $max_attempts = 0;
    public $retry_seconds = 5;
    public function __construct(array $config, string $name){
        $this->config = $config;

        $this->name = $name;
        //初始化
        $this->connect();
        //前缀
        $this->prefix = $config[$name]['options']['prefix'] ?? '';
        //最大重试次数
        $this->max_attempts = $config[$name]['options']['max_attempts'] ?? 0;
        //重试间隔
        $this->retry_seconds = $config[$name]['options']['retry_seconds'] ?? 5;
    }

    /**
     * 初始化数据
     * @Datetime: 2024/09/06
     * @Username: thb
     */
    protected function connect(){
        $host = $this->config[$this->name]['host'];
        $port = $this->config[$this->name]['port'];
        $user = $this->config[$this->name]['user'];
        $password = $this->config[$this->name]['password'];
        $vhost = $this->config[$this->name]['vhost'];
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
            3.0,
            null,
            true,
            60
        );

        $this->channel = $this->connection->channel();
        // 声明一个具有延迟插件的自定义交换机
        $args = new AMQPTable([
            'x-delayed-type' => \PhpAmqpLib\Exchange\AMQPExchangeType::DIRECT // 这里假设我们使用 direct 类型的交换机
        ]);

        //第4个参数设置为true，表示让消息队列持久化
        $this->channel->exchange_declare('delayed_exchange', 'x-delayed-message', false, true, false, false, false, $args);

        $this->channel->confirm_select();//open confirm
        //ack callback function
        $this->channel->set_ack_handler(function (AMQPMessage $message){
            $this->return = true;
        });
        //nack callback function
        $this->channel->set_nack_handler(function (AMQPMessage $message){
            $this->return = false;
            Log::channel('plugin.thb.rabbitmq.rabbitmq_queue_error')->info($message->getRoutingKey(),[$message->getBody()]);
        });
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
    public function sendAsyn(string $queue = '', array $data = [], int $delay = 0, $attempts = 0) : bool
    {
        $now = time();
        //消息json
        if($queue == 'rabbitmq_fail' && isset($data['data']) && isset($data['error'])){
            $messageBody = json_encode($data);
        }else{
            $messageBody = json_encode([
                'id'       => time().rand(),
                'time'     => $now,
                'delay'    => $delay,
                'attempts' => $attempts,
                'queue'    => $queue,
                'data'     => $data,
                'max_attempts' => $this->max_attempts,
                'retry_seconds' => $this->retry_seconds,
            ]);
        }
        $queue = $this->prefix . $queue;

        $num = 2;//断线重试次数

        for($i = 0; $i <= $num; $i++){
            try{
                // 声明延迟队列
                $this->channel->queue_declare($queue, false, true, false, false);

                // 绑定队列到交换机
                $this->channel->queue_bind($queue, 'delayed_exchange', $queue);

                //消息持久化
                $message = new AMQPMessage($messageBody, ['delivery_mode' => 2]);
                //延迟消息
                if($delay > 0){
                    $message->set('application_headers', new AMQPTable(['x-delay' => $delay * 1000]));
                }
                // 发布消息到交换机
                $this->channel->basic_publish($message, 'delayed_exchange', $queue);

                $this->channel->wait_for_pending_acks_returns(5);

                $return = $this->return;

                $this->return = false;

                return $return;
            } catch (\Throwable $exception) {
                if($i < $num){
                    $this->connect();
                }
                $i++;
                continue;
            }
        }
        throw new \PhpAmqpLib\Exception\AMQPConnectionClosedException('Channel connection is closed.', 500);
    }
    /**
     * 消费
     * @param $queue   string    队列名称
     * @param $callback callable  回调闭包
     * @Datetime: 2024/09/05
     * @Username: thb
     */
    public function consumer(string $queue,callable $callback)
    {
        $queue = $this->prefix . $queue;
        // 声明延迟队列
        $this->channel->queue_declare($queue, false, true, false, false);

        // 绑定队列到交换机
        $this->channel->queue_bind($queue, 'delayed_exchange', $queue);

        $this->channel->basic_qos(0, 1, false);

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        register_shutdown_function(function(){
            $this->close();
        });

        $this->channel->consume();
    }

    public function getMessageCount(string $queue) : int
    {
        $queue = $this->prefix . $queue;
        $queue_declare_result = $this->channel->queue_declare($queue, false, true, false, false);
        return $queue_declare_result[1];
    }

    public function getMessageData(string $queue) : string|bool
    {
        $queue = $this->prefix . $queue;
        $this->channel->queue_declare($queue, false, true, false, false);
        // 获取消息
        $msg = $this->channel->basic_get($queue, true); // 第二个参数为false会获取但不删除消息，为true则获取并删除消息
        if ($msg !== false) {
            $message = $msg->body;
            return $message;
        } else {
            return $msg;
        }
    }

    public function close(){
        $this->channel->close();
        $this->connection->close();
    }
}