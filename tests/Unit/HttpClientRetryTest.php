<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\InputFile;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real post() retry loop against a Guzzle MockHandler instead
 * of reflecting into private decision methods — the loop now coordinates
 * enough moving pieces (backoff, jitter, a pluggable strategy, an observer
 * callback, a pluggable sleeper) that testing them end to end is the more
 * honest check. `sleeper` is always overridden with a recording no-op so
 * these tests never actually block.
 */
class HttpClientRetryTest extends TestCase
{
    /** @param list<Response|\Throwable> $queue */
    private function client(array $queue, array $options = [], ?array &$sleeps = null): HttpClient
    {
        $sleeps ??= [];
        $mock = new MockHandler($queue);

        return new HttpClient('123:token', array_merge([
            'handler' => HandlerStack::create($mock),
            'sleeper' => function (int $seconds) use (&$sleeps) {
                $sleeps[] = $seconds;
            },
        ], $options));
    }

    private function ok(array $result = ['message_id' => 1]): Response
    {
        return new Response(200, [], (string) json_encode(['ok' => true, 'result' => $result]));
    }

    private function error(int $code, string $description, array $parameters = []): Response
    {
        $body = array_filter([
            'ok'          => false,
            'error_code'  => $code,
            'description' => $description,
            'parameters'  => $parameters ?: null,
        ], fn($v) => $v !== null);

        return new Response($code, [], (string) json_encode($body));
    }

    // ─── The basics still hold ──────────────────────────────────────────────

