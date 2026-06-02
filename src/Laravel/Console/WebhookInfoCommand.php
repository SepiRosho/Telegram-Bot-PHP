<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Illuminate\Console\Command;

class WebhookInfoCommand extends Command
{
    protected $signature = 'telegram:webhook-info';

    protected $description = 'Display current Telegram webhook information';

    public function handle(BotInstance $bot): int
    {
        $info = $bot->api()->getWebhookInfo();

        $this->table(
            ['Field', 'Value'],
            collect($info)->map(fn($v, $k) => [
                $k,
                is_array($v) ? json_encode($v) : (string) $v,
            ])->values()->toArray()
        );

        return self::SUCCESS;
    }
}
