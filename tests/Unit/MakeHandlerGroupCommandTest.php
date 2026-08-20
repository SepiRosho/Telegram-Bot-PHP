<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MakeHandlerGroupCommand;
use PHPUnit\Framework\TestCase;

class MakeHandlerGroupCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_handler_' . uniqid();
        mkdir($this->dir, 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        $file = $this->dir . '/app/Handlers/ShopHandlers.php';
        if (file_exists($file)) {
            unlink($file);
        }
        foreach ([$this->dir . '/app/Handlers', $this->dir . '/app', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_it_creates_a_handler_group_class(): void
    {
        ob_start();
        (new MakeHandlerGroupCommand())->execute(['ShopHandlers']);
        ob_get_clean();

        $file = $this->dir . '/app/Handlers/ShopHandlers.php';
        $this->assertFileExists($file);

        $body = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Handlers;', $body);
        $this->assertStringContainsString('class ShopHandlers', $body);
        $this->assertStringContainsString('public static function register(): void', $body);
    }

    public function test_generated_handler_group_is_syntactically_valid_and_callable(): void
    {
        ob_start();
        (new MakeHandlerGroupCommand())->execute(['ShopHandlers']);
        ob_get_clean();

        require $this->dir . '/app/Handlers/ShopHandlers.php';

        \App\Handlers\ShopHandlers::register();
        $this->assertTrue(true);
    }
}
