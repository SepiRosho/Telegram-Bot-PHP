<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\MissingTokenHttpClient;
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\MissingTokenException;
use PHPUnit\Framework\TestCase;

class MissingTokenTest extends TestCase
{
    /** @dataProvider emptyTokens */
    public function test_an_empty_token_is_reported_as_a_missing_token(?string $token): void
    {
        // Before this, Bot::init(env('BOT_TOKEN')) with an unset BOT_TOKEN
        // failed with a TypeError naming neither the token nor the .env file.
        $this->expectException(MissingTokenException::class);
        $this->expectExceptionMessageMatches('/BOT_TOKEN/');

        new BotInstance($token, [], $this->createMock(HttpClientInterface::class));
    }

    public static function emptyTokens(): array
    {
        return [
            'null'       => [null],
            'empty'      => [''],
            'whitespace' => ['   '],
        ];
    }

    public function test_the_facade_reports_it_too(): void
    {
        $this->expectException(MissingTokenException::class);

        Bot::init(null);
    }

    public function test_a_real_token_constructs_normally(): void
    {
        $bot = new BotInstance('123456:ABC', [], $this->createMock(HttpClientInterface::class));

        $this->assertInstanceOf(BotInstance::class, $bot);
    }

    public function test_the_message_names_the_env_var_the_caller_reads(): void
    {
        // Laravel's config reads TELEGRAM_BOT_TOKEN; telling a Laravel user to
        // set BOT_TOKEN sends them to a variable nothing looks at.
        $message = (new MissingTokenException('TELEGRAM_BOT_TOKEN'))->getMessage();

        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN', $message);
        $this->assertStringNotContainsString(' BOT_TOKEN', $message);
    }

    public function test_a_bot_without_a_token_only_fails_when_it_calls_the_api(): void
    {
        // What the Laravel provider hands the container when no token is set:
        // routes register fine (so `artisan` boots), the first API call throws.
        $bot = new BotInstance('missing-token', [], new MissingTokenHttpClient('TELEGRAM_BOT_TOKEN'));
        $bot->onCommand('start', fn() => null);

        $this->expectException(MissingTokenException::class);
        $this->expectExceptionMessageMatches('/TELEGRAM_BOT_TOKEN/');

        $bot->api()->getMe();
    }
}
