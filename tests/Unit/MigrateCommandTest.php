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

    /**
     * A project whose tables were created by importing the bundled .sql
     * files by hand (docs/06-database.md) never gets a `migrations` table,
     * so every bundled migration looked "Pending" even though its table
     * already existed. It should be reported as "Untracked" instead.
     */
    public function test_status_reports_untracked_for_tables_created_outside_the_migration_runner(): void
    {
        require $this->projectDir . '/bootstrap/app.php';

        // Simulate a hand-imported table: it exists, but no `migrations`
        // table (and therefore no tracking row) exists at all.
        Capsule::schema()->create('widgets', function ($table) {
            $table->id();
            $table->string('name');
        });

        ob_start();
        (new MigrateStatusCommand())->execute([]);
        $status = ob_get_clean();

        $this->assertStringContainsString("\033[36mUntracked\033[0m 2099_01_01_000000_create_widgets_table  (table exists outside migration tracking — verify schema manually)", $status);
        $this->assertStringContainsString('1 migration(s) reported as Untracked', $status);

        // create_telegram_users_table's table doesn't exist yet, so it must
        // still fall back to the ordinary Pending label.
        $this->assertStringContainsString("\033[33mPending\033[0m   0001_01_01_000001_create_telegram_users_table", $status);
    }

    /**
     * A migration that doesn't follow the `create_<table>_table` naming
     * convention (e.g. one that only adds columns) can't be confidently
     * mapped to a single target table, so it must stay Pending even when
     * some unrelated table already exists.
     */
    public function test_status_falls_back_to_pending_when_target_table_cannot_be_determined(): void
    {
        file_put_contents(
            $this->projectDir . '/database/migrations/2099_03_03_000000_add_something_to_gadgets_table.php',
            <<<'PHP'
            <?php
            return new class {
                public function up(): void {}
                public function down(): void {}
            };
            PHP,
        );

        require $this->projectDir . '/bootstrap/app.php';

        // The `gadgets` table exists, but this migration's name doesn't
        // follow the create_<table>_table convention, so its target table
        // can't be confidently derived — it must stay Pending, not Untracked.
        Capsule::schema()->create('gadgets', function ($table) {
            $table->id();
            $table->string('name');
        });

        ob_start();
        (new MigrateStatusCommand())->execute([]);
        $status = ob_get_clean();

        $this->assertStringContainsString("\033[33mPending\033[0m   2099_03_03_000000_add_something_to_gadgets_table", $status);
        $this->assertStringNotContainsString('Untracked', $status);
    }

    /**
     * ReportsDatabaseErrors::databaseConnectionErrorMessage() builds its
     * message from the resolved connection config rather than a generic
     * template — checked directly via reflection since triggering it through
     * execute() would require an actually-unreachable database and ends in
     * exit(1), which would kill the test runner.
     */
    public function test_database_connection_error_message_uses_resolved_config(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'database' => 'my_bot',
            'username' => 'root',
            'password' => '',
        ]);
        $capsule->setAsGlobal();

        $command = new MigrateCommand();
        $method = new \ReflectionMethod($command, 'databaseConnectionErrorMessage');
        $method->setAccessible(true);

        $this->assertSame(
            'Could not connect to database "my_bot" at 127.0.0.1:3306 — check DB_DATABASE / DB_HOST / DB_USERNAME / DB_PASSWORD in your .env',
            $method->invoke($command),
        );
    }
}
