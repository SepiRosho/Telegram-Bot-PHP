<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;

/**
 * Webhook management from the standalone CLI. `devflow poll` tells you to
 * delete your webhook before polling — Telegram refuses getUpdates while one
 * is registered — so the standalone side needs these as much as Laravel,
 * which already ships the equivalent Artisan commands.
 */
class WebhookCommand
{
    public function __construct(private string $action = 'info') {}

    public function execute(array $args): void
    {
        $cwd = getcwd();
        $bootstrap = $cwd . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (!file_exists($bootstrap)) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        $envFile = $cwd . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile) && class_exists('Dotenv\Dotenv')) {
            \Dotenv\Dotenv::createImmutable($cwd)->safeLoad();
        }

        require $bootstrap;

        try {
            match ($this->action) {
                'set'    => $this->set($args),
                'delete' => $this->delete($args),
                default  => $this->info(),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function set(array $args): void
    {
        $url = $args[0] ?? null;

        if (!$url) {
            $this->error('Usage: vendor/bin/devflow webhook:set <https-url>');
            exit(1);
        }

        $options = [];
        $secret = $_ENV['WEBHOOK_SECRET'] ?? getenv('WEBHOOK_SECRET') ?: null;
        if (!empty($secret)) {
            $options['secret_token'] = $secret;
        }

        Bot::setWebhook($url, $options);

        $this->success("Webhook set to {$url}");
        if (isset($options['secret_token'])) {
            $this->line('  Using WEBHOOK_SECRET from .env as secret_token.');
        }
    }

    private function delete(array $args): void
    {
        Bot::deleteWebhook(in_array('--drop-pending', $args, true));

        $this->success('Webhook deleted. You can now run: vendor/bin/devflow poll');
    }

    private function info(): void
    {
        $info = Bot::getWebhookInfo();

        if (empty($info['url'])) {
            $this->line('No webhook is registered. Polling mode is available.');
            return;
        }

        $this->line("  URL:               {$info['url']}");
        $this->line('  Pending updates:   ' . ($info['pending_update_count'] ?? 0));
        $this->line('  Custom certificate: ' . (($info['has_custom_certificate'] ?? false) ? 'yes' : 'no'));

        if (!empty($info['last_error_message'])) {
            $this->error("Last error: {$info['last_error_message']}");
        }
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
