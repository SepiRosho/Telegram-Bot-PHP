<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Testing\UpdateFactory;
use Devflow\TelegramBot\Types\Message;
use PHPUnit\Framework\TestCase;

class MessageRoutesTest extends TestCase
{
    /** @dataProvider routes */
    public function test_each_content_route_fires_for_its_message_and_not_for_text(string $method, string $factory): void
    {
        $fake = Bot::fake(['database' => false]);
        $hits = 0;
        Bot::$method(function () use (&$hits): void {
            $hits++;
        });

        $fake->dispatch(UpdateFactory::text('plain text'));
        $this->assertSame(0, $hits, "{$method} must not fire for plain text");

        $fake->dispatch(UpdateFactory::$factory());
        $this->assertSame(1, $hits, "{$method} did not fire for {$factory}()");
    }

    public static function routes(): array
    {
        return [
            'video'              => ['onVideo', 'video'],
            'voice'              => ['onVoice', 'voice'],
            'audio'              => ['onAudio', 'audio'],
            'video note'         => ['onVideoNote', 'videoNote'],
            'sticker'            => ['onSticker', 'sticker'],
            'animation'          => ['onAnimation', 'animation'],
            'location'           => ['onLocation', 'location'],
            'contact'            => ['onContact', 'contact'],
            'successful payment' => ['onSuccessfulPayment', 'successfulPayment'],
            'new chat members'   => ['onNewChatMembers', 'newChatMembers'],
            'left chat member'   => ['onLeftChatMember', 'leftChatMember'],
        ];
    }

    public function test_service_message_routes_work_on_a_private_only_bot(): void
    {
        // They only ever occur inside groups, so the private-only default must
        // not make registering them pointless (same reasoning as my_chat_member).
        $fake = Bot::fake(['database' => false, 'allowed_chat_types' => ['private']]);
        $joined = null;
        Bot::onNewChatMembers(function (Context $ctx) use (&$joined): void {
            $joined = $ctx->message()->newChatMembers[0]->id;
        });

        $fake->dispatch(UpdateFactory::newChatMembers([['id' => 321]]));

        $this->assertSame(321, $joined);
    }

    public function test_successful_payment_is_parsed_into_a_typed_object(): void
    {
        $update = UpdateFactory::successfulPayment('order-7', 250);

        $payment = $update->message->successfulPayment;
        $this->assertSame('order-7', $payment->invoicePayload);
        $this->assertSame(250, $payment->totalAmount);
        $this->assertTrue($payment->isStars());
        $this->assertSame('charge-1', $payment->telegramPaymentChargeId);
    }

    public function test_message_exposes_thread_group_sender_and_via_fields(): void
    {
        $m = Message::fromArray([
            'message_id' => 1,
            'date' => 5,
            'chat' => ['id' => -100, 'type' => 'supergroup'],
            'message_thread_id' => 12,
            'is_topic_message' => true,
            'media_group_id' => 'album-1',
            'sender_chat' => ['id' => -200, 'type' => 'channel', 'title' => 'News'],
            'edit_date' => 9,
            'via_bot' => ['id' => 9, 'is_bot' => true, 'first_name' => 'Inline'],
            'web_app_data' => ['data' => 'x', 'button_text' => 'Open'],
        ]);

        $this->assertSame(12, $m->messageThreadId);
        $this->assertTrue($m->isTopicMessage);
        $this->assertSame('album-1', $m->mediaGroupId);
        $this->assertSame('News', $m->senderChat->title);
        $this->assertSame(9, $m->editDate);
        $this->assertSame(9, $m->viaBot->id);
        $this->assertSame('x', $m->webAppData['data']);
    }

    public function test_new_builders_produce_the_update_types_they_claim(): void
    {
        $this->assertSame('chat_member', UpdateFactory::chatMember('member', 'left')->type());
        $this->assertSame('my_chat_member', UpdateFactory::myChatMember('kicked', 'member')->type());
        $this->assertSame('message_reaction', UpdateFactory::messageReaction()->type());
        $this->assertSame('inline_query', UpdateFactory::inlineQuery('q')->type());
        $this->assertSame('kicked', UpdateFactory::myChatMember('kicked', 'member')->myChatMember->newStatus);
    }

    public function test_chat_member_and_reaction_handlers_can_be_tested_end_to_end(): void
    {
        $fake = Bot::fake(['database' => false]);
        $seen = [];
        Bot::onChatMember(function () use (&$seen): void {
            $seen[] = 'chat_member';
        });
        Bot::onMessageReaction(function () use (&$seen): void {
            $seen[] = 'reaction';
        });
        Bot::onInlineQuery(function () use (&$seen): void {
            $seen[] = 'inline';
        });

        $fake->dispatch(UpdateFactory::chatMember());
        $fake->dispatch(UpdateFactory::messageReaction());
        $fake->dispatch(UpdateFactory::inlineQuery('hi'));

        $this->assertSame(['chat_member', 'reaction', 'inline'], $seen);
    }
}
