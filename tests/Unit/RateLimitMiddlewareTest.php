<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Middleware\RateLimitMiddleware;
use Devflow\TelegramBot\Testing\FakeUserRepository;
use Devflow\TelegramBot\Testing\UpdateFactory;
use PHPUnit\Framework\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();
    }

    private function makeContext(FakeHttpClient $http, FakeUserRepository $users): Context
    {
        $update = UpdateFactory::text('hello');
        $ctx = new Context($update, new TelegramApi($http));
        $ctx->setUserRepository($users);

        return $ctx;
    }

    public function test_blocks_once_the_window_is_full(): void
    {
        $http = new FakeHttpClient();
        $users = new FakeUserRepository();
        $middleware = new RateLimitMiddleware(maxHits: 2, windowSeconds: 60);
        $passed = 0;

        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($this->makeContext($http, $users), function () use (&$passed) { $passed++; });
        }

        $this->assertSame(2, $passed);
        $this->assertCount(1, $http->callsTo('sendMessage'));
        $this->assertSame('Too many requests. Please slow down.', $http->callsTo('sendMessage')[0]['params']['text']);
    }

    public function test_closure_message_is_resolved_per_request_so_it_can_be_localized(): void
    {
        // The constructor runs at registration time, long before any Context
        // exists — a plain string can never be locale-aware.
        $http = new FakeHttpClient();
        $users = new FakeUserRepository();
        $middleware = new RateLimitMiddleware(
            maxHits: 1,
            windowSeconds: 60,
            message: fn(Context $ctx) => 'slow down, ' . $ctx->from()?->firstName,
        );

        $middleware->handle($this->makeContext($http, $users), fn() => null);
        $middleware->handle($this->makeContext($http, $users), fn() => null);

        $this->assertSame('slow down, Test', $http->callsTo('sendMessage')[0]['params']['text']);
    }

    public function test_passes_through_when_there_is_no_database_user(): void
    {
        $http = new FakeHttpClient();
        $nextCalled = false;
        $middleware = new RateLimitMiddleware(maxHits: 0);

        $middleware->handle(new Context(UpdateFactory::text('hello'), new TelegramApi($http)), function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }
}
