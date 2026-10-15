<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

/**
 * handleWebhook() is parseWebhook() + processUpdate(). Callers that answer
 * Telegram between the two (respond_early) depend on parse doing all the
 * rejecting and running nothing.
 */
class ParseProcessWebhookTest extends TestCase
{
    private function payload(): string
    {
        return json_encode([
            'update_id' => 4,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => 'hello',
            ],
        ]);
    }

    public function test_parse_returns_the_update_and_runs_no_handler(): void
    {
        $ran = false;
        $bot = new BotInstance('t', ['webhook_secret' => 's'], new FakeHttpClient());
        $bot->onText(function () use (&$ran): void {
            $ran = true;
        });

        $update = $bot->parseWebhook($this->payload(), 's');

        $this->assertInstanceOf(Update::class, $update);
        $this->assertSame(4, $update->updateId);
        $this->assertFalse($ran, 'parse must not dispatch');
    }

    public function test_parse_does_all_the_rejecting(): void
    {
        $bot = new BotInstance('t', ['webhook_secret' => 's'], new FakeHttpClient());

        foreach ([[$this->payload(), 'wrong', 403], ['not json', 's', 400], ['', 's', 400]] as [$body, $secret, $status]) {
            try {
                $bot->parseWebhook($body, $secret);
                $this->fail('Expected WebhookException.');
            } catch (WebhookException $e) {
                $this->assertSame($status, $e->statusCode());
            }
        }
    }

    public function test_process_dispatches_a_parsed_update(): void
    {
        $seen = null;
        $bot = new BotInstance('t', [], new FakeHttpClient());
        $bot->onText(function ($ctx) use (&$seen): void {
            $seen = $ctx->text();
        });

        $bot->processUpdate($bot->parseWebhook($this->payload()));

        $this->assertSame('hello', $seen);
    }

    public function test_respond_early_is_a_no_op_on_the_cli(): void
    {
        // There is no client to answer on the CLI; run() must still reach the
        // handlers (here it stops earlier, at the empty php://input).
        $bot = new BotInstance('t', ['respond_early' => true], new FakeHttpClient());

        $this->expectException(WebhookException::class);
        $bot->run();
    }
}
