<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\Concerns\ReportsApiErrors;
use Devflow\TelegramBot\Support\AllowedUpdates;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    use ReportsApiErrors;

    protected $signature = 'telegram:set-webhook
                            {url : The public HTTPS URL Telegram should POST updates to}
                            {--secret= : Secret token for request verification (defaults to TELEGRAM_WEBHOOK_SECRET)}
                            {--allowed-updates= : Comma-separated update types to receive, or "all" / "default" (defaults to the allowed_updates config)}
                            {--drop-pending : Drop pending updates when setting the webhook}';

    protected $description = 'Register the Telegram bot webhook URL';

    public function handle(BotInstance $bot): int
    {
        $url = $this->argument('url');

        try {
            $allowed = AllowedUpdates::parse($this->option('allowed-updates') ?: $bot->config('allowed_updates'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // The webhook route rejects any request lacking the configured secret,
        // so registering a webhook without it would have every delivery refused.
        $secret = $this->option('secret') ?: $bot->config('webhook_secret');

        $options = array_filter([
            'secret_token' => $secret,
            'drop_pending_updates' => $this->option('drop-pending') ?: null,
        ]);

        // Kept out of the array_filter above: [] is a real value here ("back to
        // Telegram's default set"), while null means "leave the setting alone".
        if ($allowed !== null) {
            $options['allowed_updates'] = $allowed;
        }

        $this->info("Setting webhook to: {$url}");

        if ($allowed !== null) {
            $this->line('Allowed updates: ' . ($allowed === [] ? '(Telegram default)' : implode(', ', $allowed)));
        }

        return $this->guardApi(function () use ($bot, $url, $options): int {
            $result = $bot->api()->setWebhook($url, $options);

            if ($result) {
                $this->info('Webhook set successfully.');
                return self::SUCCESS;
            }

            $this->error('Failed to set webhook.');
            return self::FAILURE;
        });
    }
}
