<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Broadcasting\BroadcastRunner;
use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;

class BroadcastRunCommand
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
            $dotenv = \Dotenv\Dotenv::createImmutable($cwd);
            $dotenv->safeLoad();
        }

        require $bootstrap;

        // Rate: 1–30 msgs/s, default 25. Gives a buffer below Telegram's 30 msg/s hard limit.
        $ratePerSecond = (int) ($_ENV['BROADCAST_RATE'] ?? $_SERVER['BROADCAST_RATE'] ?? getenv('BROADCAST_RATE') ?: 25);

        $runner = new BroadcastRunner(
            Bot::getInstance(),
            fn(string $kind, string $message) => $kind === 'success' ? $this->success($message) : $this->line($message),
        );

        try {
            $runner->runPending($ratePerSecond);
        } catch (\PDOException $e) {
            $this->failOnDatabaseError($e);
        }
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
