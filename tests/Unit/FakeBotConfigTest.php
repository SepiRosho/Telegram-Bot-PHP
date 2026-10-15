<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Testing\UpdateFactory;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class FakeBotConfigTest extends TestCase
{
    private function groupText(): Update
    {
        return UpdateFactory::text('hi', ['message' => ['chat' => ['id' => -100, 'type' => 'supergroup']]]);
    }

    public function test_dispatch_honours_allowed_chat_types_like_production(): void
    {
        // FakeBot used to hand the router an empty config, so a group message
        // reached a private-only bot's handler in tests but not in production.
        $fake = Bot::fake(['database' => false, 'allowed_chat_types' => ['private']]);
        $ran = 0;
        Bot::onText(function () use (&$ran): void {
            $ran++;
        });

        $fake->dispatch($this->groupText());
        $this->assertSame(0, $ran);

        $fake->dispatch(UpdateFactory::text('hi'));
        $this->assertSame(1, $ran);
    }

    public function test_dispatch_honours_auto_answer_callbacks(): void
    {
        $fake = Bot::fake(['database' => false, 'auto_answer_callbacks' => false]);

        $fake->dispatch(UpdateFactory::callback('unrouted'));

        $fake->assertNotSent('answerCallbackQuery');
    }

    public function test_unrouted_callbacks_are_still_auto_answered_by_default(): void
    {
        $fake = Bot::fake(['database' => false]);

        $fake->dispatch(UpdateFactory::callback('unrouted'));

        $fake->assertSent('answerCallbackQuery');
    }
}
