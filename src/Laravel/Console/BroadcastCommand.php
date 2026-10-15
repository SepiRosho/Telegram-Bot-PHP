<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Broadcasting\BroadcastRunner;
use Devflow\TelegramBot\Laravel\Console\Concerns\ReportsApiErrors;
use Illuminate\Console\Command;

/**
 * Sends the pending rows of `telegram_broadcasts`. Run it from the scheduler:
 *
 *   Schedule::command('telegram:broadcast')->everyMinute()->withoutOverlapping();
 */
class BroadcastCommand extends Command
{
    use ReportsApiErrors;

    protected $signature = 'telegram:broadcast
                            {--rate= : Messages per second, 1-30 (defaults to the broadcast_rate config, 25)}';

    protected $description = 'Send all pending broadcasts to active users';

    public function handle(BotInstance $bot): int
    {
        $rate = (int) ($this->option('rate') ?: $bot->config('broadcast_rate') ?: 25);

        $runner = new BroadcastRunner(
            $bot,
            fn(string $kind, string $message) => $kind === 'success' ? $this->info($message) : $this->line($message),
        );

        return $this->guardApi(function () use ($runner, $rate): int {
            $runner->runPending($rate);

            return self::SUCCESS;
        });
    }
}
