<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Illuminate\Console\Command;

class DeleteWebhookCommand extends Command
{
    protected $signature = 'telegram:delete-webhook
                            {--drop-pending : Also drop all pending updates}';

    protected $description = 'Remove the Telegram bot webhook';

    public function handle(BotInstance $bot): int
    {
        $dropPending = (bool) $this->option('drop-pending');
        $result = $bot->api()->deleteWebhook($dropPending);

        if ($result) {
            $this->info('Webhook deleted successfully.');
            return self::SUCCESS;
        }

        $this->error('Failed to delete webhook.');
        return self::FAILURE;
    }
}
