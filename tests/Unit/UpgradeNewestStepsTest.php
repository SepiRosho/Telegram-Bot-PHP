<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\NewProjectCommand;
use Devflow\TelegramBot\Console\Commands\UpgradeCommand;
use PHPUnit\Framework\TestCase;

/**
 * Anything a freshly scaffolded project contains must also be something
 * `devflow upgrade` can bring an older project up to — and a project made by
 * the current scaffold must come out of `upgrade` with nothing left to do.
 */
class UpgradeNewestStepsTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_upgrade_new_' . uniqid();
        mkdir($this->dir . '/bootstrap', 0755, true);
        mkdir($this->dir . '/public', 0755, true);
        mkdir($this->dir . '/app', 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->remove($this->dir);
    }

    private function remove(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->remove($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function upgrade(array $args = []): string
    {
        ob_start();
        (new UpgradeCommand())->execute($args);

        return (string) ob_get_clean();
    }

    private function template(string $method): string
    {
        return (string) (new \ReflectionMethod(NewProjectCommand::class, $method))
            ->invoke(new NewProjectCommand());
    }

    public function test_an_old_project_is_told_about_the_new_wiring_and_gets_the_env_keys(): void
    {
        file_put_contents($this->dir . '/bootstrap/app.php', "<?php\nBot::init('t', ['database' => true]);\n");
        file_put_contents($this->dir . '/public/webhook.php', "<?php\ntry { Bot::run(); } catch (\\Throwable \$e) { botLog('crash'); }\n");
        file_put_contents($this->dir . '/.env', "BOT_TOKEN=abc\n");
        file_put_contents($this->dir . '/.env.example', "BOT_TOKEN=\n");

        $out = $this->upgrade();

        $this->assertStringContainsString("bootstrap/app.php is missing allowed_updates", $out);
        $this->assertStringContainsString("bootstrap/app.php is missing respond_early", $out);
        $this->assertStringContainsString('public/webhook.php reports every bad-secret', $out);
        $this->assertStringContainsString('WebhookException', $out, 'the paste-in snippet is shown');

        // .env keys are purely additive, so they are applied without asking.
        foreach (['.env', '.env.example'] as $file) {
            $contents = file_get_contents($this->dir . '/' . $file);
            $this->assertMatchesRegularExpression('/^ALLOWED_UPDATES=?$/m', $contents);
            $this->assertMatchesRegularExpression('/^RESPOND_EARLY=false?$/m', $contents);
        }
    }

    public function test_the_1_18_schema_note_reaches_old_projects(): void
    {
        file_put_contents($this->dir . '/bootstrap/app.php', "<?php\n");

        $this->assertStringContainsString('referral_code is now UNIQUE', $this->upgrade());
    }

    public function test_a_project_made_by_the_current_scaffold_needs_nothing(): void
    {
        file_put_contents($this->dir . '/bootstrap/app.php', $this->template('bootstrapApp'));
        file_put_contents($this->dir . '/public/webhook.php', $this->template('publicWebhook'));
        $env = $this->template('envExample');
        file_put_contents($this->dir . '/.env', $env);
        file_put_contents($this->dir . '/.env.example', $env);
        mkdir($this->dir . '/app/Keyboards', 0755, true);
        file_put_contents($this->dir . '/app/Emojis.php', "<?php\nreturn [];\n");

        $out = $this->upgrade();

        $this->assertStringNotContainsString(' is missing', $out, $out);
        $this->assertStringNotContainsString('add by hand', $out, $out);
    }

    public function test_the_scaffold_ships_the_new_options(): void
    {
        $bootstrap = $this->template('bootstrapApp');
        $this->assertStringContainsString("'allowed_updates'", $bootstrap);
        $this->assertStringContainsString("'respond_early'", $bootstrap);

        $env = $this->template('envExample');
        $this->assertMatchesRegularExpression('/^ALLOWED_UPDATES=?$/m', $env);
        $this->assertMatchesRegularExpression('/^RESPOND_EARLY=false?$/m', $env);
    }

    public function test_the_scaffolded_webhook_entry_point_does_not_page_the_admin_for_a_bad_secret(): void
    {
        $webhook = $this->template('publicWebhook');

        $rejection = strpos($webhook, 'WebhookException');
        $generic   = strpos($webhook, '\\Throwable');
        $alert     = strpos($webhook, 'botLog(');

        $this->assertNotFalse($rejection);
        $this->assertLessThan($generic, $rejection, 'WebhookException must be caught before the generic handler');
        $this->assertLessThan($alert, $rejection);
        $this->assertStringNotContainsString('botLog', substr($webhook, $rejection, $generic - $rejection));
    }
}
