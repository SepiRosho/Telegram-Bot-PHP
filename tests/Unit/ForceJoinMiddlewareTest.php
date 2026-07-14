<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Database\Models\BotSetting;
use Devflow\TelegramBot\Middleware\ForceJoinMiddleware;
use Devflow\TelegramBot\Testing\UpdateFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class ForceJoinMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('bot_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('bot_settings');
    }

    private function makeApi(string $memberStatus): TelegramApi
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturnCallback(function (string $method, array $params) use ($memberStatus) {
            if ($method === 'getChatMember') {
                return ['status' => $memberStatus, 'user' => ['id' => $params['user_id'], 'is_bot' => false, 'first_name' => 'Ali']];
            }
            return ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 100, 'type' => 'private']];
        });
        return new TelegramApi($http);
    }

    public function test_blocks_and_prompts_when_user_has_not_joined(): void
    {
        $middleware = new ForceJoinMiddleware(['@my_channel']);
        $ctx = new Context(UpdateFactory::command('start'), $this->makeApi('left'));
        $nextCalled = false;

        $middleware->handle($ctx, function () use (&$nextCalled) { $nextCalled = true; });

        $this->assertFalse($nextCalled);
    }

    public function test_allows_through_when_user_has_joined(): void
    {
        $middleware = new ForceJoinMiddleware(['@my_channel']);
        $ctx = new Context(UpdateFactory::command('start'), $this->makeApi('member'));
        $nextCalled = false;

        $middleware->handle($ctx, function () use (&$nextCalled) { $nextCalled = true; });

        $this->assertTrue($nextCalled);
    }

    public function test_caches_membership_check_within_ttl(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $calls = 0;
        $http->method('post')->willReturnCallback(function (string $method, array $params) use (&$calls) {
            if ($method === 'getChatMember') {
                $calls++;
                return ['status' => 'member', 'user' => ['id' => $params['user_id'], 'is_bot' => false, 'first_name' => 'Ali']];
            }
            return ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 100, 'type' => 'private']];
        });
        $api = new TelegramApi($http);

        $middleware = new ForceJoinMiddleware(['@my_channel'], cacheTtl: 60);

        $middleware->handle(new Context(UpdateFactory::command('start'), $api), function () {});
        $middleware->handle(new Context(UpdateFactory::command('help'), $api), function () {});

        $this->assertSame(1, $calls);
    }

    public function test_verify_callback_re_checks_and_clears_cache(): void
    {
        $middleware = new ForceJoinMiddleware(['@my_channel']);

        // First: not joined, cached as such.
        $ctx1 = new Context(UpdateFactory::callback('force_join_verify'), $this->makeApi('left'));
        $middleware->handle($ctx1, function () {});

        // Now the user has actually joined; verifyCallback must re-check
        // rather than trusting the cached "not joined" result.
        $ctx2 = new Context(UpdateFactory::callback('force_join_verify'), $this->makeApi('member'));
        ($middleware->verifyCallback())($ctx2);

        $nextCalled = false;
        $ctx3 = new Context(UpdateFactory::command('start'), $this->makeApi('member'));
        $middleware->handle($ctx3, function () use (&$nextCalled) { $nextCalled = true; });

        $this->assertTrue($nextCalled);
    }
}
