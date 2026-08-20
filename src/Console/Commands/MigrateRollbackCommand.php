<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Reverses previously-applied migration batches by calling each one's down().
 * Mirrors MigrateCommand's batch bookkeeping (max(batch)+1 on the way up) so
 * the two stay symmetric: no flags rolls back the single most recent batch,
 * --step=N rolls back the N most recent batches, --all rolls back everything.
 */
class MigrateRollbackCommand
{
    use ReportsDatabaseErrors;

    private const KNOWN_FLAGS_WITH_VALUE = ['--step'];

    public function execute(array $args): void
    {
        $cwd       = getcwd();
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

        $options = $this->parseOptions($args);

        try {
            if (!Capsule::schema()->hasTable('migrations')) {
                $this->line('Nothing to roll back.');
                return;
            }

            $batches = Capsule::table('migrations')
                ->orderByDesc('batch')
                ->distinct()
                ->pluck('batch')
                ->all();

            if ($batches === []) {
                $this->line('Nothing to roll back.');
                return;
            }

            $targetBatches = $options['all'] ? $batches : array_slice($batches, 0, $options['step']);

            $rolledBack = 0;

            foreach ($targetBatches as $batch) {
                $migrations = Capsule::table('migrations')
                    ->where('batch', $batch)
                    ->orderByDesc('migration')
                    ->pluck('migration');

                foreach ($migrations as $name) {
                    $path = $this->findMigrationFile($name, $cwd);

                    if ($path === null) {
                        $this->error("Rolling back: {$name} — migration file not found, skipped (its `migrations` row was left in place)");
                        continue;
                    }

                    $this->line("Rolling back: {$name}");

                    (require $path)->down();

                    Capsule::table('migrations')->where('migration', $name)->delete();

                    $this->success("Rolled back: {$name}");
                    $rolledBack++;
                }
            }

            $rolledBack === 0
                ? $this->line('Nothing to roll back.')
                : $this->line("Rolled back {$rolledBack} migration(s).");
        } catch (\PDOException $e) {
            $this->failOnDatabaseError($e);
        }
    }

    /** @return array{step: int, all: bool} */
    private function parseOptions(array $args): array
    {
        $all  = false;
        $step = 1;

        foreach ($args as $arg) {
            if ($arg === '--all') {
                $all = true;
                continue;
            }

            if (str_starts_with($arg, '--step=')) {
                $step = max(1, (int) substr($arg, 7));
                continue;
            }

            $this->error("Unknown option: {$arg}");
            $this->line('  Usage: vendor/bin/devflow migrate:rollback [--step=N] [--all]');
            exit(1);
        }

        return ['step' => $step, 'all' => $all];
    }

    private function findMigrationFile(string $name, string $cwd): ?string
    {
        foreach ([$this->packageMigrationsPath(), $cwd . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'] as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $name . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function packageMigrationsPath(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
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
