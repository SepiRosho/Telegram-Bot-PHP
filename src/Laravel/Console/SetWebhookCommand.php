<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {url : The public HTTPS URL Telegram should POST updates to}
                            {--secret= : Optional secret token for request verification}
                            {--drop-pending : Drop pending updates when setting the webhook}';

    protected $description = 'Register the Telegram bot webhook URL';

    public function handle(BotInstance $bot): int
    {
        $url = $this->argument('url');

        $options = array_filter([
            'secret_token' => $this->option('secret'),
            'drop_pending_updates' => $this->option('drop-pending') ?: null,
        ]);

        $this->info("Setting webhook to: {$url}");

        $result = $bot->api()->setWebhook($url, $options);

        if ($result) {
            $this->info('Webhook set successfully.');
            return self::SUCCESS;
        }

        $this->error('Failed to set webhook.');
        return self::FAILURE;
    }
}
