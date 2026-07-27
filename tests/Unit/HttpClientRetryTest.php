<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\InputFile;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use PHPUnit\Framework\TestCase;

class HttpClientRetryTest extends TestCase
{
    /** shouldRetry() is where the 429 policy lives; sleeping through a real one isn't testable. */
    private function shouldRetry(HttpClient $client, ?int $retryAfter, array $params, int $attempt): bool
    {
        return (new \ReflectionMethod(HttpClient::class, 'shouldRetry'))
            ->invoke($client, $retryAfter, $params, $attempt);
    }

    public function test_a_retry_after_is_retried_while_attempts_remain(): void
    {
        $client = new HttpClient('123:token', ['max_retries' => 2]);

        $this->assertTrue($this->shouldRetry($client, 3, ['chat_id' => 1], 0));
        $this->assertTrue($this->shouldRetry($client, 3, ['chat_id' => 1], 1));
    }

    public function test_retries_stop_once_max_retries_is_reached(): void
    {
        $client = new HttpClient('123:token', ['max_retries' => 2]);

        $this->assertFalse($this->shouldRetry($client, 3, ['chat_id' => 1], 2));
    }

    public function test_max_retries_zero_disables_retrying(): void
    {
        $client = new HttpClient('123:token', ['max_retries' => 0]);

        $this->assertFalse($this->shouldRetry($client, 1, ['chat_id' => 1], 0));
    }

    public function test_an_error_without_retry_after_is_never_retried(): void
    {
        $client = new HttpClient('123:token');

        $this->assertFalse($this->shouldRetry($client, null, ['chat_id' => 1], 0));
    }

    public function test_an_absurdly_long_retry_after_is_thrown_rather_than_waited_out(): void
    {
        $client = new HttpClient('123:token', ['max_retry_after' => 60]);

        $this->assertTrue($this->shouldRetry($client, 60, ['chat_id' => 1], 0));
        $this->assertFalse($this->shouldRetry($client, 61, ['chat_id' => 1], 0));
    }

    public function test_uploads_are_never_retried_because_the_stream_is_consumed(): void
    {
        $client = new HttpClient('123:token', ['max_retries' => 3]);
        $params = ['chat_id' => 1, 'document' => InputFile::contents('bytes', 'a.pdf')];

        $this->assertFalse($this->shouldRetry($client, 2, $params, 0));
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
