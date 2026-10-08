<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\WebhookException;
use PHPUnit\Framework\TestCase;

/**
 * handleWebhook() is the framework-agnostic half of run(): the same checks,
 * fed a body and header instead of php://input and $_SERVER. The Laravel
 * route relies on it, so what it rejects — and with which status — matters.
 */
class HandleWebhookTest extends TestCase
{
    private function payload(): string
    {
        return json_encode([
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => 'hello',
            ],
        ]);
    }

    private function bot(array $config = []): BotInstance
    {
        return new BotInstance('token', $config, new FakeHttpClient());
    }

    private function statusOf(callable $call): int
    {
        try {
            $call();
        } catch (WebhookException $e) {
            return $e->statusCode();
        }
        $this->fail('Expected WebhookException was not thrown.');
    }

    public function test_a_valid_delivery_is_dispatched(): void
    {
        $seen = null;
        $bot  = $this->bot(['webhook_secret' => 's3cret']);
        $bot->onText(function ($ctx) use (&$seen): void {
            $seen = $ctx->text();
        });

        $bot->handleWebhook($this->payload(), 's3cret');

        $this->assertSame('hello', $seen);
    }

    public function test_a_wrong_or_missing_secret_is_403(): void
    {
        $bot = $this->bot(['webhook_secret' => 's3cret']);

        $this->assertSame(403, $this->statusOf(fn() => $bot->handleWebhook($this->payload(), 'wrong')));
        $this->assertSame(403, $this->statusOf(fn() => $bot->handleWebhook($this->payload(), null)));
    }

    public function test_an_empty_configured_secret_means_no_secret(): void
    {
        // TELEGRAM_WEBHOOK_SECRET= in .env arrives as '' rather than null.
        $bot = $this->bot(['webhook_secret' => '']);

        $bot->handleWebhook($this->payload(), null);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider malformedBodies */
    public function test_a_malformed_body_is_400(string $body): void
    {
        $bot = $this->bot();

        $this->assertSame(400, $this->statusOf(fn() => $bot->handleWebhook($body)));
    }

    public static function malformedBodies(): array
    {
        return [
            'empty'            => [''],
            'not json'         => ['not json'],
            'json scalar'      => ['42'],
            'no update_id'     => ['{"message":{}}'],
        ];
    }

    public function test_a_bad_secret_is_rejected_before_the_body_is_read(): void
    {
        $bot = $this->bot(['webhook_secret' => 's3cret']);

        $this->assertSame(403, $this->statusOf(fn() => $bot->handleWebhook('not json', 'wrong')));
    }

    public function test_a_handler_error_still_propagates(): void
    {
        // handleWebhook() absorbs only what Telegram treats as routine; turning
        // a real bug into a 200 is the Laravel controller's decision, not the core's.
        $bot = $this->bot();
        $bot->onText(function (): void {
            throw new \RuntimeException('boom');
        });

        $this->expectException(\RuntimeException::class);
        $bot->handleWebhook($this->payload());
    }
}
