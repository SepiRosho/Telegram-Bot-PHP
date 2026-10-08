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
        try {
            Bot::getInstance()->handleWebhook(
                (string) $request->getContent(),
                $request->header('X-Telegram-Bot-Api-Secret-Token'),
            );
        } catch (WebhookException $e) {
            // Not from Telegram, or not a valid update: say so (403/400), but
            // it is the caller's mistake, not an application error to report.
            return response($e->getMessage(), $e->statusCode());
        } catch (\Throwable $e) {
            // Telegram redelivers anything that isn't answered with a 2xx, so
            // a handler that throws would be retried indefinitely. Hand the
            // failure to the app's exception handler (log, Sentry, ...) and
            // acknowledge the update.
            report($e);
        }

        return response('OK');
    }
}
