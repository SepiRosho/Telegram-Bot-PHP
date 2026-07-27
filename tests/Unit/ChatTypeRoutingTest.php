<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class ChatTypeRoutingTest extends TestCase
{
    private function makeApi(): TelegramApi
    {
        return new TelegramApi($this->createMock(HttpClientInterface::class));
    }

    private function messageUpdate(string $text, string $chatType = 'private'): Update
    {
        $entities = [];
        if (str_starts_with($text, '/')) {
            $entities[] = ['type' => 'bot_command', 'offset' => 0, 'length' => strlen($text)];
        }

        return Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => $chatType],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => $text,
                'entities'   => $entities,
            ],
        ]);
    }

    private function inlineQueryUpdate(): Update
    {
        return Update::fromArray([
            'update_id'    => 1,
            'inline_query' => [
                'id'     => 'iq1',
                'from'   => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'query'  => 'search',
                'offset' => '',
            ],
        ]);
    }

    private function myChatMemberUpdate(string $chatType = 'supergroup'): Update
    {
        return Update::fromArray([
            'update_id'      => 1,
            'my_chat_member' => [
                'chat'            => ['id' => 100, 'type' => $chatType],
                'from'            => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'date'            => 0,
                'old_chat_member' => ['status' => 'left', 'user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot']],
                'new_chat_member' => ['status' => 'member', 'user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot']],
            ],
        ]);
    }

    // ─── The default must not change behaviour for existing bots ──────────────

    public function test_no_allowed_chat_types_config_means_every_chat_still_matches(): void
    {
        foreach (['private', 'group', 'supergroup', 'channel'] as $chatType) {
            $called = false;
            $router = new Router();
            $router->addRoute('text', '*', function () use (&$called) { $called = true; });

            $router->dispatch($this->messageUpdate('hi', $chatType), $this->makeApi());

            $this->assertTrue($called, "Unconfigured router should still match a {$chatType} chat.");
        }
    }

    // ─── Filtering ────────────────────────────────────────────────────────────

    public function test_private_only_config_blocks_group_and_channel_messages(): void
    {
        foreach (['group', 'supergroup', 'channel'] as $chatType) {
            $called = false;
            $router = new Router();
            $router->addRoute('text', '*', function () use (&$called) { $called = true; });

            $router->dispatch(
                $this->messageUpdate('hi', $chatType),
                $this->makeApi(),
                ['allowed_chat_types' => ['private']],
            );

            $this->assertFalse($called, "A {$chatType} message should not reach a private-only bot.");
        }
    }

    public function test_private_only_config_still_allows_private_messages(): void
    {
        $called = false;
        $router = new Router();
        $router->addRoute('text', '*', function () use (&$called) { $called = true; });

        $router->dispatch(
            $this->messageUpdate('hi', 'private'),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertTrue($called);
    }

    public function test_commands_are_filtered_too(): void
    {
        // The scaffold's /start writes a telegram_users row whose chat_id is
        // whatever chat it fired in, so a group /start slipping through is the
        // failure that actually corrupts data.
        $called = false;
        $router = new Router();
        $router->addRoute('command', 'start', function () use (&$called) { $called = true; });

        $router->dispatch(
            $this->messageUpdate('/start', 'supergroup'),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertFalse($called);
    }

    // ─── Always-pass cases ────────────────────────────────────────────────────

    public function test_updates_without_a_chat_are_never_filtered(): void
    {
        $called = false;
        $router = new Router();
        $router->addRoute('inline_query', '*', function () use (&$called) { $called = true; });

        $router->dispatch(
            $this->inlineQueryUpdate(),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertTrue($called, 'An inline query has no chat to filter against.');
    }

    public function test_my_chat_member_is_exempt_so_a_private_bot_can_leave_groups(): void
    {
        $called = false;
        $router = new Router();
        $router->addRoute('my_chat_member', '*', function () use (&$called) { $called = true; });

        $router->dispatch(
            $this->myChatMemberUpdate('supergroup'),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertTrue($called);
    }

    public function test_every_exempt_type_is_a_real_route_type(): void
    {
        // Guards against a typo silently exempting nothing.
        $handled = [];
        $router  = new Router();

        foreach (Router::CHAT_TYPE_EXEMPT as $type) {
            $router->addRoute($type, '*', function () use ($type, &$handled) { $handled[] = $type; });
        }

        $router->dispatch($this->myChatMemberUpdate(), $this->makeApi(), ['allowed_chat_types' => ['private']]);

        $this->assertSame(['my_chat_member'], $handled);
    }

    // ─── Per-route overrides ──────────────────────────────────────────────────

    public function test_with_chat_types_widens_a_route_past_the_global_default(): void
    {
        $called = false;
        $router = new Router();

        $router->withChatTypes(['group', 'supergroup'], function () use ($router, &$called) {
            $router->addRoute('command', 'stats', function () use (&$called) { $called = true; });
        });

        $router->dispatch(
            $this->messageUpdate('/stats', 'supergroup'),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertTrue($called);
    }

    public function test_wildcard_chat_types_accepts_anything(): void
    {
        $called = false;
        $router = new Router();

        $router->withChatTypes(['*'], function () use ($router, &$called) {
            $router->addRoute('text', '*', function () use (&$called) { $called = true; });
        });

        $router->dispatch(
            $this->messageUpdate('hi', 'channel'),
            $this->makeApi(),
            ['allowed_chat_types' => ['private']],
        );

        $this->assertTrue($called);
    }

    public function test_scope_does_not_leak_to_routes_registered_afterwards(): void
    {
        $router = new Router();

        $router->withChatTypes(['group'], function () use ($router) {
            $router->addRoute('command', 'inside', fn() => null);
        });
        $router->addRoute('command', 'outside', fn() => null);

        $routes = $router->routes();

        $this->assertSame(['group'], $routes[0]->chatTypes);
        $this->assertNull($routes[1]->chatTypes, 'Routes after the block must fall back to the global default.');
    }

    public function test_scope_is_restored_when_the_callback_throws(): void
    {
        $router = new Router();

        try {
            $router->withChatTypes(['group'], function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $router->addRoute('command', 'after', fn() => null);

        $this->assertNull($router->routes()[0]->chatTypes);
    }
}
