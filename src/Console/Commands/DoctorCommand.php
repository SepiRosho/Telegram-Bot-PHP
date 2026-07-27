<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\Concerns\BootsProject;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * One command that answers "why isn't my bot responding?".
 *
 * Every check is independent and non-fatal: the point is to print the *whole*
 * picture in one run, so a missing extension and a bad DB password show up
 * together rather than one per debugging round-trip. Exits 1 if any check
 * failed, so it also works as a deploy gate.
 */
class DoctorCommand
{
    use BootsProject;

    private int $failures = 0;
    private int $warnings = 0;

    public function execute(array $args): void
    {
        echo "\n\033[1mdevflow doctor\033[0m — checking this project\n\n";

        $this->checkEnvironment();
        $this->checkProjectFiles();

        if ($this->hasProjectBootstrap()) {
            $this->loadProjectEnv();
            $this->checkToken();
            $this->checkBootstrap();
            $this->checkDatabase();
            $this->checkWebhook();
        }

        $this->summary();
    }

    // ─── Checks ───────────────────────────────────────────────────────────────

    private function checkEnvironment(): void
    {
        $this->section('Environment');

        PHP_VERSION_ID >= 80100
            ? $this->pass('PHP ' . PHP_VERSION)
            : $this->fail('PHP ' . PHP_VERSION . ' — this library requires 8.1 or newer');

        foreach (['curl', 'json', 'mbstring', 'openssl', 'pdo'] as $ext) {
            extension_loaded($ext)
                ? $this->pass("ext-{$ext} loaded")
                : $this->fail("ext-{$ext} missing — install it in php.ini");
        }

        // Either driver is fine; only the absence of both actually blocks.
        extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite')
            ? $this->pass('A PDO database driver is available')
            : $this->fail('No PDO driver found — enable ext-pdo_mysql for MySQL/MariaDB');
    }

