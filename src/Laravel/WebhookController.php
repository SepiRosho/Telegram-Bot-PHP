<?php

namespace Devflow\TelegramBot\Laravel;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The route Telegram POSTs updates to.
 *
 * Feeds the framework's own Request into the bot rather than letting it read
 * php://input and $_SERVER, so it behaves the same under php-fpm, Octane and
 * feature tests.
 */
class WebhookController
{
    public function __invoke(Request $request): Response
    {
        $bot = Bot::getInstance();

        try {
            $update = $bot->parseWebhook(
                (string) $request->getContent(),
                $request->header('X-Telegram-Bot-Api-Secret-Token'),
            );
        } catch (WebhookException $e) {
            // Not from Telegram, or not a valid update: say so (403/400), but
            // it is the caller's mistake, not an application error to report.
            return response($e->getMessage(), $e->statusCode());
        } catch (\Throwable $e) {
            // Authenticated but structurally broken (a type the parser chokes on):
            // redelivering the same bytes cannot help, so report it and move on.
            report($e);

            return response('OK');
        }

        $process = function () use ($bot, $update): void {
            try {
                $bot->processUpdate($update);
            } catch (\Throwable $e) {
                // Telegram redelivers anything that isn't answered with a 2xx, so
                // a handler that throws would be retried indefinitely. Hand the
                // failure to the app's exception handler (log, Sentry, ...) and
                // acknowledge the update.
                report($e);
            }
        };

        if ($bot->config('respond_early')) {
            // Run after the 200 has gone out: under php-fpm the response is
            // flushed to Telegram before terminating callbacks execute, so a
            // slow handler can no longer trip Telegram's delivery timeout.
            app()->terminating($process);
        } else {
            $process();
        }

        return response('OK');
    }
}
