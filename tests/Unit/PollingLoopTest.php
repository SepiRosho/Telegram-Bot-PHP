<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use PHPUnit\Framework\TestCase;

/**
 * runPolling() never returns, so every test here ends it the only way a real
 * bot would: a 409, which is one of the errors the loop refuses to retry.
 */
class PollingLoopTest extends TestCase
{
    private function update(int $id, string $text): array
    {
        return [
            'update_id' => $id,
            'message'   => [
                'message_id' => $id,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => $text,
            ],
        ];
    }

    private function conflict(): TelegramApiException
    {
        return new TelegramApiException(
            "Conflict: can't use getUpdates method while webhook is active",
            409,
        );
    }

    /**
     * @param list<array{0:\Throwable,1:int}> $errors
     * @return list<array{method:string,params:array}> every getUpdates call made
     */
    private function runUntilConflict(BotInstance $bot, FakeHttpClient $http, array &$errors, bool $dropPending = false): array
    {
        try {
            $bot->runPolling(
                function (\Throwable $e, int $retryIn = 0) use (&$errors): void {
                    $errors[] = [$e, $retryIn];
                },
                $dropPending,
            );
        } catch (TelegramApiException $e) {
            $this->assertSame(409, $e->telegramErrorCode(), 'the loop stopped for the wrong reason');
        }

        return $http->callsTo('getUpdates');
    }

    // ─── The reported bug ─────────────────────────────────────────────────────

    public function test_a_handler_that_throws_does_not_get_the_same_update_forever(): void
    {
        // The exact report: sendMessage fails with "bot was blocked by the
        // user", the loop treats it as a crash, and because the offset only
        // advanced after a *successful* dispatch, Telegram hands back the same
        // update every 5 seconds for ever.
        $http = new FakeHttpClient();
        $http->respond('getUpdates', [$this->update(77, 'hi')]);
        $http->respond('getUpdates', $this->conflict());

        $bot = new BotInstance('123:token', [], $http);
        $bot->onText(function (Context $ctx): void {
            throw new TelegramApiException('Forbidden: bot was blocked by the user', 403);
        });

        $errors = [];
        $calls  = $this->runUntilConflict($bot, $http, $errors);

        $this->assertCount(2, $calls, 'the loop must move on, not refetch the same batch');
        $this->assertSame(78, $calls[1]['params']['offset'], 'offset must clear the update that threw');
    }

