<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\WebhookException;
use PHPUnit\Framework\TestCase;

class BotInstanceWebhookTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);
        http_response_code(200);
    }

    public function test_invalid_webhook_secret_sets_403_before_throwing(): void
    {
        $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'wrong-token';
        $instance = new BotInstance('token', ['webhook_secret' => 'correct-token'], new FakeHttpClient());

        try {
            $instance->run();
            $this->fail('Expected WebhookException was not thrown.');
        } catch (WebhookException $e) {
            $this->assertSame(403, http_response_code());
        }
    }
}
