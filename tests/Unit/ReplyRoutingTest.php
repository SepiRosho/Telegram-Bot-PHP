<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Testing\UpdateFactory;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

/**
 * Replies must land where the message being answered lives: in the same forum
 * topic, and through the same business connection.
 */
class ReplyRoutingTest extends TestCase
{
    private FakeHttpClient $http;

    private function ctx(Update $update): Context
    {
        $this->http = new FakeHttpClient();

        return new Context($update, new TelegramApi($this->http));
    }

    private function sent(string $method = 'sendMessage'): array
    {
        $calls = $this->http->callsTo($method);
        $this->assertCount(1, $calls);

        return $calls[0]['params'];
    }

    private function topicMessage(array $extra = []): Update
    {
        return UpdateFactory::text('hi', ['message' => array_merge([
            'chat'              => ['id' => -100, 'type' => 'supergroup'],
            'message_thread_id' => 12,
            'is_topic_message'  => true,
        ], $extra)]);
    }

    public function test_a_reply_in_a_forum_topic_stays_in_that_topic(): void
    {
        $ctx = $this->ctx($this->topicMessage());

        $ctx->reply('answer');

        $this->assertSame(12, $this->sent()['message_thread_id']);
    }

    public function test_every_send_shorthand_carries_the_topic(): void
    {
        $ctx = $this->ctx($this->topicMessage());

        $ctx->replyWithPhoto('file_id');
        $ctx->replyWithLocation(1.0, 2.0);
        $ctx->typing();

        $this->assertSame(12, $this->sent('sendPhoto')['message_thread_id']);
        $this->assertSame(12, $this->sent('sendLocation')['message_thread_id']);
        $this->assertSame(12, $this->sent('sendChatAction')['message_thread_id']);
    }

    public function test_a_plain_reply_thread_is_not_mistaken_for_a_topic(): void
    {
        // Non-forum supergroups also number reply chains with message_thread_id,
        // but is_topic_message is absent — sending that id as a thread would fail.
        $ctx = $this->ctx($this->topicMessage(['is_topic_message' => false]));

        $ctx->reply('answer');

        $this->assertArrayNotHasKey('message_thread_id', $this->sent());
    }

    public function test_an_explicit_thread_wins_including_null_for_general(): void
    {
        $ctx = $this->ctx($this->topicMessage());
        $ctx->reply('a', ['message_thread_id' => 99]);
        $this->assertSame(99, $this->sent()['message_thread_id']);

        // null is stripped by the HTTP client, so the message reaches General.
        $ctx = $this->ctx($this->topicMessage());
        $ctx->reply('b', ['message_thread_id' => null]);
        $this->assertNull($this->sent()['message_thread_id']);
    }

    public function test_a_callback_from_a_topic_message_replies_into_the_topic(): void
    {
        $update = UpdateFactory::callback('x', ['callback_query' => ['message' => [
            'chat'              => ['id' => -100, 'type' => 'supergroup'],
            'message_thread_id' => 7,
            'is_topic_message'  => true,
        ]]]);
        $ctx = $this->ctx($update);

        $ctx->reply('answer');

        $this->assertSame(7, $this->sent()['message_thread_id']);
    }

    public function test_a_private_chat_reply_has_no_routing_keys(): void
    {
        $ctx = $this->ctx(UpdateFactory::text('hi'));

        $ctx->reply('answer');

        $params = $this->sent();
        $this->assertArrayNotHasKey('message_thread_id', $params);
        $this->assertArrayNotHasKey('business_connection_id', $params);
    }

    public function test_a_business_chat_reply_goes_through_the_connection(): void
    {
        $update = UpdateFactory::raw([
            'update_id'        => 1,
            'business_message' => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Customer'],
                'text'       => 'hello',
                'business_connection_id' => 'bc-1',
            ],
        ]);
        $ctx = $this->ctx($update);

        $ctx->reply('answer');

        $this->assertSame('bc-1', $this->sent()['business_connection_id']);
    }
}
