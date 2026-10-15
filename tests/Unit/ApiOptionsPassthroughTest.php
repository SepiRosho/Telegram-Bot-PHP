<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\Api\TelegramApi;
use PHPUnit\Framework\TestCase;

/**
 * These methods used to accept only a fixed parameter list, so Bot API
 * parameters added later (business_connection_id, message_thread_id,
 * return_bots) could not be sent at all.
 */
class ApiOptionsPassthroughTest extends TestCase
{
    private FakeHttpClient $http;
    private TelegramApi $api;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->api  = new TelegramApi($this->http);
    }

    private function params(string $method): array
    {
        return $this->http->callsTo($method)[0]['params'];
    }

    public function test_send_chat_action_takes_a_thread_and_business_connection(): void
    {
        $this->api->sendChatAction(1, 'typing', ['message_thread_id' => 5, 'business_connection_id' => 'bc']);

        $this->assertSame(
            ['chat_id' => 1, 'action' => 'typing', 'message_thread_id' => 5, 'business_connection_id' => 'bc'],
            $this->params('sendChatAction'),
        );
    }

    public function test_pin_and_unpin_take_a_business_connection(): void
    {
        $this->api->pinChatMessage(1, 2, false, ['business_connection_id' => 'bc']);
        $this->api->unpinChatMessage(1, 2, ['business_connection_id' => 'bc']);

        $this->assertSame('bc', $this->params('pinChatMessage')['business_connection_id']);
        $this->assertSame('bc', $this->params('unpinChatMessage')['business_connection_id']);
    }

    public function test_get_chat_administrators_takes_return_bots(): void
    {
        $this->http->respond('getChatAdministrators', []);
        $this->api->getChatAdministrators(1, ['return_bots' => true]);

        $this->assertTrue($this->params('getChatAdministrators')['return_bots']);
    }

    public function test_edit_message_reply_markup_takes_a_business_connection(): void
    {
        $this->api->editMessageReplyMarkup(1, 2, ['inline_keyboard' => []], ['business_connection_id' => 'bc']);

        $this->assertSame('bc', $this->params('editMessageReplyMarkup')['business_connection_id']);
    }

    public function test_the_old_call_shapes_still_send_exactly_what_they_did(): void
    {
        $this->http->respond('getChatAdministrators', []);
        $this->api->sendChatAction(1, 'typing');
        $this->api->getChatAdministrators(1);

        $this->assertSame(['chat_id' => 1, 'action' => 'typing'], $this->params('sendChatAction'));
        $this->assertSame(['chat_id' => 1], $this->params('getChatAdministrators'));
    }
}
