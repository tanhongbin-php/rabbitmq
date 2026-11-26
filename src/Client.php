<?php
declare(strict_types=1);

namespace Thb\Rabbitmq;

use Workerman\RabbitMQ\Client as RabbitClient;

/**
 * RabbitMQ 入队客户端
 *
 * 用法：
 *   Client::send('fast_queue', ['id' => 1]);
 *   Client::send('fast_queue', ['id' => 1], 10); // 延迟 10 秒
 *   Client::connection('default')->send('fast_queue', ['id' => 1]);
 */
class Client
{
    /** @var array<string, RabbitClient|null> */
    protected static array $clients = [];

    /** @var array<string, mixed> */
    protected static array $channels = [];

    /**
     * 进程内已声明队列缓存：connection => [cacheKey => true]
     * @var array<string, array<string, true>>
     */
    protected static array $declared = [];

    protected string $connection;

    public function __construct(string $connection = 'default')
    {
        $this->connection = $connection;
    }

    public static function connection(string $name = 'default'): self
    {
        return new static($name);
    }

    /**
     * @param string $queueName 队列名（会自动加 prefix）
     * @param array $body 消息体
     * @param int $delay 延迟秒数，0 为立即投递
     * @param string $connection 连接名（静态调用时可用）
     */
    public static function send(string $queueName = '', array $body = [], int $delay = 0, string $connection = 'default'): bool
    {
        return (new static($connection))->push($queueName, $body, $delay);
    }

    /**
     * 实例方法入队
     */
    public function push(string $queueName = '', array $body = [], int $delay = 0): bool
    {
        $prefix = (string)config('plugin.thb.rabbitmq.rabbitmq.' . $this->connection . '.prefix', '');
        $queueName = $prefix . $queueName;
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $channel = $this->channel();
                $headers = [
                    'delivery_mode' => 2, // 持久化
                ];
                $queueArguments = [];
                $targetQueue = $queueName;

                if ($delay > 0) {
                    // 延迟：TTL + DLX 投回原队列
                    $queueArguments = [
                        'x-dead-letter-exchange' => '',
                        'x-dead-letter-routing-key' => $queueName,
                    ];
                    $targetQueue = 'retry_delayed_' . $queueName;
                    $headers['expiration'] = (string)($delay * 1000);
                    // 确保死信目标队列存在
                    $this->ensureQueue($channel, $queueName, []);
                }

                $this->ensureQueue($channel, $targetQueue, $queueArguments);
                $res = $channel->publish($payload, $headers, '', $targetQueue);
                return (bool)$res;
            } catch (\Throwable $e) {
                $this->reset();
                usleep(100 * 1000);
            }
        }
        return false;
    }

    /**
     * 进程内缓存 queueDeclare，同连接同参数只声明一次
     *
     * @param mixed $channel
     * @param array<string, mixed> $arguments
     */
    protected function ensureQueue($channel, string $queue, array $arguments = []): void
    {
        $name = $this->connection;
        $cacheKey = $queue . "\0" . md5(serialize($arguments));
        if (!empty(static::$declared[$name][$cacheKey])) {
            return;
        }
        $channel->queueDeclare($queue, false, true, false, false, false, $arguments);
        static::$declared[$name][$cacheKey] = true;
    }

    protected function channel()
    {
        $name = $this->connection;
        if (empty(static::$clients[$name])) {
            $config = config('plugin.thb.rabbitmq.rabbitmq.' . $name, []);
            if ($config === []) {
                throw new \RuntimeException("RabbitMQ connection [{$name}] not found");
            }
            static::$clients[$name] = RabbitClient::factory($config)->connect();
        }
        if (empty(static::$channels[$name])) {
            static::$channels[$name] = static::$clients[$name]->channel();
        }
        return static::$channels[$name];
    }

    protected function reset(): void
    {
        $name = $this->connection;
        try {
            if (!empty(static::$channels[$name])) {
                static::$channels[$name]->close();
            }
        } catch (\Throwable $e) {
        }
        try {
            if (!empty(static::$clients[$name])) {
                static::$clients[$name]->disconnect();
            }
        } catch (\Throwable $e) {
        }
        static::$channels[$name] = null;
        static::$clients[$name] = null;
        // channel 重建后需重新 declare
        unset(static::$declared[$name]);
    }
}
