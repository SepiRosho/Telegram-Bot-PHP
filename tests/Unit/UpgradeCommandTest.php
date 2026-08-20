<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\UpgradeCommand;
use PHPUnit\Framework\TestCase;

class UpgradeCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_upgrade_' . uniqid();

        mkdir($this->dir . '/bootstrap', 0755, true);
        mkdir($this->dir . '/app', 0755, true);

        // A "legacy" bootstrap/app.php that predates language_code,
        // lang_auto_fallback, user_defaults and Emoji::registerMany.
        file_put_contents($this->dir . '/bootstrap/app.php', <<<'PHP'
        <?php
        use Devflow\TelegramBot\Bot;
        Bot::init('token', ['database' => true]);
        PHP);

        file_put_contents($this->dir . '/.env', "BOT_TOKEN=abc\nADMIN_CHAT_ID=1\n");
        file_put_contents($this->dir . '/.env.example', "BOT_TOKEN=\nADMIN_CHAT_ID=\n");

        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    public function test_dry_run_reports_missing_pieces_without_touching_the_filesystem(): void
    {
        ob_start();
        (new UpgradeCommand())->execute(['--dry-run']);
        $output = ob_get_clean();

        $this->assertStringContainsString('would fix (dry run)', $output);
        $this->assertDirectoryDoesNotExist($this->dir . '/app/Keyboards');
        $this->assertFileDoesNotExist($this->dir . '/app/Emojis.php');
        $this->assertStringNotContainsString('LANGUAGE_CODE', (string) file_get_contents($this->dir . '/.env'));
    }

    public function test_it_creates_the_missing_keyboards_directory(): void
    {
        ob_start();
        (new UpgradeCommand())->execute([]);
        ob_get_clean();

        $this->assertDirectoryExists($this->dir . '/app/Keyboards');
    }

    public function test_it_creates_the_missing_emojis_registry(): void
    {
        ob_start();
        (new UpgradeCommand())->execute([]);
        ob_get_clean();

        $file = $this->dir . '/app/Emojis.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('return [];', (string) file_get_contents($file));
    }

    public function test_it_does_not_overwrite_an_existing_emojis_registry(): void
    {
        $file = $this->dir . '/app/Emojis.php';
        file_put_contents($file, "<?php\nreturn ['fire' => ['id' => '1', 'fallback' => '🔥']];\n");

        ob_start();
        (new UpgradeCommand())->execute([]);
        ob_get_clean();

        $this->assertStringContainsString('fire', (string) file_get_contents($file));
    }

    public function test_it_appends_missing_env_keys_without_touching_existing_ones(): void
    {
        ob_start();
        (new UpgradeCommand())->execute([]);
        ob_get_clean();

        $env = (string) file_get_contents($this->dir . '/.env');
        $this->assertStringContainsString('BOT_TOKEN=abc', $env);
        $this->assertStringContainsString('LANGUAGE_CODE=auto', $env);
        $this->assertStringContainsString('LANG_AUTO_FALLBACK=false', $env);

        $example = (string) file_get_contents($this->dir . '/.env.example');
        $this->assertStringContainsString('LANGUAGE_CODE=auto', $example);
        $this->assertStringContainsString('LANG_AUTO_FALLBACK=false', $example);
    }

    public function test_it_reports_the_missing_bootstrap_wiring_but_does_not_edit_the_file(): void
    {
        $before = (string) file_get_contents($this->dir . '/bootstrap/app.php');

        ob_start();
        (new UpgradeCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertSame($before, (string) file_get_contents($this->dir . '/bootstrap/app.php'));
        $this->assertStringContainsString('bootstrap/app.php is missing', $output);
        $this->assertStringContainsString('language_code', $output);
        $this->assertStringContainsString('lang_auto_fallback', $output);
        $this->assertStringContainsString('user_defaults', $output);
        $this->assertStringContainsString('Emoji::registerMany', $output);
    }

    public function test_a_second_run_reports_the_filesystem_checks_as_already_satisfied(): void
    {
        ob_start();
        (new UpgradeCommand())->execute([]);
        ob_get_clean();

        ob_start();
        (new UpgradeCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('app/Keyboards/ exists', $output);
        $this->assertStringContainsString('app/Emojis.php exists', $output);
        $this->assertStringContainsString('has LANGUAGE_CODE / LANG_AUTO_FALLBACK', $output);
    }
}
