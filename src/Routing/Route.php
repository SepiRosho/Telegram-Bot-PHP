<?php

namespace Devflow\TelegramBot\Routing;

class Route
{
    /**
     * @param ?array $chatTypes Chat types this route accepts, overriding the
     *                          global `allowed_chat_types` config. null means
     *                          "inherit the global setting"; ['*'] means "any
     *                          chat", which is how a single group-only route
     *                          escapes an otherwise private-only bot.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $pattern,
        public readonly mixed $handler,
        public readonly array $types = ['text'],
        public readonly array $middleware = [],
        public readonly ?array $chatTypes = null,
    ) {}
}
