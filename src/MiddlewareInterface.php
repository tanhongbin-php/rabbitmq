<?php

namespace Thb\Rabbitmq;

interface MiddlewareInterface
{
    public function process(array $request, callable $next): array;
}
