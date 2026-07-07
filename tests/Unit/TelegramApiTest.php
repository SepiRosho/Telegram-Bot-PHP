<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\User;
use PHPUnit\Framework\TestCase;

class TelegramApiTest extends TestCase
{
    private function makeMessage(): array
    {
        return [
            'message_id' => 1,
            'date'       => 0,
            'chat'       => ['id' => 100, 'type' => 'private'],
            'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
            'text'       => 'hi',
        ];
    }

    public function test_get_me_returns_user(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturn([
            'id'         => 1,
            'is_bot'     => true,
            'first_name' => 'MyBot',
            'username'   => 'my_bot',
        ]);

        $api = new TelegramApi($http);
        $user = $api->getMe();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('MyBot', $user->firstName);
        $this->assertTrue($user->isBot);
    }

    public function test_send_message_returns_message(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturn($this->makeMessage());

        $api = new TelegramApi($http);
        $msg = $api->sendMessage(100, 'Hello');

        $this->assertInstanceOf(Message::class, $msg);
        $this->assertSame('hi', $msg->text);
    }

    public function test_send_message_passes_options(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with('sendMessage', $this->callback(function (array $params) {
                return $params['chat_id'] === 100
                    && $params['text'] === 'Hello'
                    && $params['parse_mode'] === 'HTML';
            }))
            ->willReturn($this->makeMessage());

        $api = new TelegramApi($http);
        $api->sendMessage(100, 'Hello', ['parse_mode' => 'HTML']);
    }

    public function test_answer_callback_query_returns_bool(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturn(true);

        $api = new TelegramApi($http);
        $result = $api->answerCallbackQuery('cq1', ['text' => 'Done']);

        $this->assertTrue($result);
    }

    public function test_delete_message_returns_bool(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturn(true);

        $api = new TelegramApi($http);
        $this->assertTrue($api->deleteMessage(100, 1));
    }

    public function test_get_updates_returns_array(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturn([]);

        $api = new TelegramApi($http);
        $this->assertSame([], $api->getUpdates(['timeout' => 1]));
    }

    public function test_http_exception_bubbles_as_telegram_api_exception(): void
    {
        $this->expectException(TelegramApiException::class);

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willThrowException(new TelegramApiException('Bad request', 400));

        $api = new TelegramApi($http);
        $api->sendMessage(100, 'fail');
    }
}
