<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MakeModelCommand;
use PHPUnit\Framework\TestCase;

class MakeModelCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_model_' . uniqid();
        mkdir($this->dir, 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        foreach (['OrderItem', 'Category'] as $name) {
            $file = $this->dir . "/app/Models/{$name}.php";
            if (file_exists($file)) {
                unlink($file);
            }
        }
        foreach ([$this->dir . '/app/Models', $this->dir . '/app', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_it_creates_a_model_extending_eloquent(): void
    {
        ob_start();
        (new MakeModelCommand())->execute(['OrderItem']);
        ob_get_clean();

        $file = $this->dir . '/app/Models/OrderItem.php';
        $this->assertFileExists($file);

        $body = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Models;', $body);
        $this->assertStringContainsString('class OrderItem extends Model', $body);
    }

    /**
     * The commented $table line names Eloquent's own auto-detected guess
     * (snake_case, pluralized), so uncommenting it without editing it is a
     * harmless no-op -- and it's the right starting point when it isn't.
     */
    public function test_the_commented_table_hint_matches_eloquents_own_naming_convention(): void
    {
        ob_start();
        (new MakeModelCommand())->execute(['OrderItem']);
        ob_get_clean();

        $body = (string) file_get_contents($this->dir . '/app/Models/OrderItem.php');
        $this->assertStringContainsString("// protected \$table = 'order_items';", $body);
    }

    public function test_generated_model_is_syntactically_valid(): void
    {
        ob_start();
        (new MakeModelCommand())->execute(['Category']);
        ob_get_clean();

        require $this->dir . '/app/Models/Category.php';

        $this->assertTrue(is_subclass_of(\App\Models\Category::class, \Illuminate\Database\Eloquent\Model::class));
    }
}
