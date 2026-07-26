<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Illuminate\Database\Capsule\Manager as Capsule;

class MigrateStatusCommand
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
            $applied = Capsule::schema()->hasTable('migrations')
                ? Capsule::table('migrations')->pluck('batch', 'migration')->all()
                : [];

            $files = array_merge(
                $this->phpFilesIn(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'),
                $this->phpFilesIn($cwd . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'),
            );
            usort($files, fn(string $a, string $b) => basename($a) <=> basename($b));

            if ($files === []) {
                $this->line('No migration files found.');
                return;
            }

            $untracked = 0;

            foreach ($files as $path) {
                $name = basename($path, '.php');

                if (isset($applied[$name])) {
                    $this->success("{$name}  (batch {$applied[$name]})");
                    continue;
                }

                $table = $this->targetTable($name);

                // Tables created by hand (e.g. the bundled .sql files, per
                // docs/06-database.md) never get a `migrations` row, so a
                // bare "Pending" here would be actively misleading.
                if ($table !== null && Capsule::schema()->hasTable($table)) {
                    $untracked++;
                    $this->line("\033[36mUntracked\033[0m {$name}  (table exists outside migration tracking — verify schema manually)");
                    continue;
                }

                $this->line("\033[33mPending\033[0m   {$name}");
            }

            if ($untracked > 0) {
                $this->line('');
                $this->line("\033[33mWarning:\033[0m {$untracked} migration(s) reported as Untracked. Their target table already exists but was never recorded in the `migrations` table — likely from importing the bundled .sql files by hand. Verify the schema matches before running `migrate`.");
            }
        } catch (\PDOException $e) {
            $this->failOnDatabaseError($e);
        }
    }

    /**
     * Bundled and project migrations follow a `<prefix>_create_<table>_table`
     * naming convention. Anything that doesn't match (e.g. an
     * `..._add_x_to_y_table` migration) can't be mapped to a single target
     * table with confidence, so it's left for the caller to fall back to Pending.
     */
    private function targetTable(string $migrationName): ?string
    {
        $withoutPrefix = preg_replace('/^\d[\d_]*_/', '', $migrationName, 1) ?? $migrationName;

        return preg_match('/^create_(.+)_table$/', $withoutPrefix, $matches) ? $matches[1] : null;
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
