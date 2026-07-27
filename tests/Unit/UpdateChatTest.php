<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class UpdateChatTest extends TestCase
{
    private function context(Update $update): Context
    {
        return new Context($update, new TelegramApi($this->createMock(HttpClientInterface::class)));
    }

    private function chat(string $type): array
    {
        return ['id' => 100, 'type' => $type];
    }

    private function user(): array
    {
        return ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'];
    }

    /** @return array<string, array{array, ?string}> */
    public static function updateShapes(): array
    {
        $chat = ['id' => 100, 'type' => 'supergroup'];
        $from = ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'];
        $msg  = ['message_id' => 1, 'date' => 0, 'chat' => $chat, 'from' => $from, 'text' => 'hi'];

        return [
            'message'                 => [['message' => $msg], 'supergroup'],
            'edited_message'          => [['edited_message' => $msg], 'supergroup'],
            'channel_post'            => [['channel_post' => $msg], 'supergroup'],
            'edited_channel_post'     => [['edited_channel_post' => $msg], 'supergroup'],
            'business_message'        => [['business_message' => $msg], 'supergroup'],
            'edited_business_message' => [['edited_business_message' => $msg], 'supergroup'],
            'guest_message'           => [['guest_message' => $msg], 'supergroup'],
            'callback_query'          => [['callback_query' => ['id' => 'c1', 'from' => $from, 'data' => 'x', 'message' => $msg]], 'supergroup'],
            'my_chat_member'          => [['my_chat_member' => [
                'chat' => $chat, 'from' => $from, 'date' => 0,
                'old_chat_member' => ['status' => 'left', 'user' => $from],
                'new_chat_member' => ['status' => 'member', 'user' => $from],
            ]], 'supergroup'],
            'chat_member'             => [['chat_member' => [
                'chat' => $chat, 'from' => $from, 'date' => 0,
                'old_chat_member' => ['status' => 'left', 'user' => $from],
                'new_chat_member' => ['status' => 'member', 'user' => $from],
            ]], 'supergroup'],
            'chat_join_request'       => [['chat_join_request' => ['chat' => $chat, 'from' => $from, 'user_chat_id' => 200, 'date' => 0]], 'supergroup'],

            // No chat to resolve — these reach the bot from outside any chat.
            'inline_query'            => [['inline_query' => ['id' => 'i1', 'from' => $from, 'query' => 'q', 'offset' => '']], null],
            'chosen_inline_result'    => [['chosen_inline_result' => ['result_id' => 'r1', 'from' => $from, 'query' => 'q']], null],
            'poll_answer'             => [['poll_answer' => ['poll_id' => 'p1', 'option_ids' => [0]]], null],
        ];
    }

    /** @dataProvider updateShapes */
    public function test_chat_type_resolves_across_update_shapes(array $payload, ?string $expected): void
    {
        $update = Update::fromArray(['update_id' => 1] + $payload);

        $this->assertSame($expected, $update->chatType());
        $this->assertSame($expected, $this->context($update)->chatType());
    }

    public function test_context_predicates_for_a_private_chat(): void
    {
        $ctx = $this->context(Update::fromArray([
            'update_id' => 1,
            'message'   => ['message_id' => 1, 'date' => 0, 'chat' => $this->chat('private'), 'from' => $this->user(), 'text' => 'hi'],
        ]));

        $this->assertTrue($ctx->isPrivate());
        $this->assertFalse($ctx->isGroup());
        $this->assertFalse($ctx->isChannel());
    }

    public function test_is_group_covers_both_group_and_supergroup(): void
    {
        foreach (['group', 'supergroup'] as $type) {
            $ctx = $this->context(Update::fromArray([
                'update_id' => 1,
                'message'   => ['message_id' => 1, 'date' => 0, 'chat' => $this->chat($type), 'from' => $this->user(), 'text' => 'hi'],
            ]));

            $this->assertTrue($ctx->isGroup(), "{$type} should count as a group.");
            $this->assertFalse($ctx->isPrivate());
        }
    }

    public function test_channel_predicate(): void
    {
        $ctx = $this->context(Update::fromArray([
            'update_id'    => 1,
            'channel_post' => ['message_id' => 1, 'date' => 0, 'chat' => $this->chat('channel'), 'text' => 'hi'],
        ]));

        $this->assertTrue($ctx->isChannel());
        $this->assertFalse($ctx->isGroup());
    }

    public function test_predicates_are_all_false_when_there_is_no_chat(): void
    {
        $ctx = $this->context(Update::fromArray([
            'update_id'    => 1,
            'inline_query' => ['id' => 'i1', 'from' => $this->user(), 'query' => 'q', 'offset' => ''],
        ]));

        $this->assertNull($ctx->chat());
        $this->assertFalse($ctx->isPrivate());
        $this->assertFalse($ctx->isGroup());
        $this->assertFalse($ctx->isChannel());
    }

    public function test_chat_returns_the_chat_object_itself(): void
    {
        $ctx = $this->context(Update::fromArray([
            'update_id' => 1,
            'message'   => ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 555, 'type' => 'private'], 'from' => $this->user(), 'text' => 'hi'],
        ]));

        $this->assertSame(555, $ctx->chat()?->id);
        $this->assertTrue($ctx->chat()?->isPrivate());
    }
}
