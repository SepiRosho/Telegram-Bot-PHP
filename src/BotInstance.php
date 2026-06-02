<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Types\Update;

class BotInstance
{
    private TelegramApi $api;
    private Router $router;

    public function __construct(
        string $token,
        private array $config = [],
    ) {
        $this->api = new TelegramApi(new HttpClient($token));
        $this->router = new Router();
    }

    public function api(): TelegramApi
    {
        return $this->api;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Route registration — fluent, returns $this
    // -------------------------------------------------------------------------

    public function onCommand(string $command, callable|string $handler): static
    {
        $this->router->addRoute('command', $command, $handler);
        return $this;
    }

    public function onText(callable|string $handler): static
    {
        $this->router->addRoute('text', '*', $handler);
        return $this;
    }

    public function onMessage(callable|string $handler): static
    {
        $this->router->addRoute('message', '*', $handler);
        return $this;
    }

    public function onCallbackQuery(string|callable $patternOrHandler, callable|string|null $handler = null): static
    {
        if ($handler === null) {
            $this->router->addRoute('callback_query', '*', $patternOrHandler);
        } else {
            $this->router->addRoute('callback_query', (string) $patternOrHandler, $handler);
        }
        return $this;
    }

    public function onPhoto(callable|string $handler): static
    {
        $this->router->addRoute('photo', '*', $handler);
        return $this;
    }

    public function onDocument(callable|string $handler): static
    {
        $this->router->addRoute('document', '*', $handler);
        return $this;
    }

    public function onInlineQuery(callable|string $handler): static
    {
        $this->router->addRoute('inline_query', '*', $handler);
        return $this;
    }

    public function onUpdate(callable|string $handler): static
    {
        $this->router->addRoute('update', '*', $handler);
        return $this;
    }

    public function use(callable|string $middleware): static
    {
        $this->router->addMiddleware($middleware);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function run(): void
    {
        $input = file_get_contents('php://input');

        if (empty($input)) {
            throw new WebhookException('Empty webhook payload received.');
        }

        $data = json_decode($input, true);

        if (!is_array($data)) {
            throw new WebhookException('Invalid JSON in webhook payload.');
        }

        $update = Update::fromArray($data);
        $this->router->dispatch($update, $this->api, $this->config);
    }

    // Proxy any unknown call directly to TelegramApi
    public function __call(string $method, array $args): mixed
    {
        return $this->api->$method(...$args);
    }
}
