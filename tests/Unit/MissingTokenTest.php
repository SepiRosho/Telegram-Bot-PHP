<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
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
}