    public function test_a_failed_update_does_not_block_the_ones_behind_it(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', [
            $this->update(1, 'first'),
            $this->update(2, 'boom'),
            $this->update(3, 'third'),
        ]);
        $http->respond('getUpdates', $this->conflict());

        $seen = [];
        $bot = new BotInstance('123:token', [], $http);
        $bot->onText(function (Context $ctx) use (&$seen): void {
            $seen[] = $ctx->text();
            if ($ctx->text() === 'boom') {
                throw new \RuntimeException('handler exploded');
            }
        });

        $errors = [];
        $calls  = $this->runUntilConflict($bot, $http, $errors);

        $this->assertSame(['first', 'boom', 'third'], $seen);
        $this->assertSame(4, $calls[1]['params']['offset']);
    }

    public function test_a_dispatch_failure_is_reported_without_a_retry_delay(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', [$this->update(5, 'hi')]);
        $http->respond('getUpdates', $this->conflict());

        $bot = new BotInstance('123:token', [], $http);
        $bot->onText(function (): void {
            throw new TelegramApiException('Forbidden: bot was blocked by the user', 403);
        });

        $errors = [];
        $this->runUntilConflict($bot, $http, $errors);

        // First the handler failure, then the conflict that ended the loop.
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('blocked by the user', $errors[0][0]->getMessage());
        $this->assertSame(0, $errors[0][1], 'a dead recipient is not something to back off from');
    }

    // ─── Errors that never resolve by waiting ────────────────────────────────

    public static function fatalCodeProvider(): array
    {
        return [
            'webhook still set' => [409, "Conflict: can't use getUpdates method while webhook is active"],
            'token rejected'    => [401, 'Unauthorized'],
            'bot unknown'       => [404, 'Not Found'],
        ];
    }

    /** @dataProvider fatalCodeProvider */
    public function test_polling_stops_instead_of_looping_on_unfixable_errors(int $code, string $message): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', new TelegramApiException($message, $code));

        $bot = new BotInstance('123:token', [], $http);

        $reported = [];
        try {
            $bot->runPolling(function (\Throwable $e, int $retryIn = 0) use (&$reported): void {
                $reported[] = $retryIn;
            });
            $this->fail('runPolling should have rethrown');
        } catch (TelegramApiException $e) {
            $this->assertSame($code, $e->telegramErrorCode());
        }

        $this->assertSame([0], $reported, 'no retry should be promised for an error that cannot clear');
        $this->assertCount(1, $http->callsTo('getUpdates'), 'it must not try again');
    }

    // ─── Backoff ─────────────────────────────────────────────────────────────

    private function backoff(BotInstance $bot, int $failures, \Throwable $e): int
    {
        return (new \ReflectionMethod(BotInstance::class, 'pollingBackoff'))->invoke($bot, $failures, $e);
    }

    public function test_repeated_fetch_failures_back_off_and_then_plateau(): void
    {
        $bot = new BotInstance('123:token', [], new FakeHttpClient());
        $e   = new TelegramApiException('cURL error 6: Could not resolve host', 0);

        $waits = array_map(fn(int $n) => $this->backoff($bot, $n, $e), [1, 2, 3, 4, 5, 6, 7, 50]);

        $this->assertSame([1, 2, 5, 10, 30, 60, 60, 60], $waits);
    }

    public function test_telegrams_own_retry_after_beats_the_backoff_schedule(): void
    {
        $bot = new BotInstance('123:token', [], new FakeHttpClient());
        $e   = new TelegramApiException('Too Many Requests', 429, null, ['retry_after' => 17]);

        $this->assertSame(17, $this->backoff($bot, 1, $e));
    }

    // ─── --drop-pending ──────────────────────────────────────────────────────

    public function test_drop_pending_skips_the_backlog_without_handling_it(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', [$this->update(500, 'sent while the bot was down')]);
        $http->respond('getUpdates', $this->conflict());

        $seen = [];
        $bot = new BotInstance('123:token', [], $http);
        $bot->onText(function (Context $ctx) use (&$seen): void {
            $seen[] = $ctx->text();
        });

        $errors = [];
        $calls  = $this->runUntilConflict($bot, $http, $errors, dropPending: true);

        // A negative offset asks for the newest update, not the oldest — that
        // is what makes finding the end of the backlog a single call.
        $this->assertSame(['offset' => -1, 'limit' => 1, 'timeout' => 0], $calls[0]['params']);
        $this->assertSame(501, $calls[1]['params']['offset']);
        $this->assertSame([], $seen, 'the probed update is skipped, never dispatched');
    }

    public function test_drop_pending_on_an_empty_queue_starts_from_scratch(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', []);
        $http->respond('getUpdates', $this->conflict());

        $bot = new BotInstance('123:token', [], $http);

        $errors = [];
        $calls  = $this->runUntilConflict($bot, $http, $errors, dropPending: true);

        $this->assertArrayNotHasKey('offset', $calls[1]['params']);
    }

    public function test_polling_without_the_flag_still_starts_at_the_backlog(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getUpdates', $this->conflict());

        $bot = new BotInstance('123:token', [], $http);

        $errors = [];
        $calls  = $this->runUntilConflict($bot, $http, $errors);

        $this->assertCount(1, $calls, 'no probe call should be made');
        $this->assertArrayNotHasKey('offset', $calls[0]['params']);
    }
}
