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

    protected $_middleware = [];

    protected $enable = false;

    /**
     * StompConsumer constructor.
     * @param string $consumer_dir
     */
    public function __construct($consumer_dir = '', $middleware = [])
    {
        $this->_consumerDir = $consumer_dir;
        $this->_middleware = $middleware;
    }

    /**
     * onWorkerStart.
     */
    public function onWorkerStart()
    {
        if(DIRECTORY_SEPARATOR === '/'){
            pcntl_signal(SIGINT, function(){
                Worker::stopAll();
            });
        }

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
                $middleware = config('plugin.thb.rabbitmq.rabbitmq.' . $connection_name . '.middleware', []);
                $middlewares = array_merge($middleware, $this->_middleware);
                $connection->consumer($queue, function(AMQPMessage $message) use ($connection, $queue, $consumer, $middlewares) {
                    $package = json_decode($message->getBody(), true);
                    try {
                        // 使用示例
                        $rabbitmqMidd = Container::get('Thb\Rabbitmq\Rabbitmqlication');
                        if(!$this->enable){
                            $this->enable = true;
                            foreach ($middlewares as $middleware) {
                                if(is_string($middleware) && !class_exists($middleware)){
                                    continue;
                                }
                                if(is_string($middleware)){
                                    $rabbitmqMidd->use(new $middleware); // 添加中间件
                                }else{
                                    $rabbitmqMidd->use($middleware); // 添加中间件
                                }
                            }
                        }
                        $rabbitmqMidd ->handle($package, function() use($consumer, $package) {
                            try {
                                return \call_user_func([$consumer, 'consume'], $package['data']);
                            } catch (BusinessException $exception) {
                                return $exception;
                            }  catch (\Throwable $exception) {
                                $package['error'] = ['errMessage'=>$exception->getMessage(),'errCode'=>$exception->getCode(),'errFile'=>$exception->getFile(),'errLine'=>$exception->getLine()];
                                //重试超过最大次数,放入失败队列
                                if($package['max_attempts'] == 0 || ($package['max_attempts'] > 0 && $package['attempts'] >= $package['max_attempts'])){
                                    $connection->sendAsyn('rabbitmq_fail', $package);
                                }else{
                                    $package['attempts']++;
                                    $dela = $package['attempts'] * $package['retry_seconds'];
                                    $connection->sendAsyn($queue, $package['data'], $dela, $package['attempts']);
                                }
                                return $exception;
                            }
                        });
                    } catch (\Throwable $exception) {
                        $package['error'] = ['errMessage'=>$exception->getMessage(),'errCode'=>$exception->getCode(),'errFile'=>$exception->getFile(),'errLine'=>$exception->getLine()];
                        $connection->sendAsyn('rabbitmq_fail', $package);
                        Log::channel('plugin.thb.rabbitmq.default')->info((string)$exception);
                    }
                    $message->ack();
                });
                $connection->close();
            }
        }
    }
}
