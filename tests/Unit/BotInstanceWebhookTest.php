<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Types\Update;
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

    // ─── Errors escaping a handler ────────────────────────────────────────────
    //
    // run() itself reads php://input, which CLI can't populate, so these drive
    // the dispatch step directly — the part the redelivery behaviour hinges on.

    private function textUpdate(): Update
    {
        return Update::fromArray([
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

    private function dispatch(BotInstance $bot): void
    {
        (new \ReflectionMethod(BotInstance::class, 'dispatchWebhookUpdate'))
            ->invoke($bot, $this->textUpdate(), null);
    }

    public function test_a_blocked_user_does_not_fail_the_webhook_request(): void
    {
        // A non-2xx here makes Telegram resend the update, so this exception
        // reaching the top would put the webhook in the same redelivery loop
        // polling used to be in.
        $bot = new BotInstance('token', [], new FakeHttpClient());
        $bot->onText(function (): void {
            throw new TelegramApiException('Forbidden: bot was blocked by the user', 403);
        });

        $this->dispatch($bot);
        $this->addToAssertionCount(1);
    }

    public function test_a_stale_callback_query_does_not_fail_the_webhook_request(): void
    {
        $bot = new BotInstance('token', [], new FakeHttpClient());
        $bot->onText(function (): void {
            throw new TelegramApiException('Bad Request: query is too old and response timeout expired', 400);
        });

        $this->dispatch($bot);
        $this->addToAssertionCount(1);
    }

    public function test_a_real_bug_still_surfaces(): void
    {
        // The line between "absorb" and "suppress": anything Telegram doesn't
        // consider routine has to keep reaching the project's error handler.
        $bot = new BotInstance('token', [], new FakeHttpClient());
        $bot->onText(function (): void {
            throw new TelegramApiException('Bad Request: message text is empty', 400);
        });

        $this->expectException(TelegramApiException::class);
        $this->dispatch($bot);
    }

    public function test_non_telegram_exceptions_are_never_absorbed(): void
    {
        $bot = new BotInstance('token', [], new FakeHttpClient());
        $bot->onText(function (): void {
            throw new \RuntimeException('your database is on fire');
        });

        $this->expectException(\RuntimeException::class);
        $this->dispatch($bot);
    }
}
