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
    public static function connection($name = 'default', $consumer = false) {
        if (!isset(static::$_connections[$name])) {
            $config = config('plugin.thb.rabbitmq.rabbitmq', []);
            if (!isset($config[$name])) {
                throw new \RuntimeException("rabbitmq connection $name not found");
            }
            $client = new RabbitmqClient($config, $name, $consumer);
            static::$_connections[$name] = $client;
        }
        return static::$_connections[$name];
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
