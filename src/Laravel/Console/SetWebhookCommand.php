<?php

namespace Devflow\TelegramBot\Laravel\Console;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\Concerns\ReportsApiErrors;
use Devflow\TelegramBot\Support\WebhookOptions;
use Illuminate\Console\Command;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class SetWebhookCommand extends Command
{
    use ReportsApiErrors;

    protected $signature = 'telegram:set-webhook
                            {url? : The public HTTPS URL Telegram should POST updates to (defaults to the telegram.webhook route under APP_URL)}
                            {--secret= : Secret token for request verification (defaults to TELEGRAM_WEBHOOK_SECRET)}
                            {--allowed-updates= : Comma-separated update types to receive, or "all" / "default" (defaults to the allowed_updates config)}
                            {--max-connections= : Maximum simultaneous HTTPS connections Telegram may open (1-100)}
                            {--ip-address= : Fixed IP address Telegram should use instead of resolving the host}
                            {--certificate= : Path to a PEM public certificate, for a self-signed server}
                            {--drop-pending : Drop pending updates when setting the webhook}';

    protected $description = 'Register the Telegram bot webhook URL';

    public function handle(BotInstance $bot): int
    {
        $url = $this->argument('url') ?: $this->defaultUrl();

        if ($url === null) {
            $this->error('No URL given, and the webhook route is disabled or has no APP_URL to build one from.');
            $this->line('Usage: php artisan telegram:set-webhook https://your-domain.example/telegram/webhook');
            return self::FAILURE;
        }

        try {
            // The webhook route rejects any request lacking the configured secret,
            // so registering a webhook without it would have every delivery refused.
            $options = WebhookOptions::build([
                'secret'          => $this->option('secret') ?: $bot->config('webhook_secret'),
                'allowed_updates' => $this->option('allowed-updates') ?: $bot->config('allowed_updates'),
                'max_connections' => $this->option('max-connections'),
                'ip_address'      => $this->option('ip-address'),
                'certificate'     => $this->option('certificate'),
                'drop_pending'    => (bool) $this->option('drop-pending'),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (!str_starts_with($url, 'https://') && !isset($options['certificate'])) {
            $this->warn('Telegram only delivers webhooks to HTTPS URLs — expect this to be rejected.');
        }

        $this->info("Setting webhook to: {$url}");
        foreach (WebhookOptions::describe($options) as $line) {
            $this->line($line);
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

    private function defaultUrl(): ?string
    {
        try {
            return route('telegram.webhook');
        } catch (RouteNotFoundException|UrlGenerationException) {
            return null;
        }
    }
}