    private function checkProjectFiles(): void
    {
        $this->section('Project files');

        $root = $this->projectRoot();

        $this->hasProjectBootstrap()
            ? $this->pass('bootstrap/app.php found')
            : $this->fail('bootstrap/app.php not found — run this from your project root');

        file_exists($root . DIRECTORY_SEPARATOR . '.env')
            ? $this->pass('.env found')
            : $this->fail('.env not found — copy .env.example to .env and fill it in');

        file_exists($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')
            ? $this->pass('vendor/autoload.php found')
            : $this->fail('vendor/ missing — run `composer install`');

        $logs = $root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logs)) {
            $this->warn('logs/ does not exist — saveLog() will not be able to write');
        } elseif (!is_writable($logs)) {
            $this->warn('logs/ is not writable — saveLog() will silently drop entries');
        } else {
            $this->pass('logs/ is writable');
        }
    }

    private function checkToken(): void
    {
        $this->section('Bot token');

        $token = $this->env('BOT_TOKEN');

        if ($token === null || $token === '') {
            $this->fail('BOT_TOKEN is empty in .env — get one from @BotFather');
            return;
        }

        // Telegram tokens are "<bot_id>:<35-char secret>". Catching a malformed
        // one here saves a confusing 404 from the API call below.
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $token)) {
            $this->fail('BOT_TOKEN does not look like a Telegram token (expected "123456:ABC-DEF...")');
            return;
        }

        $this->pass('BOT_TOKEN is present and well-formed');

        $adminId = $this->env('ADMIN_CHAT_ID');
        ($adminId !== null && $adminId !== '' && ctype_digit(ltrim($adminId, '-')))
            ? $this->pass("ADMIN_CHAT_ID is set ({$adminId})")
            : $this->warn('ADMIN_CHAT_ID is empty — no superadmin will be auto-promoted, and botLog() alerts go nowhere');
    }

    private function checkBootstrap(): void
    {
        $this->section('Bot configuration');

        try {
            require $this->bootstrapPath();
        } catch (\Throwable $e) {
            $this->fail('bootstrap/app.php threw: ' . $e->getMessage());
            return;
        }

        $this->pass('bootstrap/app.php loaded without errors');

        try {
            $instance = Bot::getInstance();
        } catch (\Throwable $e) {
            $this->fail('Bot::init() was never called in bootstrap/app.php');
            return;
        }

        $allowed = $instance->config('allowed_chat_types');
        if ($allowed === null) {
            $this->warn('allowed_chat_types is not set — the bot will respond in groups and channels too. Set it to [\'private\'] in bootstrap/app.php unless that is intended');
        } else {
            $this->pass('allowed_chat_types = [' . implode(', ', (array) $allowed) . ']');
        }

        $instance->config('webhook_secret')
            ? $this->pass('webhook_secret is configured')
            : $this->warn('webhook_secret is not set — anyone who learns your webhook URL can post fake updates. Set WEBHOOK_SECRET in .env and re-run `webhook:set`');

        $routes = count($instance->router()->routes());
        $routes > 0
            ? $this->pass("{$routes} route(s) registered")
            : $this->fail('No routes registered — check Bot::loadHandlers() in bootstrap/app.php');
    }

    private function checkDatabase(): void
    {
        $this->section('Database');

        try {
            Capsule::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->fail('Cannot connect — ' . $e->getMessage());
            return;
        }

        $config = Capsule::connection()->getConfig();
        $this->pass("Connected to \"{$config['database']}\" at {$config['host']}");

        $expected = ['telegram_users', 'bot_settings', 'telegram_broadcasts'];
        $missing  = array_values(array_filter(
            $expected,
            fn(string $table) => !Capsule::schema()->hasTable($table),
        ));

        $missing === []
            ? $this->pass('Base tables exist (' . implode(', ', $expected) . ')')
            : $this->fail('Missing table(s): ' . implode(', ', $missing) . ' — run `vendor/bin/devflow migrate`');
    }

    private function checkWebhook(): void
    {
        $this->section('Webhook');

        try {
            $info = Bot::getInstance()->api()->getWebhookInfo();
        } catch (\Throwable $e) {
            // A rejected token and an unreachable network both surface here,
            // but they need completely different fixes — 401 is the tell.
            if ($e instanceof TelegramApiException && $e->telegramErrorCode() === 401) {
                $this->fail('Telegram rejected the token (401 Unauthorized) — BOT_TOKEN is wrong or has been revoked. Re-issue it with @BotFather');
                return;
            }

            $this->fail('Could not reach the Telegram API — ' . $e->getMessage());
            $this->line('        If Telegram is blocked in your country, set PROXY_URL in .env');
            return;
        }

        $url = $info['url'] ?? '';

        if ($url === '') {
            $this->warn('No webhook registered — fine if you use `devflow poll`, otherwise run `webhook:set <https-url>`');
        } else {
            $this->pass("Webhook registered: {$url}");
        }

        // A non-zero backlog usually means the endpoint is 500ing or too slow,
        // and it is the single most useful signal in getWebhookInfo.
        $pending = (int) ($info['pending_update_count'] ?? 0);
        $pending > 0
            ? $this->warn("{$pending} update(s) queued at Telegram — your endpoint may be failing or too slow")
            : $this->pass('No backlog of pending updates');

        if (!empty($info['last_error_message'])) {
            $when = isset($info['last_error_date'])
                ? date('Y-m-d H:i:s', (int) $info['last_error_date'])
                : 'unknown time';
            $this->fail("Telegram's last delivery failed ({$when}): {$info['last_error_message']}");
        }

        if (($url !== '') && empty($info['has_custom_certificate']) && !str_starts_with($url, 'https://')) {
            $this->fail('Webhook URL is not HTTPS — Telegram requires TLS');
        }
    }

    // ─── Output ───────────────────────────────────────────────────────────────

    private function summary(): void
    {
        echo "\n";

        if ($this->failures === 0 && $this->warnings === 0) {
            echo "\033[32m✓ Everything looks good.\033[0m\n\n";
            return;
        }

        $parts = [];
        if ($this->failures > 0) {
            $parts[] = "\033[31m{$this->failures} problem(s)\033[0m";
        }
        if ($this->warnings > 0) {
            $parts[] = "\033[33m{$this->warnings} warning(s)\033[0m";
        }

        echo implode(', ', $parts) . "\n\n";

        if ($this->failures > 0) {
            exit(1);
        }
    }

    private function section(string $title): void
    {
        echo "\033[1m{$title}\033[0m\n";
    }

    private function pass(string $msg): void
    {
        echo "  \033[32m✓\033[0m {$msg}\n";
    }

    private function warn(string $msg): void
    {
        $this->warnings++;
        echo "  \033[33m!\033[0m {$msg}\n";
    }

    private function fail(string $msg): void
    {
        $this->failures++;
        echo "  \033[31m✗\033[0m {$msg}\n";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return ($value === false || $value === null) ? null : trim((string) $value);
    }
}
