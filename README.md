# thb/rabbitmq

Webman RabbitMQ 插件（基于 `workerman/rabbitmq` + `revolt/event-loop`）。

## 安装

```bash
composer require thb/rabbitmq
```

会自动安装依赖：`workerman/rabbitmq`、`revolt/event-loop`，并复制配置到 `config/plugin/thb/rabbitmq`。

## 入队

```php
use Thb\Rabbitmq\Client;

// 立即投递
Client::send('fast_queue', ['id' => 1]);

// 延迟 10 秒
Client::send('fast_queue', ['id' => 1], 10);

// 指定连接
Client::connection('default')->push('fast_queue', ['id' => 1]);
```

## 消费

在 `app/queue/rabbitmq` 下新建类，**必须继承** `Thb\Rabbitmq\Consumer`，并实现 `consume`：

```php
<?php
namespace app\queue\rabbitmq\fast;

use Thb\Rabbitmq\Consumer;

class Demo extends Consumer
{
    public string $queue = 'fast_queue';
    public string $connection = 'default';

    public function consume($data)
    {
        // 业务逻辑
        var_export($data);
    }
}
```

进程配置见 `config/plugin/thb/rabbitmq/process.php`（需 `Fiber` 事件循环）。
