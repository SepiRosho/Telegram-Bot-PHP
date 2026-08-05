<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Illuminate\Database\Capsule\Manager as Capsule;

class PollCommand
{
    use ReportsDatabaseErrors;

    /** Both spellings, because the setWebhook parameter this mirrors is the long one. */
    private const DROP_PENDING_FLAGS = ['--drop-pending', '--drop-pending-updates'];

    public function execute(array $args): void
    {
        $cwd = getcwd();
        $bootstrap = $cwd . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (!file_exists($bootstrap)) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        $dropPending = (bool) array_intersect($args, self::DROP_PENDING_FLAGS);

        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && !in_array($arg, self::DROP_PENDING_FLAGS, true)) {
                $this->error("Unknown option: {$arg}");
                $this->line('  Usage: vendor/bin/devflow poll [--drop-pending]');
                exit(1);
            }
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
        if ($dropPending) {
            $this->line("  \033[33mNote:\033[0m dropping updates queued before now — only new messages will be answered.");
        }
        $this->line('  Press Ctrl+C to stop.');
        $this->line('');

        try {
            Bot::getInstance()->runPolling(
                function (\Throwable $e, int $retryIn = 0): void {
                    $this->report($e, $retryIn);
                },
                $dropPending,
            );
        } catch (TelegramApiException $e) {
            // runPolling only rethrows what it cannot recover from.
            $this->line('');
            $this->error($e->getMessage());
            $this->explainFatal($e);
            exit(1);
        }
    }

    /**
     * Errors reaching here are already survivable — the loop has moved on. The
     * point of separating them is that "a user blocked your bot" and "your
     * handler has a TypeError" are the same red ✗ otherwise, and only one of
     * them is worth acting on.
     */
    private function report(\Throwable $e, int $retryIn): void
    {
        if ($e instanceof TelegramApiException && $e->isExpected()) {
            $this->warn($e->getMessage());
            return;
        }

        $this->error($e->getMessage());
        $this->line("  \033[90mat " . basename($e->getFile()) . ':' . $e->getLine() . "\033[0m");

        if ($retryIn > 0) {
            $this->line("  Retrying in {$retryIn}s...");
        }
    }

    private function explainFatal(TelegramApiException $e): void
    {
        $hint = match ($e->telegramErrorCode()) {
            409     => "A webhook is still registered — Telegram won't serve getUpdates while one is set.\n"
                     . "  Remove it first: \033[36mvendor/bin/devflow webhook:delete\033[0m",
            401     => "Telegram rejected your token. Check BOT_TOKEN in .env against @BotFather.",
            404     => "Telegram doesn't recognise this bot. The token is malformed or the bot was deleted.",
            default => null,
        };

        if ($hint !== null) {
            $this->line("\n  {$hint}\n");
        }
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function warn(string $msg): void
    {
        echo "\033[33m!\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
