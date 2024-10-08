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
namespace Thb\Rabbitmq;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Class RedisQueue
 * @package support
 *
 * Strings methods
 * @method static void send($queue, $data, $delay=0)
 */
class Client
{
    /**
     * @var Client[]
     */
    protected static $_connections = null;
    protected static $_name = 'default';


    /**
     * @param string $name
     * @param bool $consumer 是否是消费者
     * @return RabbitmqClient
     */
    public static function connection($name = 'default') {
        if (!isset(static::$_connections[$name])) {
            $config = config('plugin.thb.rabbitmq.rabbitmq', []);
            if (!isset($config[$name])) {
                throw new \RuntimeException("rabbitmq connection $name not found");
            }
            $client = new RabbitmqClient($config, $name);
            static::$_connections[$name] = $client;
            static::$_name = $name;
        }
        return static::$_connections[$name];
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
    public static function send(string $queue = '', array $data = [], int $delay = 0, string $name = 'default') : bool
    {
        $config = config('plugin.thb.rabbitmq.rabbitmq', []);
        $consumer = true;
        $client = new RabbitmqClient($config, $name, $consumer);

        $queue = $client->prefix . $queue;
        if(!isset($client->queueArr[$queue])){
            // 声明延迟队列
            $client->channel->queue_declare($queue, false, true, false, false);

            // 绑定队列到交换机
            $client->channel->queue_bind($queue, 'delayed_exchange', $queue);

            $client->queueArr[$queue] = $queue;
        }
        $now = time();
        $package_str = json_encode([
            'id'       => time().rand(),
            'time'     => $now,
            'delay'    => $delay,
            'attempts' => $attempts,
            'queue'    => $queue,
            'data'     => $data,
            'max_attempts' => $client->max_attempts,
            'retry_seconds' => $client->retry_seconds,
        ]);
        //消息json
        $messageBody = $package_str;
        //消息持久化
        $message = new AMQPMessage($messageBody, ['delivery_mode' => 2]);
        //延迟消息
        if($delay > 0){
            $message->set('application_headers', new AMQPTable(['x-delay' => $delay * 1000]));
        }
        // 发布消息到交换机
        $client->channel->basic_publish($message, 'delayed_exchange', $queue);

        $client->channel->wait_for_pending_acks_returns(5);

        $return = $client->return;

        $client->return = false;
        $client->close();
        return $return;
    }
    
    
    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        try {
            return static::connection('default')->{$name}(... $arguments);
        } catch (\PhpAmqpLib\Exception\AMQPConnectionClosedException $exception) {
//            var_dump($exception->getMessage());
//            var_dump($exception->getCode());
            //连接超时
            $client = new RabbitmqClient($config, static::$_name);
            static::$_connections[static::$_name] = $client;
            return $client->{$name}(... $arguments);
        }
    }
}
