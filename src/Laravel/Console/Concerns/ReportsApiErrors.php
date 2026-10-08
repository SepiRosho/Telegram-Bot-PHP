<?php

namespace Devflow\TelegramBot\Laravel\Console\Concerns;

use Devflow\TelegramBot\Exceptions\MissingTokenException;
use Devflow\TelegramBot\Exceptions\TelegramApiException;

/**
 * Turns the two ways a Telegram call fails from the console — no token, or
 * the API refusing / being unreachable — into one error line and a non-zero
 * exit code, instead of a stack trace.
 */
trait ReportsApiErrors
{
    /** @param callable(): int $run */
    protected function guardApi(callable $run): int
    {
        try {
            return $run();
        } catch (MissingTokenException|TelegramApiException $e) {
            // Transport errors can carry a multi-line cURL dump; the first line is the cause.
            $this->error(strtok($e->getMessage(), "\n") ?: 'Telegram request failed.');

            if ($e instanceof TelegramApiException && $e->telegramErrorCode() === 401) {
                $this->line('Check TELEGRAM_BOT_TOKEN in your .env file.');
            }

            return self::FAILURE;
        }
    }
}
