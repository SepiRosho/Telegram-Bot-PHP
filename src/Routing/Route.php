<?php

namespace Devflow\TelegramBot\Routing;

class Route
{
    public function __construct(
        public readonly string $type,
        public readonly string $pattern,
        public readonly mixed $handler,
        public readonly array $types = ['text'],
        public readonly array $middleware = [],
    ) {}
}
