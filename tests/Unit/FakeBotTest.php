<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Testing\UpdateFactory;
use PHPUnit\Framework\TestCase;

class FakeBotTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();
    }

    public function test_fake_bot_dispatches_command_without_network(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->reply('Hello, ' . $ctx->from()?->firstName);
        });

        $fake->dispatch(UpdateFactory::command('start'));

        $fake->assertSent('sendMessage', fn(array $p) => $p['text'] === 'Hello, Test');
    }

    public function test_fake_bot_via_static_facade_after_fake(): void
    {
        // Simulates production handler code calling Bot::onCommand() inside a
        // register() method — it should route to the active fake instance.
        $fake = Bot::fake();
        Bot::onCommand('ping', function (Context $ctx) {
            $ctx->reply('pong');
        });

        $fake->dispatch(UpdateFactory::command('ping'));

        $fake->assertSent('sendMessage', fn(array $p) => $p['text'] === 'pong');
    }

    public function test_assert_not_sent(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->reply('hi');
        });

        $fake->dispatch(UpdateFactory::command('other'));

        $fake->assertNotSent('sendMessage');
    }

    public function test_step_flow_persists_across_dispatches_via_fake_user_repository(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->setStep('awaiting_name');
        });
        $fake->onStep('awaiting_name', function (Context $ctx) {
            $name = $ctx->text();
            $ctx->clearFlow();
            $ctx->reply('Got it: ' . $name);
        });

        $fake->dispatch(UpdateFactory::command('start'));
        $fake->dispatch(UpdateFactory::text('Ali'));

        $fake->assertSent('sendMessage', fn(array $p) => $p['text'] === 'Got it: Ali');
    }

    public function test_step_flow_matches_photo_when_types_configured(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->setStep('awaiting_media');
        });
        $fake->onStep('awaiting_media', function (Context $ctx) {
            $ctx->reply('media received');
        }, ['text', 'photo']);

        $fake->dispatch(UpdateFactory::command('start'));
        $fake->dispatch(UpdateFactory::photo());

        $fake->assertSent('sendMessage', fn(array $p) => $p['text'] === 'media received');
    }

    public function test_fake_user_supports_update_and_admin_checks_like_the_real_model(): void
    {
        // Regression test: real handler code commonly calls $ctx->user()->update([...])
        // and ->isAdmin() (see the scaffolded UserHandlers/AdminHandlers), so FakeUser
        // must support both to be a faithful stand-in for TelegramUser.
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->user()->update(['current_panel' => 'user', 'role' => 'superadmin']);
            $ctx->reply($ctx->user()->isAdmin() ? 'admin' : 'not admin');
        });

        $fake->dispatch(UpdateFactory::command('start'));

        $fake->assertSent('sendMessage', fn(array $p) => $p['text'] === 'admin');
    }

    public function test_fake_user_carries_a_stable_id_for_related_tables(): void
    {
        // Almost any bot beyond a toy example has its own tables keyed on
        // telegram_users.id; without an id, nothing a handler stores can be
        // related back to $ctx->user().
        $fake = Bot::fake();
        $seen = [];
        $fake->onCommand('start', function (Context $ctx) use (&$seen) {
            $seen[] = $ctx->user()->id;
        });

        $fake->dispatch(UpdateFactory::command('start'));
        $fake->dispatch(UpdateFactory::command('start'));

        $this->assertSame([1, 1], $seen, 'The same Telegram user must keep the same id across dispatches.');
        $this->assertSame(1, $fake->users()->find(1)?->id);
    }

    public function test_distinct_users_get_distinct_ids(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', fn(Context $ctx) => null);

        $fake->dispatch(UpdateFactory::command('start'));
        $fake->dispatch(UpdateFactory::command('start', overrides: [
            'message' => [
                'message_id' => 2,
                'date'       => 0,
                'chat'       => ['id' => 101, 'type' => 'private'],
                'from'       => ['id' => 201, 'is_bot' => false, 'first_name' => 'Other'],
                'text'       => '/start',
                'entities'   => [['type' => 'bot_command', 'offset' => 0, 'length' => 6]],
            ],
        ]));

        $this->assertSame([1, 2], array_map(fn($u) => $u->id, array_values($fake->users()->all())));
    }
}
