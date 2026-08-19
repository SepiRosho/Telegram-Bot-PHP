<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MakeKeyboardCommand;
use PHPUnit\Framework\TestCase;

class MakeKeyboardCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_kbd_' . uniqid();
        mkdir($this->dir, 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        $file = $this->dir . '/app/Keyboards/MainMenuKeyboard.php';
        if (file_exists($file)) {
            unlink($file);
        }
        foreach ([$this->dir . '/app/Keyboards', $this->dir . '/app', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_it_creates_a_keyboard_class_implementing_the_interface(): void
    {
        ob_start();
        (new MakeKeyboardCommand())->execute(['MainMenuKeyboard']);
        ob_get_clean();

        $file = $this->dir . '/app/Keyboards/MainMenuKeyboard.php';
        $this->assertFileExists($file);

        $body = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Keyboards;', $body);
        $this->assertStringContainsString('class MainMenuKeyboard implements KeyboardInterface', $body);
        $this->assertStringContainsString('public static function build(array $vars = []): array', $body);
    }

    public function test_generated_keyboard_is_syntactically_valid_and_callable(): void
    {
        ob_start();
        (new MakeKeyboardCommand())->execute(['MainMenuKeyboard']);
        ob_get_clean();

        require $this->dir . '/app/Keyboards/MainMenuKeyboard.php';

        $result = \App\Keyboards\MainMenuKeyboard::build();
        $this->assertArrayHasKey('inline_keyboard', $result);
    }
}
