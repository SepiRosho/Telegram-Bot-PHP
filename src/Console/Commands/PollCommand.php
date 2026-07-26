<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Illuminate\Database\Capsule\Manager as Capsule;

class PollCommand
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

        // Load .env if Dotenv is available
        $envFile = $cwd . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile) && class_exists('Dotenv\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($cwd);
            $dotenv->safeLoad();
        }

        // Bootstrap registers Bot::init(), middleware, and handlers
        require $bootstrap;

        // Fail fast on a bad .env instead of letting the polling loop retry
        // against an unreachable database every 5 seconds.
        if (Bot::getInstance()->config('database')) {
            try {
                Capsule::connection()->getPdo();
            } catch (\PDOException $e) {
                $this->failOnDatabaseError($e);
            }
        }

        $this->line('');
        $this->line("  \033[32mdevflow poll\033[0m — long-polling mode");
        // Telegram refuses getUpdates while a webhook is registered.
        $this->line("  \033[33mNote:\033[0m delete your webhook first if one is set: \033[36mvendor/bin/devflow webhook:delete\033[0m");
        $this->line('  Press Ctrl+C to stop.');
        $this->line('');

        Bot::getInstance()->runPolling(function (\Throwable $e): void {
            $this->error($e->getMessage());
            $this->line('  Retrying in 5 seconds...');
        });
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