    public function test_a_retry_after_is_retried_while_attempts_remain(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 3]),
            $this->ok(),
        ], ['max_retries' => 2], $sleeps);

        $result = $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame(['message_id' => 1], $result);
        $this->assertSame([3], $sleeps);
    }

    public function test_retries_stop_once_max_retries_is_reached(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 1]),
            $this->error(429, 'Too Many Requests', ['retry_after' => 1]),
        ], ['max_retries' => 1], $sleeps);

        $this->expectException(TelegramApiException::class);

        try {
            $client->post('sendMessage', ['chat_id' => 1]);
        } finally {
            $this->assertSame([1], $sleeps, 'exactly one retry should have been attempted');
        }
    }

    public function test_max_retries_zero_disables_retrying(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 1]),
        ], ['max_retries' => 0], $sleeps);

        $this->expectException(TelegramApiException::class);

        try {
            $client->post('sendMessage', ['chat_id' => 1]);
        } finally {
            $this->assertSame([], $sleeps);
        }
    }

    public function test_an_absurdly_long_retry_after_is_thrown_rather_than_waited_out(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 61]),
        ], ['max_retry_after' => 60], $sleeps);

        try {
            $client->post('sendMessage', ['chat_id' => 1]);
            $this->fail('Expected a TelegramApiException.');
        } catch (TelegramApiException $e) {
            $this->assertSame(61, $e->retryAfter());
            $this->assertSame([], $sleeps);
        }
    }

    public function test_an_error_without_retry_after_is_never_retried_by_default(): void
    {
        $client = $this->client([
            $this->error(400, 'Bad Request: chat not found'),
        ], [], $sleeps);

        $this->expectException(TelegramApiException::class);

        try {
            $client->post('sendMessage', ['chat_id' => 1]);
        } finally {
            $this->assertSame([], $sleeps);
        }
    }

    // ─── Uploads: the old "never retried" limitation is fixed ──────────────

    /**
     * requestOptions() — and therefore InputFile::open() — is called fresh on
     * every loop iteration, so a retry sends a brand new handle/string rather
     * than replaying an already-consumed one. Uploads now retry exactly like
     * any other request.
     */
    public function test_uploads_are_retried_because_a_fresh_stream_is_opened_each_attempt(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 1]),
            $this->ok(),
        ], ['max_retries' => 2], $sleeps);

        $result = $client->post('sendDocument', [
            'chat_id'  => 1,
            'document' => InputFile::contents('file bytes', 'a.pdf'),
        ]);

        $this->assertSame(['message_id' => 1], $result);
        $this->assertSame([1], $sleeps);
    }

    // ─── 429 without an explicit retry_after ────────────────────────────────

    public function test_a_429_without_retry_after_falls_back_to_exponential_backoff(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests'),
            $this->ok(),
        ], ['max_retries' => 2], $sleeps);

        $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame([1], $sleeps); // backoffSeconds(0) === 2**0
    }

    // ─── retry_transient (opt-in) ────────────────────────────────────────────

    public function test_transient_5xx_errors_are_not_retried_by_default(): void
    {
        $client = $this->client([
            $this->error(500, 'Internal Server Error'),
        ], [], $sleeps);

        $this->expectException(TelegramApiException::class);

        try {
            $client->post('sendMessage', ['chat_id' => 1]);
        } finally {
            $this->assertSame([], $sleeps);
        }
    }

    public function test_retry_transient_retries_5xx_errors(): void
    {
        $client = $this->client([
            $this->error(500, 'Internal Server Error'),
            $this->ok(),
        ], ['retry_transient' => true, 'max_retries' => 2], $sleeps);

        $result = $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame(['message_id' => 1], $result);
        $this->assertSame([1], $sleeps);
    }

    public function test_retry_transient_retries_a_network_level_failure(): void
    {
        $request = new Request('POST', 'sendMessage');

        $client = $this->client([
            new ConnectException('cURL error 7: Failed to connect', $request),
            $this->ok(),
        ], ['retry_transient' => true, 'max_retries' => 2], $sleeps);

        $result = $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame(['message_id' => 1], $result);
        $this->assertSame([1], $sleeps);
    }

    // ─── Pluggable backoff: jitter, a custom strategy, an observer ─────────

    public function test_retry_jitter_adds_bounded_randomness_on_top_of_the_base_wait(): void
    {
        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 10]),
            $this->ok(),
        ], ['retry_jitter' => 1.0], $sleeps);

        $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertCount(1, $sleeps);
        $this->assertGreaterThanOrEqual(10, $sleeps[0]);
        $this->assertLessThanOrEqual(20, $sleeps[0]);
    }

    public function test_retry_strategy_overrides_the_computed_wait(): void
    {
        $seen = [];

        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 2]),
            $this->ok(),
        ], [
            'retry_strategy' => function (int $attempt, int $baseWait, TelegramApiException $e) use (&$seen) {
                $seen[] = [$attempt, $baseWait, $e->retryAfter()];
                return 5;
            },
        ], $sleeps);

        $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame([5], $sleeps);
        $this->assertSame([[0, 2, 2]], $seen);
    }

    public function test_on_retry_is_invoked_with_the_attempt_wait_method_and_exception(): void
    {
        $calls = [];

        $client = $this->client([
            $this->error(429, 'Too Many Requests', ['retry_after' => 2]),
            $this->ok(),
        ], [
            'on_retry' => function (int $attempt, int $wait, string $method, TelegramApiException $e) use (&$calls) {
                $calls[] = [$attempt, $wait, $method, $e->retryAfter()];
            },
        ], $sleeps);

        $client->post('sendMessage', ['chat_id' => 1]);

        $this->assertSame([[0, 2, 'sendMessage', 2]], $calls);
    }

    public function test_the_default_sleeper_is_a_plain_callable_wrapping_sleep(): void
    {
        $client = new HttpClient('123:token');

        $sleeper = (new \ReflectionProperty(HttpClient::class, 'sleeper'))->getValue($client);

        $this->assertIsCallable($sleeper);
    }

    // ─── The exception surface ────────────────────────────────────────────────

    public function test_exception_exposes_retry_after_from_telegrams_parameters(): void
    {
        $e = new TelegramApiException('Too Many Requests', 429, null, ['retry_after' => 12]);

        $this->assertSame(12, $e->retryAfter());
        $this->assertSame(429, $e->telegramErrorCode());
        $this->assertSame(['retry_after' => 12], $e->parameters());
    }

    public function test_exception_exposes_migrate_to_chat_id(): void
    {
        $e = new TelegramApiException('Group upgraded', 400, null, ['migrate_to_chat_id' => -100123]);

        $this->assertSame(-100123, $e->migrateToChatId());
        $this->assertNull($e->retryAfter());
    }

    public function test_exception_without_parameters_reports_nulls(): void
    {
        $e = new TelegramApiException('Bad Request', 400);

        $this->assertNull($e->retryAfter());
        $this->assertNull($e->migrateToChatId());
        $this->assertSame([], $e->parameters());
    }
}
