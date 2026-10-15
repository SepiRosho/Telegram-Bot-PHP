<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\WebhookCommand;
use PHPUnit\Framework\TestCase;

class WebhookCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->dir = sys_get_temp_dir() . '/devflow_webhook_test_' . uniqid();
        mkdir($this->dir . '/bootstrap', 0777, true);
        chdir($this->dir);
        unset($GLOBALS['fakeBot']);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        @unlink($this->dir . '/bootstrap/app.php');
        @rmdir($this->dir . '/bootstrap');
        @rmdir($this->dir);
        unset($GLOBALS['fakeBot']);
    }

    private function bootstrapWith(string $configPhp = '[]'): void
    {
        file_put_contents(
            $this->dir . '/bootstrap/app.php',
            "<?php\n\$GLOBALS['fakeBot'] = \Devflow\TelegramBot\Bot::fake({$configPhp});\n",
        );
    }

    private function runCommand(array $args, string $action = 'set'): string
    {
        ob_start();
        try {
            (new WebhookCommand($action))->execute($args);
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    private function setWebhookParams(): array
    {
        $calls = $GLOBALS['fakeBot']->http()->callsTo('setWebhook');
        $this->assertCount(1, $calls);

        return $calls[0]['params'];
    }

    public function test_allowed_updates_flag_is_sent_to_set_webhook(): void
    {
        $this->bootstrapWith();

        $out = $this->runCommand(['https://example.com/hook', '--allowed-updates=message,chat_member']);

        $params = $this->setWebhookParams();
        $this->assertSame('https://example.com/hook', $params['url']);
        $this->assertSame(['message', 'chat_member'], $params['allowed_updates']);
        $this->assertStringContainsString('message, chat_member', $out);
    }

    public function test_the_flag_may_come_before_the_url_and_use_a_space(): void
    {
        $this->bootstrapWith();

        $this->runCommand(['--allowed-updates', 'all', 'https://example.com/hook']);

        $params = $this->setWebhookParams();
        $this->assertSame('https://example.com/hook', $params['url']);
        $this->assertContains('message_reaction', $params['allowed_updates']);
    }

    public function test_the_config_value_is_the_default(): void
    {
        $this->bootstrapWith("['allowed_updates' => ['message', 'callback_query']]");

        $this->runCommand(['https://example.com/hook']);

        $this->assertSame(['message', 'callback_query'], $this->setWebhookParams()['allowed_updates']);
    }

    public function test_the_flag_beats_the_config(): void
    {
        $this->bootstrapWith("['allowed_updates' => ['message']]");

        $this->runCommand(['https://example.com/hook', '--allowed-updates=poll']);

        $this->assertSame(['poll'], $this->setWebhookParams()['allowed_updates']);
    }

    public function test_default_sends_an_empty_list_to_reset_telegrams_setting(): void
    {
        $this->bootstrapWith();

        $this->runCommand(['https://example.com/hook', '--allowed-updates=default']);

        $params = $this->setWebhookParams();
        $this->assertArrayHasKey('allowed_updates', $params);
        $this->assertSame([], $params['allowed_updates']);
    }

    public function test_without_the_option_the_parameter_is_not_sent(): void
    {
        $this->bootstrapWith();

        $this->runCommand(['https://example.com/hook']);

        $this->assertArrayNotHasKey('allowed_updates', $this->setWebhookParams());
    }

    public function test_info_shows_the_registered_allowed_updates(): void
    {
        $this->bootstrapWith();
        // FakeBot is built inside the bootstrap, which runs during execute(), so
        // queue the canned answer from a second bootstrap line.
        file_put_contents(
            $this->dir . '/bootstrap/app.php',
            "<?php\n\$GLOBALS['fakeBot'] = \Devflow\TelegramBot\Bot::fake();\n"
            . "\$GLOBALS['fakeBot']->http()->respond('getWebhookInfo', ['url' => 'https://example.com/hook', "
            . "'pending_update_count' => 0, 'allowed_updates' => ['message', 'chat_member']]);\n",
        );

        $out = $this->runCommand([], 'info');

        $this->assertStringContainsString('Allowed updates:', $out);
        $this->assertStringContainsString('message, chat_member', $out);
    }
}
