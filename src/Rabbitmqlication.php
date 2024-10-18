<?php

namespace Thb\Rabbitmq;
/**
 * Rpc.
 *
 * This middleware
 *
 * @author thb
 * @date 2024/10/16
 */
class Rabbitmqlication {
    public $middleware = [];

    public function use(object $middleware) {
        $this->middleware[] = $middleware;
    }

    public function handle(array $request, callable $func) {
        // 创建一个闭包来处理中间件链
        $next = function($request) use ($func) {
            return $func();
        };

        // 反向遍历中间件并执行
        foreach (array_reverse($this->middleware) as $middleware) {
            $next = function($request) use ($middleware, $next) {
                return $middleware->process($request, $next);
            };
        }

        return $next($request);
    }
}
