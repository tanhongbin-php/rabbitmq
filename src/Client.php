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
        $client = new RabbitmqClient($config, $name);
        $return = $client->sendAsyn($queue, $data, $delay);
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
        return static::connection('default')->{$name}(... $arguments);
    }
}
