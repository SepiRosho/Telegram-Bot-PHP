<?php

namespace Devflow\TelegramBot\Testing;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\Assert;

/**
 * In-memory bot for tests: wraps a BotInstance wired to a FakeHttpClient
 * (no real network calls) and a FakeUserRepository (no real database).
 *
 * Created via Bot::fake() — which also swaps the static Bot facade's
 * instance, so production handler code using Bot::onCommand()/etc. inside a
 * register() method routes to this fake instance unmodified.
 */
class FakeBot
{
    private BotInstance $instance;
    private FakeHttpClient $http;
    private FakeUserRepository $users;

    public function __construct(string $token = 'fake-token', array $config = [])
    {
        $this->http = new FakeHttpClient();
        $this->users = new FakeUserRepository();
        $this->instance = new BotInstance($token, $config, $this->http);
    }

    public function instance(): BotInstance
    {
        return $this->instance;
    }

    public function http(): FakeHttpClient
    {
        return $this->http;
    }

    public function users(): FakeUserRepository
    {
        return $this->users;
    }

    /** Run an update through the router, same as a real webhook dispatch. */
    public function dispatch(Update $update): void
    {
        $useDb = $this->instance->config('database') ?? true;

        $this->instance->router()->dispatch(
            $update,
            $this->instance->api(),
            [],
            $useDb ? $this->users : null,
        );
    }

    public function calls(): array
    {
        return $this->http->calls();
    }

    public function assertSent(string $method, ?callable $callback = null): void
    {
        $calls = $this->http->callsTo($method);
        Assert::assertNotEmpty($calls, "Expected [{$method}] to have been sent, but it was not.");

        if ($callback !== null) {
            $matched = array_filter($calls, fn(array $c) => $callback($c['params']));
            Assert::assertNotEmpty($matched, "Expected [{$method}] to have been sent matching the given condition.");
        }
    }

    public function assertNotSent(string $method): void
    {
        Assert::assertEmpty($this->http->callsTo($method), "Expected [{$method}] not to have been sent, but it was.");
    }

    /** Proxy route registration (onCommand, onText, ...) straight to BotInstance. */
    public function __call(string $method, array $args): mixed
    {
        return $this->instance->$method(...$args);
    }
}
