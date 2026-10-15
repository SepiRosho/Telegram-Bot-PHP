<?php

// respond_early, and a webhook entry point that treats a bad secret as a rejection, not a crash.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::envKey(
        'RESPOND_EARLY',
        "# true = answer Telegram first and run handlers afterwards, so a slow handler\n"
        . "# cannot hit Telegram's delivery timeout and be redelivered.\n"
        . 'RESPOND_EARLY=false',
    ),

    UpgradeStep::bootstrapMarker(
        marker: 'respond_early',
        label: 'respond_early',
        snippet: "'respond_early'      => filter_var(env('RESPOND_EARLY', false), FILTER_VALIDATE_BOOLEAN),",
    ),

    UpgradeStep::manual(
        check: function (string $root): bool {
            $path = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'webhook.php';

            return !file_exists($path) || str_contains((string) file_get_contents($path), 'WebhookException');
        },
        okMessage: 'public/webhook.php already treats a bad secret / junk body as a rejection',
        problemMessage: 'public/webhook.php reports every bad-secret or malformed request as a "Bot crash" alert to ADMIN_CHAT_ID',
        snippet: <<<'PHP'
        // in public/webhook.php, between `try { Bot::run(); }` and `catch (\Throwable $e)`:
        } catch (\Devflow\TelegramBot\Exceptions\WebhookException $e) {
            // Not a delivery from Telegram: run() already set 403/400. Log quietly.
            saveLog($e->getMessage(), 'WARNING');
        PHP,
    ),

    UpgradeStep::note(
        'With RESPOND_EARLY=true the 200 goes to Telegram as soon as the secret and payload check out '
        . '(fastcgi_finish_request under php-fpm, an empty Connection: close response elsewhere), and handlers run '
        . 'after it. Off by default.',
    ),
];
