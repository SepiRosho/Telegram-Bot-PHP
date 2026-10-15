<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Exceptions\MissingTokenException;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Illuminate\Console\Command;

/**
 * Long-polling for local development: no public HTTPS URL, no tunnel. Handlers
 * run in this process exactly as they would behind the webhook route.
 */
class PollCommand extends Command
{
    protected $signature = 'telegram:poll
                            {--drop-pending : Discard updates queued before now; only new messages are answered}';

    protected $description = 'Receive updates by long-polling instead of a webhook (local development)';

    public function handle(BotInstance $bot): int
    {
        $this->line('Long-polling started. Press Ctrl+C to stop.');
        $this->line('Telegram refuses getUpdates while a webhook is set; if it complains, run telegram:delete-webhook.');

        if ($this->option('drop-pending')) {
            $this->line('Dropping updates queued before now.');
        }

        try {
            $bot->runPolling(
                function (\Throwable $e, int $retryIn = 0): void {
                    $this->report($e, $retryIn);
                },
                (bool) $this->option('drop-pending'),
            );
        } catch (MissingTokenException $e) {
            $this->error($e->getMessage());
        } catch (TelegramApiException $e) {
            // runPolling only rethrows what waiting cannot fix.
            $this->error($e->getMessage());

            $hint = match ($e->telegramErrorCode()) {
                409     => 'A webhook is still registered. Remove it first: php artisan telegram:delete-webhook',
                401     => 'Telegram rejected the token. Check TELEGRAM_BOT_TOKEN in your .env file.',
                404     => 'Telegram does not recognise this bot — the token is malformed or the bot was deleted.',
                default => null,
            };
            if ($hint !== null) {
                $this->line($hint);
            }
        }

        return self::FAILURE;
    }

    /**
     * A blocked user and a TypeError in a handler are both "an error", but only
     * one is worth acting on: expected Telegram errors print as warnings, and
     * everything else is also handed to Laravel's reporter (log, Sentry, ...).
     */
    private function report(\Throwable $e, int $retryIn): void
    {
        if ($e instanceof TelegramApiException && $e->isExpected()) {
            $this->warn($e->getMessage());
            return;
        }

        $this->error($e->getMessage());
        $this->line('  at ' . basename($e->getFile()) . ':' . $e->getLine());
        report($e);

        if ($retryIn > 0) {
            $this->line("  Retrying in {$retryIn}s...");
        }
    }
}
