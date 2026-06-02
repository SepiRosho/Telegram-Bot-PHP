<?php

namespace Devflow\TelegramBot\Routing;

use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;
use Devflow\TelegramBot\Types\Update;

class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function addRoute(string $type, string $pattern, callable|string $handler): void
    {
        $this->routes[] = new Route($type, $pattern, $handler);
    }

    public function addMiddleware(callable|string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(Update $update, TelegramApi $api, array $config = []): void
    {
        foreach ($this->routes as $route) {
            if ($this->matches($route, $update)) {
                $ctx = new Context($update, $api, $config);
                $this->runWithMiddleware($ctx, $route->handler);
                return;
            }
        }
    }

    private function matches(Route $route, Update $update): bool
    {
        return match ($route->type) {
            'command' => $this->matchesCommand($route->pattern, $update),
            'text' => $update->message?->text !== null && !$update->message->isCommand(),
            'message' => $update->message !== null,
            'callback_query' => $update->callbackQuery !== null
                && $this->matchesPattern($route->pattern, $update->callbackQuery->data ?? ''),
            'photo' => $update->message?->photo !== null,
            'document' => $update->message?->document !== null,
            'inline_query' => $update->inlineQuery !== null,
            'update' => true,
            default => false,
        };
    }

    private function matchesCommand(string $command, Update $update): bool
    {
        if ($update->message === null || !$update->message->isCommand()) {
            return false;
        }
        if ($command === '*') {
            return true;
        }
        return $update->message->command() === ltrim($command, '/');
    }

    private function matchesPattern(string $pattern, string $value): bool
    {
        if ($pattern === '*') {
            return true;
        }
        if (!str_contains($pattern, '*')) {
            return $pattern === $value;
        }
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $value);
    }

    private function runWithMiddleware(Context $ctx, callable|string $handler): void
    {
        $runner = fn(Context $ctx) => $this->callHandler($handler, $ctx);

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = $runner;
            $runner = fn(Context $ctx) => $this->callMiddleware($middleware, $ctx, $next);
        }

        $runner($ctx);
    }

    private function callHandler(callable|string $handler, Context $ctx): void
    {
        if (is_string($handler)) {
            $instance = new $handler();
            if ($instance instanceof HandlerInterface) {
                $instance->handle($ctx);
                return;
            }
        }

        ($handler)($ctx);
    }

    private function callMiddleware(callable|string $middleware, Context $ctx, callable $next): void
    {
        if (is_string($middleware)) {
            (new $middleware())->handle($ctx, $next);
            return;
        }

        ($middleware)($ctx, $next);
    }
}
