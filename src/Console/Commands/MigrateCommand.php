<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Runs pending PHP migrations (both the ones bundled with this package,
 * under database/migrations/, and the project's own under
 * <project>/database/migrations/), tracked in a `migrations` table so
 * library upgrades that alter telegram_users/bot_settings/telegram_broadcasts
 * ship as a migration instead of a manual ALTER note.
 */
class MigrateCommand
{
    use ReportsDatabaseErrors;

    public function execute(array $args): void
    {
        $cwd = getcwd();
        $bootstrap = $cwd . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (!file_exists($bootstrap)) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        $envFile = $cwd . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile) && class_exists('Dotenv\Dotenv')) {
            \Dotenv\Dotenv::createImmutable($cwd)->safeLoad();
        }

        require $bootstrap;

        try {
            $this->ensureMigrationsTable();

            $pending = $this->pendingMigrations($cwd);

            if ($pending === []) {
                $this->line('Nothing to migrate.');
                return;
            }

            $batch = (int) (Capsule::table('migrations')->max('batch') ?? 0) + 1;

            foreach ($pending as $path) {
                $name = basename($path, '.php');
                $this->line("Migrating: {$name}");

                (require $path)->up();

                Capsule::table('migrations')->insert([
                    'migration' => $name,
                    'batch'     => $batch,
                    'run_at'    => date('Y-m-d H:i:s'),
                ]);

                $this->success("Migrated:  {$name}");
            }
        } catch (\PDOException $e) {
            $this->failOnDatabaseError($e);
        }
    }

    private function ensureMigrationsTable(): void
    {
        if (Capsule::schema()->hasTable('migrations')) {
            return;
        }

        Capsule::schema()->create('migrations', function ($table) {
            $table->id();
            $table->string('migration');
            $table->unsignedInteger('batch');
            $table->timestamp('run_at')->useCurrent();
        });
    }

    /** @return list<string> Absolute paths of migration files not yet recorded, in filename order. */
    private function pendingMigrations(string $cwd): array
    {
        $files = array_merge(
            $this->phpFilesIn($this->packageMigrationsPath()),
            $this->phpFilesIn($cwd . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'),
        );

        usort($files, fn(string $a, string $b) => basename($a) <=> basename($b));

        $applied = Capsule::table('migrations')->pluck('migration')->all();

        return array_values(array_filter(
            $files,
            fn(string $f) => !in_array(basename($f, '.php'), $applied, true),
        ));
    }

    private function packageMigrationsPath(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    /** @return list<string> */
    private function phpFilesIn(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        return glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
