<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MigrateCommand;
use Devflow\TelegramBot\Console\Commands\MigrateRollbackCommand;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class MigrateRollbackCommandTest extends TestCase
{
    private string $projectDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->projectDir = sys_get_temp_dir() . '/devflow_rollback_test_' . uniqid();

        mkdir($this->projectDir . '/bootstrap', 0777, true);
        mkdir($this->projectDir . '/database/migrations', 0777, true);

        $dbFile = $this->projectDir . '/database.sqlite';
        touch($dbFile);

        file_put_contents($this->projectDir . '/bootstrap/app.php', <<<PHP
        <?php
        use Illuminate\Database\Capsule\Manager as Capsule;
        \$capsule = new Capsule();
        \$capsule->addConnection(['driver' => 'sqlite', 'database' => '{$dbFile}']);
        \$capsule->setAsGlobal();
        \$capsule->bootEloquent();
        PHP);

        file_put_contents($this->projectDir . '/database/migrations/2099_01_01_000000_create_widgets_table.php', <<<'PHP'
        <?php
        use Illuminate\Database\Capsule\Manager as Capsule;
        return new class {
            public function up(): void {
                Capsule::schema()->create('widgets', function ($table) {
                    $table->id();
                    $table->string('name');
                });
            }
            public function down(): void {
                Capsule::schema()->dropIfExists('widgets');
            }
        };
        PHP);

        chdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (Capsule::connection() !== null) {
            Capsule::connection()->disconnect();
        }

        $this->removeDirectory($this->projectDir);
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

    public function test_nothing_to_roll_back_when_never_migrated(): void
    {
        ob_start();
        (new MigrateRollbackCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Nothing to roll back.', $output);
    }

    public function test_rollback_reverses_the_only_batch(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        $this->assertTrue(Capsule::schema()->hasTable('widgets'));
        $this->assertTrue(Capsule::schema()->hasTable('telegram_users'));

        ob_start();
        (new MigrateRollbackCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Rolled back: 2099_01_01_000000_create_widgets_table', $output);
        $this->assertFalse(Capsule::schema()->hasTable('widgets'));
        $this->assertFalse(Capsule::schema()->hasTable('telegram_users'));
        $this->assertSame(0, (int) Capsule::table('migrations')->count());
    }

    public function test_default_step_only_rolls_back_the_most_recent_batch(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        file_put_contents($this->projectDir . '/database/migrations/2099_02_02_000000_create_gadgets_table.php', <<<'PHP'
        <?php
        use Illuminate\Database\Capsule\Manager as Capsule;
        return new class {
            public function up(): void {
                Capsule::schema()->create('gadgets', function ($table) {
                    $table->id();
                });
            }
            public function down(): void {
                Capsule::schema()->dropIfExists('gadgets');
            }
        };
        PHP);

        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        $this->assertTrue(Capsule::schema()->hasTable('gadgets'));
        $this->assertTrue(Capsule::schema()->hasTable('widgets'));

        ob_start();
        (new MigrateRollbackCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Rolled back: 2099_02_02_000000_create_gadgets_table', $output);
        $this->assertFalse(Capsule::schema()->hasTable('gadgets'));
        // The earlier batch (widgets, telegram_users, ...) must survive a
        // default (single-batch) rollback.
        $this->assertTrue(Capsule::schema()->hasTable('widgets'));
        $this->assertTrue(Capsule::schema()->hasTable('telegram_users'));
    }

    public function test_step_option_rolls_back_multiple_batches(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        file_put_contents($this->projectDir . '/database/migrations/2099_02_02_000000_create_gadgets_table.php', <<<'PHP'
        <?php
        use Illuminate\Database\Capsule\Manager as Capsule;
        return new class {
            public function up(): void { Capsule::schema()->create('gadgets', function ($table) { $table->id(); }); }
            public function down(): void { Capsule::schema()->dropIfExists('gadgets'); }
        };
        PHP);

        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        ob_start();
        (new MigrateRollbackCommand())->execute(['--step=2']);
        $output = ob_get_clean();

        $this->assertMatchesRegularExpression('/Rolled back \d+ migration\(s\)\./', $output);
        $this->assertFalse(Capsule::schema()->hasTable('gadgets'));
        $this->assertFalse(Capsule::schema()->hasTable('widgets'));
        $this->assertFalse(Capsule::schema()->hasTable('telegram_users'));
    }

    public function test_all_option_rolls_back_every_batch(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        file_put_contents($this->projectDir . '/database/migrations/2099_02_02_000000_create_gadgets_table.php', <<<'PHP'
        <?php
        use Illuminate\Database\Capsule\Manager as Capsule;
        return new class {
            public function up(): void { Capsule::schema()->create('gadgets', function ($table) { $table->id(); }); }
            public function down(): void { Capsule::schema()->dropIfExists('gadgets'); }
        };
        PHP);

        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        ob_start();
        (new MigrateRollbackCommand())->execute(['--all']);
        ob_get_clean();

        $this->assertSame(0, (int) Capsule::table('migrations')->count());
        $this->assertFalse(Capsule::schema()->hasTable('gadgets'));
        $this->assertFalse(Capsule::schema()->hasTable('widgets'));
    }

    public function test_a_missing_migration_file_is_skipped_without_crashing(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        unlink($this->projectDir . '/database/migrations/2099_01_01_000000_create_widgets_table.php');

        ob_start();
        (new MigrateRollbackCommand())->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('migration file not found, skipped', $output);
        // Its tracking row must survive since down() never ran for it.
        $this->assertSame(
            1,
            (int) Capsule::table('migrations')->where('migration', '2099_01_01_000000_create_widgets_table')->count(),
        );
    }
}
