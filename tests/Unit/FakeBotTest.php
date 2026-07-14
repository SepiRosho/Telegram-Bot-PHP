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
}
