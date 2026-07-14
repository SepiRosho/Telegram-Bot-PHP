<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MigrateCommand;
use Devflow\TelegramBot\Console\Commands\MigrateStatusCommand;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class MigrateCommandTest extends TestCase
{
    private string $projectDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->projectDir = sys_get_temp_dir() . '/devflow_migrate_test_' . uniqid();

        mkdir($this->projectDir . '/bootstrap', 0777, true);
        mkdir($this->projectDir . '/database/migrations', 0777, true);

        // A real file (not :memory:) so state persists across the separate
        // `require bootstrap/app.php` calls each execute() makes below —
        // matching how two real CLI invocations share the same on-disk DB.
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

        // Release the PDO handle first — Windows keeps the sqlite file
        // locked otherwise, and unlink()/rmdir() below would fail.
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
            // Windows can still hold the sqlite file open briefly after
            // disconnect(); this is best-effort temp-dir cleanup, not part
            // of what the test verifies, so tolerate a failed delete.
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    public function test_migrate_creates_package_and_project_tables(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        $this->assertTrue(Capsule::schema()->hasTable('telegram_users'));
        $this->assertTrue(Capsule::schema()->hasTable('bot_settings'));
        $this->assertTrue(Capsule::schema()->hasTable('telegram_broadcasts'));
        $this->assertTrue(Capsule::schema()->hasTable('widgets'));
        $this->assertTrue(Capsule::schema()->hasColumn('telegram_users', 'language'));
    }

    public function test_migrate_is_idempotent_and_status_reports_applied(): void
    {
        ob_start();
        (new MigrateCommand())->execute([]);
        ob_get_clean();

        // Second run should have nothing pending (no errors re-creating tables).
        ob_start();
        (new MigrateCommand())->execute([]);
        $secondRunOutput = ob_get_clean();
        $this->assertStringContainsString('Nothing to migrate.', $secondRunOutput);

        ob_start();
        (new MigrateStatusCommand())->execute([]);
        $status = ob_get_clean();

        $this->assertStringContainsString('create_telegram_users_table', $status);
        $this->assertStringContainsString('create_widgets_table', $status);
        $this->assertStringNotContainsString('Pending', $status);
    }
}
