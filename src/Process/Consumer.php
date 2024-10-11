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

namespace Thb\Rabbitmq\Process;

use PhpAmqpLib\Message\AMQPMessage;
use support\Container;
use support\exception\BusinessException;
use support\Log;
use Thb\Rabbitmq\Client;
use Webman\Event\Event;
use Workerman\Worker;

/**
 * Class Consumer
 * @package process
 */
class Consumer
{
    /**
     * @var string
     */
    protected $_consumerDir = '';

    /**
     * @var array
     */
    protected $_consumers = [];

    protected $reconnectDelay = 10;

    /**
     * StompConsumer constructor.
     * @param string $consumer_dir
     */
    public function __construct($consumer_dir = '')
    {
        $this->_consumerDir = $consumer_dir;
    }

    /**
     * onWorkerStart.
     */
    public function onWorkerStart()
    {
        if (!file_exists($this->_consumerDir)) {
            echo "Consumer directory {$this->_consumerDir} not exists\r\n";
            return false;
        }
        $fileinfo = new \SplFileInfo($this->_consumerDir);
        $ext = $fileinfo->getExtension();
        if ($ext === 'php') {
            $class = str_replace('/', "\\", substr(substr($this->_consumerDir, strlen(base_path())), 0, -4));
            if (is_a($class, 'Thb\Rabbitmq\Consumer', true)) {
                $consumer = Container::get($class);
                $connection_name = $consumer->connection ?? 'default';
                $queue = $consumer->queue;
                if (!$queue) {
                    echo "Consumer {$class} queue not exists\r\n";
                    return false;
                }
                $connection = Client::connection($connection_name);
                $connection->consumer($queue, function(AMQPMessage $message) use ($connection, $queue, $consumer) {
                    $package = json_decode($message->getBody(), true);
                    try {
                        Event::emit('queue.dbListen', $package);
                        call_user_func([$consumer, 'consume'], $package['data']);
                        Event::emit('queue.log', ['type' => 'rabbitmq']);
                    } catch (BusinessException $e) {
                        $package['error'] = ['errMessage'=>$exception->getMessage(),'errCode'=>$exception->getCode()];
                        $package['type'] = 'rabbitmq';
                        try {
                            Event::emit('queue.exCep', $package);
                        } catch (\Throwable $ta) {
                            Log::channel('plugin.thb.rabbitmq.default')->info((string)$ta);
                        }
                        call_user_func([$consumer, 'onConsumeFailure'], $exception, $package);
                    } catch (\Throwable $exception) {
                        $package['error'] = ['errMessage'=>$exception->getMessage(),'errCode'=>$exception->getCode(),'errFile'=>$exception->getFile(),'errLine'=>$exception->getLine()];
                        $package['type'] = 'rabbitmq';
                        try {
                            Event::emit('queue.exCep', $package);
                        } catch (\Throwable $ta) {
                            Log::channel('plugin.thb.rabbitmq.default')->info((string)$ta);
                        }
                        //重试超过最大次数,放入失败队列
                        if($package['max_attempts'] == 0 || ($package['max_attempts'] > 0 && $package['attempts'] >= $package['max_attempts'])){
                            $connection->sendAsyn('rabbitmq_fail', $package);
                        }else{
                            $package['attempts']++;
                            $dela = $package['attempts'] * $package['retry_seconds'];
                            $connection->sendAsyn($queue, $package['data'], $dela, $package['attempts']);
                        }
                    }
                    $message->ack();
                });
                $connection->close();
            }
        }
    }
}
