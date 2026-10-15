<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Support\WebhookOptions;

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

    /** Options that take a value, as `--name=value` or `--name value`. */
    private const VALUE_OPTIONS = ['allowed-updates', 'secret', 'max-connections', 'ip-address', 'certificate'];

    /** Flags with no value (the long spelling mirrors setWebhook's own parameter). */
    private const FLAGS = ['drop-pending', 'drop-pending-updates'];

    private function set(array $args): void
    {
        $url = null;
        $values = [];
        $flags = [];

        // The first bare argument is the URL; flags may come before or after it.
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            if (!str_starts_with($arg, '--')) {
                $url ??= $arg;
                continue;
            }

            $name = substr($arg, 2);
            $inline = null;
            if (str_contains($name, '=')) {
                [$name, $inline] = explode('=', $name, 2);
            }

            if (in_array($name, self::VALUE_OPTIONS, true)) {
                $values[$name] = $inline ?? ($args[++$i] ?? '');
            } elseif (in_array($name, self::FLAGS, true)) {
                $flags[] = $name;
            } else {
                $this->error("Unknown option: {$arg}");
                exit(1);
            }
        }

        if (!$url) {
            $this->error('Usage: vendor/bin/devflow webhook:set <https-url> [options]');
            $this->line('  --allowed-updates=message,chat_member,...|all|default');
            $this->line('  --secret=TOKEN          (defaults to WEBHOOK_SECRET from .env)');
            $this->line('  --max-connections=40    --ip-address=1.2.3.4');
            $this->line('  --certificate=path/to/public.pem    (self-signed server)');
            $this->line('  --drop-pending');
            exit(1);
        }

        $secret = $values['secret'] ?? ($_ENV['WEBHOOK_SECRET'] ?? getenv('WEBHOOK_SECRET') ?: null);

        $options = WebhookOptions::build([
            'secret'          => $secret,
            'allowed_updates' => ($values['allowed-updates'] ?? '') ?: Bot::getInstance()->config('allowed_updates'),
            'max_connections' => $values['max-connections'] ?? null,
            'ip_address'      => $values['ip-address'] ?? null,
            'certificate'     => $values['certificate'] ?? null,
            'drop_pending'    => $flags !== [],
        ]);

        Bot::setWebhook($url, $options);

        $this->success("Webhook set to {$url}");
        foreach (WebhookOptions::describe($options) as $line) {
            $this->line('  ' . $line);
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
        if (isset($info['allowed_updates'])) {
            $this->line('  Allowed updates:   ' . ($info['allowed_updates'] === [] ? '(Telegram default)' : implode(', ', $info['allowed_updates'])));
        }
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
