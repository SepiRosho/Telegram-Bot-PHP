<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\Concerns\ReportsApiErrors;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    use ReportsApiErrors;

    protected $signature = 'telegram:set-webhook
                            {url : The public HTTPS URL Telegram should POST updates to}
                            {--secret= : Secret token for request verification (defaults to TELEGRAM_WEBHOOK_SECRET)}
                            {--drop-pending : Drop pending updates when setting the webhook}';

    protected $description = 'Register the Telegram bot webhook URL';

    public function handle(BotInstance $bot): int
    {
        $url = $this->argument('url');

        // The webhook route rejects any request lacking the configured secret,
        // so registering a webhook without it would have every delivery refused.
        $secret = $this->option('secret') ?: $bot->config('webhook_secret');

        $options = array_filter([
            'secret_token' => $secret,
            'drop_pending_updates' => $this->option('drop-pending') ?: null,
        ]);

        $this->info("Setting webhook to: {$url}");

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
