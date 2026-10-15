<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\DoctorCommand;
use PHPUnit\Framework\TestCase;

/**
 * `doctor` flags a handler Telegram will never call: one registered for an
 * update type that is not in the webhook's (or polling's) allowed_updates.
 */
class DoctorAllowedUpdatesTest extends TestCase
{
    private function check(array $info, string $url, array $botConfig = []): string
    {
        $fake = Bot::fake(array_merge(['database' => false], $botConfig));

        return $this->runCheck($info, $url);
    }

    private function runCheck(array $info, string $url): string
    {
        $doctor = new DoctorCommand();
        $method = new \ReflectionMethod($doctor, 'checkAllowedUpdates');

        ob_start();
        $method->invoke($doctor, $info, $url);

        return (string) ob_get_clean();
    }

    public function test_a_chat_member_handler_with_the_default_set_is_flagged(): void
    {
        $this->check([], 'https://example.com/hook');
        Bot::onChatMember(fn() => null);

        $out = $this->runCheck(['allowed_updates' => []], 'https://example.com/hook');

        $this->assertStringContainsString('`chat_member`', $out);
        $this->assertStringContainsString('--allowed-updates=', $out);
        $this->assertStringContainsString('chat_member', substr($out, (int) strpos($out, '--allowed-updates=')));
    }

    public function test_it_passes_once_the_type_is_requested(): void
    {
        $this->check([], 'https://example.com/hook');
        Bot::onChatMember(fn() => null);
        Bot::onCommand('start', fn() => null);

        $out = $this->runCheck(['allowed_updates' => ['message', 'chat_member']], 'https://example.com/hook');

        $this->assertStringContainsString('covers every registered route', $out);
        $this->assertStringNotContainsString('will never fire', $out);
    }

    public function test_a_restricted_list_that_drops_messages_is_flagged(): void
    {
        $this->check([], 'https://example.com/hook');
        Bot::onCommand('start', fn() => null);

        $out = $this->runCheck(['allowed_updates' => ['callback_query']], 'https://example.com/hook');

        $this->assertStringContainsString('`message`', $out);
    }

    public function test_ordinary_routes_need_nothing_special_under_the_default_set(): void
    {
        $this->check([], 'https://example.com/hook');
        Bot::onCommand('start', fn() => null);
        Bot::onCallbackQuery(fn() => null);

        $out = $this->runCheck([], 'https://example.com/hook');

        $this->assertStringContainsString('covers every registered route', $out);
    }

    public function test_when_polling_the_bot_config_is_what_counts(): void
    {
        $this->check([], '', ['allowed_updates' => 'message,message_reaction']);
        Bot::onMessageReaction(fn() => null);

        $this->assertStringContainsString('covers every registered route', $this->runCheck([], ''));

        $this->check([], '');
        Bot::onMessageReaction(fn() => null);

        $out = $this->runCheck([], '');
        $this->assertStringContainsString('`message_reaction`', $out);
        $this->assertStringContainsString("'allowed_updates' =>", $out);
    }

    public function test_a_bot_with_no_routes_says_nothing(): void
    {
        $this->check([], 'https://example.com/hook');

        $this->assertSame('', $this->runCheck([], 'https://example.com/hook'));
    }
}
