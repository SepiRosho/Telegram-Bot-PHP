<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\Concerns\ReportsApiErrors;
use Illuminate\Console\Command;

class DeleteWebhookCommand extends Command
{
    use ReportsApiErrors;

    protected $signature = 'telegram:delete-webhook
                            {--drop-pending : Also drop all pending updates}';

    protected $description = 'Remove the Telegram bot webhook';

    public function handle(BotInstance $bot): int
    {
        return $this->guardApi(function () use ($bot): int {
            $dropPending = (bool) $this->option('drop-pending');
            $result = $bot->api()->deleteWebhook($dropPending);

            if ($result) {
                $this->info('Webhook deleted successfully.');
                return self::SUCCESS;
            }

            $this->error('Failed to delete webhook.');
            return self::FAILURE;
        });
    }
}
