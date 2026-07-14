<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Devflow\TelegramBot\Testing\UpdateFactory;
use PHPUnit\Framework\TestCase;

class ContextSafeEditTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();
    }

    private function callbackUpdateOnMediaMessage(): \Devflow\TelegramBot\Types\Update
    {
        return UpdateFactory::callback('refresh', [
            'callback_query' => [
                'message' => [
                    'photo' => [['file_id' => 'p1', 'width' => 10, 'height' => 10]],
                    'caption' => 'old caption',
                ],
            ],
        ]);
    }

    public function test_edit_reply_safe_swallows_not_modified_error(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willReturnCallback(function (string $method, array $params) {
            if ($method === 'editMessageText') {
                throw new TelegramApiException('Bad Request: message is not modified');
            }
            return ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 100, 'type' => 'private']];
        });

        $ctx = new Context(UpdateFactory::callback('refresh'), new TelegramApi($http));

        $result = $ctx->editReplySafe('same text');

        $this->assertNull($result);
    }

    public function test_edit_reply_safe_rethrows_other_errors(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willThrowException(new TelegramApiException('Bad Request: chat not found'));

        $ctx = new Context(UpdateFactory::callback('refresh'), new TelegramApi($http));

        $this->expectException(TelegramApiException::class);
        $ctx->editReplySafe('new text');
    }

    public function test_edit_reply_safe_uses_caption_edit_for_media_messages(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $calledMethods = [];
        $http->method('post')->willReturnCallback(function (string $method, array $params) use (&$calledMethods) {
            $calledMethods[] = $method;
            return ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 100, 'type' => 'private']];
        });

        $ctx = new Context($this->callbackUpdateOnMediaMessage(), new TelegramApi($http));
        $ctx->editReplySafe('new caption');

        $this->assertSame(['editMessageCaption'], $calledMethods);
    }

    public function test_remove_keyboard_strips_inline_keyboard(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $capturedParams = null;
        $http->method('post')->willReturnCallback(function (string $method, array $params) use (&$capturedParams) {
            $capturedParams = $params;
            return ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 100, 'type' => 'private']];
        });

        $ctx = new Context(UpdateFactory::callback('refresh'), new TelegramApi($http));

        $this->assertTrue($ctx->removeKeyboard());
        $this->assertSame(['inline_keyboard' => []], $capturedParams['reply_markup']);
    }

    public function test_remove_keyboard_swallows_not_modified_error(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('post')->willThrowException(new TelegramApiException('Bad Request: message is not modified'));

        $ctx = new Context(UpdateFactory::callback('refresh'), new TelegramApi($http));

        $this->assertFalse($ctx->removeKeyboard());
    }
}
