<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class TextPatternRoutingTest extends TestCase
{
    private function makeApi(): TelegramApi
    {
        return new TelegramApi($this->createMock(HttpClientInterface::class));
    }

    private function textUpdate(string $text): Update
    {
        return Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => $text,
            ],
        ]);
    }

    private function bot(): BotInstance
    {
        return new BotInstance('123:token', [], $this->createMock(HttpClientInterface::class));
    }

    /** @return list<string> labels of the handlers that fired */
    private function dispatchAgainst(BotInstance $bot, string $text, array &$fired): array
    {
        $bot->router()->dispatch($this->textUpdate($text), $this->makeApi());

        return $fired;
    }

    public function test_single_argument_on_text_still_matches_everything(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText(function () use (&$fired) { $fired[] = 'all'; });

        $this->assertSame(['all'], $this->dispatchAgainst($bot, 'anything at all', $fired));
    }

    public function test_handler_class_name_as_single_argument_is_not_treated_as_a_pattern(): void
    {
        // A lone string means a HandlerInterface class name, not a pattern —
        // the ambiguity this overload has to preserve.
        $bot = $this->bot();
        $bot->onText(TextPatternSpyHandler::class);

        TextPatternSpyHandler::$calls = 0;
        $bot->router()->dispatch($this->textUpdate('whatever'), $this->makeApi());

        $this->assertSame(1, TextPatternSpyHandler::$calls);
    }

    public function test_wildcard_glob_pattern_matches_a_prefix(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('buy_*', function () use (&$fired) { $fired[] = 'buy'; });

        $this->assertSame(['buy'], $this->dispatchAgainst($bot, 'buy_42', $fired));
    }

    public function test_wildcard_glob_pattern_rejects_a_non_match(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('buy_*', function () use (&$fired) { $fired[] = 'buy'; });

        $this->assertSame([], $this->dispatchAgainst($bot, 'sell_42', $fired));
    }

    public function test_regex_pattern_matches(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('/^buy_\d+$/', function () use (&$fired) { $fired[] = 'buy'; });

        $this->assertSame(['buy'], $this->dispatchAgainst($bot, 'buy_42', $fired));
    }

    public function test_regex_pattern_rejects_a_near_miss(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('/^buy_\d+$/', function () use (&$fired) { $fired[] = 'buy'; });

        $this->assertSame([], $this->dispatchAgainst($bot, 'buy_abc', $fired));
    }

    public function test_a_literal_starting_with_a_slash_is_not_misread_as_a_regex(): void
    {
        // '/help me' is not a valid PCRE; isRegex() must reject it rather than
        // emit a "no ending delimiter" warning.
        $fired = [];
        $bot = $this->bot();
        $bot->onText('/help me', function () use (&$fired) { $fired[] = 'literal'; });

        $this->assertSame([], $this->dispatchAgainst($bot, 'something else', $fired));
    }

    public function test_first_matching_pattern_wins_and_dispatch_stops(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('buy_*', function () use (&$fired) { $fired[] = 'specific'; });
        $bot->onText(function () use (&$fired) { $fired[] = 'catchall'; });

        $this->assertSame(['specific'], $this->dispatchAgainst($bot, 'buy_1', $fired));
    }

    public function test_catch_all_still_receives_text_no_pattern_claimed(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('buy_*', function () use (&$fired) { $fired[] = 'specific'; });
        $bot->onText(function () use (&$fired) { $fired[] = 'catchall'; });

        $this->assertSame(['catchall'], $this->dispatchAgainst($bot, 'hello', $fired));
    }

    public function test_commands_never_match_a_text_pattern(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onText('/.*/', function () use (&$fired) { $fired[] = 'text'; });

        $update = Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => '/start',
                'entities'   => [['type' => 'bot_command', 'offset' => 0, 'length' => 6]],
            ],
        ]);

        $bot->router()->dispatch($update, $this->makeApi());

        $this->assertSame([], $fired);
    }

    public function test_callback_query_patterns_also_accept_a_regex(): void
    {
        $fired = [];
        $bot = $this->bot();
        $bot->onCallbackQuery('/^page_\d+$/', function () use (&$fired) { $fired[] = 'page'; });

        $bot->router()->dispatch(Update::fromArray([
            'update_id'      => 1,
            'callback_query' => [
                'id'   => 'cq1',
                'from' => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'data' => 'page_3',
            ],
        ]), $this->makeApi());

        $this->assertSame(['page'], $fired);
    }
}

class TextPatternSpyHandler implements \Devflow\TelegramBot\Handlers\HandlerInterface
{
    public static int $calls = 0;

    public function handle(\Devflow\TelegramBot\Context $ctx): void
    {
        self::$calls++;
    }
}
