<?php
declare(strict_types=1);

namespace Thb\Rabbitmq\Process;

use Bunny\AbstractClient;
use Bunny\Channel;
use Bunny\Message;
use support\Container;
use support\Context;
use Thb\Rabbitmq\Consumer as BaseConsumer;
use Workerman\RabbitMQ\Client;

/**
 * 扫描消费者目录，订阅 RabbitMQ 队列
 * 消费者类须继承 Thb\Rabbitmq\Consumer 并实现 consume
 */
class Consumer
{
    protected string $consumerDir = '';

    /** 仅加载该连接的消费者；空=不限制（多连接时仅启动第一个） */
    protected string $connectionFilter = '';

    public function __construct(string $consumer_dir = '', string $connection = '')
    {
        $this->consumerDir = $consumer_dir;
        $this->connectionFilter = $connection;
    }

    public function onWorkerStart(): void
    {
        if (!is_dir($this->consumerDir)) {
            echo "Consumer directory {$this->consumerDir} not exists\r\n";
            return;
        }

        $byConnection = [];
        $dirIterator = new \RecursiveDirectoryIterator($this->consumerDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // Windows 下统一路径分隔符，避免类名解析失败
            $base = rtrim(str_replace('\\', '/', base_path()), '/');
            $path = str_replace('\\', '/', $file->getPathname());
            if (!str_starts_with($path, $base . '/')) {
                continue;
            }
            $class = str_replace('/', '\\', substr($path, strlen($base) + 1, -4));
            if ($class === '' || !class_exists($class) || !is_a($class, BaseConsumer::class, true)) {
                continue;
            }
            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            /** @var BaseConsumer $consumer */
            $consumer = Container::get($class);
            $queue = trim((string)($consumer->queue ?? ''));
            if ($queue === '') {
                echo "Consumer {$class} queue not exists\r\n";
                continue;
            }
            $connection = (string)($consumer->connection ?? 'default');
            if ($this->connectionFilter !== '' && $connection !== $this->connectionFilter) {
                continue;
            }
            $prefix = (string)config('plugin.thb.rabbitmq.rabbitmq.' . $connection . '.prefix', '');
            $fullQueue = $prefix . $queue;
            $byConnection[$connection][$fullQueue] = $consumer;
        }

        if ($byConnection === []) {
            echo "No RabbitMQ consumer found in {$this->consumerDir}\r\n";
            return;
        }

        if (count($byConnection) > 1) {
            $names = implode(', ', array_keys($byConnection));
            echo "Warning: multiple RabbitMQ connections [{$names}] in one process; only the first will run. Split consumer_dir or set constructor connection.\r\n";
        }

        $connection = (string)array_key_first($byConnection);
        $queueMap = $byConnection[$connection];
        $config = config('plugin.thb.rabbitmq.rabbitmq.' . $connection, []);
        if ($config === []) {
            echo "RabbitMQ connection [{$connection}] not found\r\n";
            return;
        }

        $client = Client::factory($config)->connect();
        $channel = $client->channel();
        foreach ($queueMap as $fullQueue => $consumer) {
            $channel->queueDeclare($fullQueue, false, true, false, false, false, []);
            $channel->consume(function (Message $message, Channel $channel, AbstractClient $client) use ($consumer) {
                try {
                    $data = json_decode($message->content, true);
                    $consumer->consume($data);
                    $channel->ack($message);
                } catch (\Throwable $e) {
                    try {
                        $channel->nack($message, false, true);
                    } catch (\Throwable $ee) {
                    }
                    throw $e;
                } finally {
                    Context::destroy();
                }
            }, $fullQueue);
        }
        $client->run();
    }
}
