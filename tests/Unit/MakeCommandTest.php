<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MakeHandlerCommand;
use PHPUnit\Framework\TestCase;

class MakeCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_make_' . uniqid();
        mkdir($this->dir, 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        $file = $this->dir . '/app/Commands/TestOne.php';
        if (file_exists($file)) {
            unlink($file);
        }
        foreach ([$this->dir . '/app/Commands', $this->dir . '/app', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    /**
     * getTargetDirectory() implementations build paths with a hardcoded '/'
     * (getcwd() . '/app/Commands'), so on Windows the created file's absolute
     * path mixes '/' and '\'. relativePath() used to strip only
     * DIRECTORY_SEPARATOR off the front, leaving a stray leading '/' in the
     * printed confirmation on Windows.
     */
    public function test_success_message_has_no_leading_slash_on_the_relative_path(): void
    {
        ob_start();
        (new MakeHandlerCommand())->execute(['TestOne']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('app/Commands', $output);
        $this->assertDoesNotMatchRegularExpression('#[:\s][/\\\\]app[/\\\\]Commands#', $output);
    }
}
