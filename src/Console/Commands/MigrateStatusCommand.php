<?php

namespace Devflow\TelegramBot\Console\Commands;

use Illuminate\Database\Capsule\Manager as Capsule;

class MigrateStatusCommand
{
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

        foreach ($files as $path) {
            $name = basename($path, '.php');
            if (isset($applied[$name])) {
                $this->success("{$name}  (batch {$applied[$name]})");
            } else {
                $this->line("\033[33mPending\033[0m   {$name}");
            }
        }
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
