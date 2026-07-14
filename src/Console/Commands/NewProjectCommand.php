<?php

namespace Devflow\TelegramBot\Console\Commands;

class NewProjectCommand
{
    public function execute(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: vendor/bin/devflow new <project-name>');
            exit(1);
        }

        if (is_dir($name)) {
            $this->error("Directory [{$name}] already exists.");
            exit(1);
        }

        $root = getcwd() . DIRECTORY_SEPARATOR . $name;

        $this->info("Creating project \033[36m{$name}\033[0m ...\n");

        $this->makeDirectories($root);
        $this->writeFiles($root, $name);

        echo "\n";
        $this->success("Project [{$name}] created successfully!");
        echo "\n";
        echo "  Next steps:\n";
        echo "  \033[33m1.\033[0m cd {$name}\n";
        echo "  \033[33m2.\033[0m composer install\n";
        echo "  \033[33m3.\033[0m Edit \033[36m.env\033[0m — set BOT_TOKEN and ADMIN_CHAT_ID (your Telegram numeric user ID)\n";
        echo "  \033[33m4.\033[0m Point your Telegram webhook at: https://yourdomain.com/public/webhook.php\n";
        echo "  \033[33m5.\033[0m When adding new classes, run: composer dump-autoload\n";
        echo "\n";
        echo "  Database setup (required — used for auto-registration and admin panel):\n";
        echo "  \033[33m6.\033[0m Import each SQL file from \033[36mdatabase/migrations/\033[0m into your MySQL/MariaDB database\n";
        echo "     (phpMyAdmin: select your DB → Import → choose each .sql file)\n";
        echo "     CLI: mysql -u root my_bot < database/migrations/telegram_users.sql\n";
        echo "  \033[33m7.\033[0m Fill in DB_ credentials in .env  (\033[36mDB_DRIVER=mariadb\033[0m for MariaDB)\n";
        echo "\n";
        echo "  Optional:\n";
        echo "  \033[33m•\033[0m Local dev (no webhook needed): \033[36mvendor/bin/devflow poll\033[0m\n";
        echo "  \033[33m•\033[0m Send broadcasts:               \033[36mvendor/bin/devflow broadcast:run\033[0m\n";
        echo "  \033[33m•\033[0m Countries blocking Telegram:   set \033[36mPROXY_URL=\033[0m in .env\n";
        echo "\n";
    }

    private function makeDirectories(string $root): void
    {
        $dirs = [
            'app/Commands',
            'app/Callbacks',
            'app/Middleware',
            'app/Flows',
            'app/Handlers',
            'app/Texts',
            'app/Services',
            'bootstrap',
            'config',
            'database/migrations',
            'lang',
            'public',
            'logs',
        ];

        foreach ($dirs as $dir) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
            if (!mkdir($path, 0755, true)) {
                $this->error("Could not create directory: {$path}");
                exit(1);
            }
        }
    }

    private function writeFiles(string $root, string $projectName): void
    {
        $files = [
            'composer.json'              => $this->composerJson($projectName),
            '.env.example'               => $this->envExample(),
            '.env'                       => $this->envExample(),
            '.gitignore'                 => $this->gitignore(),
            '.htaccess'                  => $this->htaccess(),
            'bootstrap/helpers.php'      => $this->bootstrapHelpers(),
            'bootstrap/app.php'          => $this->bootstrapApp(),
            'public/webhook.php'         => $this->publicWebhook(),
            'app/Commands/HelpCommand.php'              => $this->helpCommand(),
            'app/Middleware/AuthMiddleware.php'         => $this->authMiddleware(),
            'app/Handlers/UserHandlers.php'             => $this->userHandlers(),
            'app/Handlers/AdminHandlers.php'            => $this->adminHandlers(),
            'app/Texts/WelcomeText.php'                 => $this->welcomeText(),
            'lang/en.php'                                => $this->langEn(),
            'lang/fa.php'                                => $this->langFa(),
            'database/migrations/telegram_users.sql'    => $this->migrationTelegramUsers(),
            'database/migrations/bot_settings.sql'      => $this->migrationBotSettings(),
            'database/migrations/telegram_broadcasts.sql' => $this->migrationBroadcasts(),
            'logs/.gitignore'                           => "*.log\n",
            'app/Callbacks/.gitkeep'                    => '',
            'app/Flows/.gitkeep'                        => '',
            'app/Services/.gitkeep'                     => '',
        ];

        foreach ($files as $relative => $content) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            file_put_contents($path, $content);
            $this->line("  \033[32m+\033[0m {$relative}");
        }
    }

    // ─── File templates ───────────────────────────────────────────────────────

    private function composerJson(string $projectName): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $projectName));
        return <<<JSON
        {
            "name": "my/{$slug}",
            "type": "project",
            "require": {
                "php": "^8.1",
                "devflow/telegram-bot": "^1.1",
                "vlucas/phpdotenv": "^5.0"
            },
            "autoload": {
                "psr-4": {
                    "App\\\\": "app/"
                },
                "files": [
                    "bootstrap/helpers.php"
                ]
            },
            "config": {
                "optimize-autoloader": true
            },
            "minimum-stability": "stable",
            "prefer-stable": true
        }
        JSON;
    }

    private function envExample(): string
    {
        return <<<ENV
        BOT_TOKEN=your_bot_token_here
        ADMIN_CHAT_ID=

        # Optional: set a secret token when registering your webhook (recommended for production).
        # Pass this same value to setWebhook as the secret_token option.
        WEBHOOK_SECRET=

        # Optional: HTTP or SOCKS5 proxy URL (e.g. http://user:pass@host:port or socks5://host:port).
        # Useful in countries where Telegram's API is blocked.
        PROXY_URL=

        # Broadcast rate limit — messages per second sent during broadcast:run.
        # Telegram allows up to 30 msg/s; 25 leaves headroom for normal user responses.
        BROADCAST_RATE=25

        DB_DRIVER=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=my_bot
        DB_USERNAME=root
        DB_PASSWORD=
        ENV;
    }

    private function gitignore(): string
    {
        return <<<GIT
        /vendor/
        composer.lock
        .env
        .env.*
        !.env.example
        .idea/
        .vscode/
        .DS_Store
        Thumbs.db
        *.log
        /logs/
        GIT;
    }

    private function bootstrapHelpers(): string
    {
        return <<<'PHP'
        <?php

        if (!function_exists('env')) {
            function env(string $key, mixed $default = null): mixed
            {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
                return ($value !== false && $value !== null && $value !== '') ? $value : $default;
            }
        }

        if (!function_exists('saveLog')) {
            function saveLog(mixed $data, string $level = 'INFO'): void
            {
                \Devflow\TelegramBot\Support\Log::save($data, $level, 2);
            }
        }

        if (!function_exists('botLog')) {
            function botLog(mixed $data): void
            {
                \Devflow\TelegramBot\Support\Log::send($data);
            }
        }
        PHP;
    }

    private function bootstrapApp(): string
    {
        return <<<'PHP'
        <?php

        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Support\Log;
        use Illuminate\Database\Capsule\Manager as Capsule;

        // ─── Logging ───────────────────────────────────────────────────────────
        // saveLog() / botLog() helpers are available after this.
        // botLog() sends to ADMIN_CHAT_ID; saveLog() writes to logs/YYYY-MM-DD.log.
        Log::setPath(__DIR__ . '/../logs');
        Log::setAdminChatId((int) env('ADMIN_CHAT_ID'));

        // ─── Database ──────────────────────────────────────────────────────────
        // Required for auto-registration, $ctx->user(), banning, rate limiting.
        // Setup:
        //   1. Import each SQL file from database/migrations/ into MySQL/MariaDB
        //      phpMyAdmin: select DB → Import → choose .sql file
        //      CLI: mysql -u root my_bot < database/migrations/telegram_users.sql
        //   2. Fill in DB_ credentials in .env
        //   MariaDB: set DB_DRIVER=mariadb in .env
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'    => env('DB_DRIVER', 'mysql'),
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'my_bot'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // ─── Bot init ──────────────────────────────────────────────────────────
        // webhook_secret: validates the X-Telegram-Bot-Api-Secret-Token header (set WEBHOOK_SECRET in .env).
        // proxy:          HTTP or SOCKS5 proxy URL for countries where Telegram is blocked (set PROXY_URL in .env).
        // ─── i18n ──────────────────────────────────────────────────────────────
        // lang_path: directory of per-locale array files (lang/en.php, lang/fa.php, ...).
        // Use $ctx->t('key', ['name' => ...]) in handlers; $ctx->locale() resolves
        // the stored user preference, falling back to Telegram's client language.
        Bot::init(env('BOT_TOKEN'), [
            'database'       => true,
            'webhook_secret' => env('WEBHOOK_SECRET') ?: null,
            'proxy'          => env('PROXY_URL') ?: null,
            'lang_path'      => __DIR__ . '/../lang',
            'default_locale' => 'en',
        ]);

        // ─── Middleware ────────────────────────────────────────────────────────
        // AuthMiddleware checks bans and updates last_activity_at.
        Bot::use(\App\Middleware\AuthMiddleware::class);

        // ─── Handlers ──────────────────────────────────────────────────────────
        // Each handler group class has a static register() method.
        // Add more groups here as your bot grows.
        Bot::loadHandlers([
            \App\Handlers\UserHandlers::class,
            \App\Handlers\AdminHandlers::class,
        ]);
        PHP;
    }

    private function configBot(): string
    {
        return <<<'PHP'
        <?php

        return [
            'token'    => env('BOT_TOKEN'),
            'database' => (bool) env('BOT_DATABASE', true),
            'db'       => [
                'driver'    => env('DB_DRIVER', 'mysql'),
                'host'      => env('DB_HOST', '127.0.0.1'),
                'port'      => env('DB_PORT', '3306'),
                'database'  => env('DB_DATABASE', 'my_bot'),
                'username'  => env('DB_USERNAME', 'root'),
                'password'  => env('DB_PASSWORD', ''),
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ];
        PHP;
    }

    private function publicWebhook(): string
    {
        return <<<'PHP'
        <?php

        require __DIR__ . '/../vendor/autoload.php';

        // Load .env (safeLoad silently ignores a missing .env file)
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();

        // Bootstrap the bot (init, DB, middleware, handlers)
        require __DIR__ . '/../bootstrap/app.php';

        // Always return 200 to Telegram so it does not retry failed updates.
        http_response_code(200);

        try {
            \Devflow\TelegramBot\Bot::run();
        } catch (\Throwable $e) {
            // Write the full stack trace to the daily log file.
            saveLog($e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');

            // Send a short alert to the admin (full trace would exceed Telegram's message limit).
            botLog(
                '🔴 Bot crash: ' . $e->getMessage() . "\n" .
                '📄 ' . basename($e->getFile()) . ':' . $e->getLine()
            );

            error_log('[devflow-bot] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        PHP;
    }

    private function helpCommand(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Commands;

        use Devflow\TelegramBot\Context;
        use Devflow\TelegramBot\Handlers\HandlerInterface;

        class HelpCommand implements HandlerInterface
        {
            public function handle(Context $ctx): void
            {
                $ctx->reply(
                    "Available commands:\n" .
                    "/start — Start the bot\n" .
                    "/help  — Show this message"
                );
            }
        }
        PHP;
    }

    private function authMiddleware(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Middleware;

        use Devflow\TelegramBot\Context;
        use Devflow\TelegramBot\Middleware\MiddlewareInterface;

        class AuthMiddleware implements MiddlewareInterface
        {
            public function handle(Context $ctx, callable $next): void
            {
                if ($ctx->user()?->is_banned) {
                    $ctx->reply('You are banned from using this bot.');
                    return;
                }

                $ctx->user()?->touchActivity();

                $next($ctx);
            }
        }
        PHP;
    }

    // ─── Output helpers ───────────────────────────────────────────────────────

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m \033[1m{$msg}\033[0m\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }

    private function htaccess(): string
    {
        return <<<'HTACCESS'
        # Disable directory listing
        Options -Indexes

        # Protect sensitive files (works on Apache 2.2 and 2.4)
        <FilesMatch "\.(env|json|lock|gitignore|md|stub)$">
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order Deny,Allow
                Deny from all
            </IfModule>
        </FilesMatch>

        <IfModule mod_rewrite.c>
            RewriteEngine On

            # Block direct access to sensitive directories
            RewriteRule ^(app|bootstrap|config|vendor|database|docs|bin)(/.*)?$ - [F,L]

            # Allow only the webhook entry point
            RewriteRule ^public/webhook\.php$ - [L]

            # Block all other PHP files and bare directory access
            RewriteRule \.php$ - [F,L]
            RewriteRule .* - [F,L]
        </IfModule>
        HTACCESS;
    }

    private function userHandlers(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Handlers;

        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Context;
        use Devflow\TelegramBot\Database\Models\TelegramUser;

        class UserHandlers
        {
            public static function register(): void
            {
                // ─── /start — Auto-registration ──────────────────────────────────
                Bot::onCommand('start', function (Context $ctx) {
                    $user = $ctx->user();

                    if ($user === null) {
                        $adminId = env('ADMIN_CHAT_ID');
                        $role    = ($adminId && (string) $ctx->userId() === trim((string) $adminId))
                            ? 'superadmin'
                            : 'user';

                        $user = TelegramUser::create([
                            'telegram_id'   => $ctx->userId(),
                            'chat_id'       => $ctx->chatId(),
                            'first_name'    => $ctx->from()?->firstName ?? '',
                            'last_name'     => $ctx->from()?->lastName,
                            'username'      => $ctx->from()?->username,
                            'language_code' => $ctx->from()?->languageCode,
                            'role'          => $role,
                            'joined_at'     => date('Y-m-d H:i:s'),
                        ]);
                    }

                    $user->update(['current_panel' => 'user']);
                    $name = $ctx->from()?->firstName ?? 'there';

                    $ctx->reply("👋 Hello, {$name}! Welcome to the bot.", [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::reply([
                            ['📋 My Account'],
                        ]),
                    ]);
                });

                // ─── /user — Switch to user panel ────────────────────────────────
                Bot::onCommand('user', function (Context $ctx) {
                    $ctx->user()?->update(['current_panel' => 'user']);

                    $ctx->reply('👤 User panel.', [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::reply([
                            ['📋 My Account'],
                        ]),
                    ]);
                });

                Bot::onCommand('help', \App\Commands\HelpCommand::class);

                // ─── Fallback ─────────────────────────────────────────────────────
                Bot::onText(function (Context $ctx) {
                    $ctx->reply('You said: ' . $ctx->text());
                });
            }
        }
        PHP;
    }

    private function adminHandlers(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Handlers;

        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Context;
        use Devflow\TelegramBot\Database\Models\TelegramUser;

        class AdminHandlers
        {
            public static function register(): void
            {
                // ─── /admin — Switch to admin panel ──────────────────────────────
                Bot::onCommand('admin', function (Context $ctx) {
                    if (!$ctx->user()?->isAdmin()) {
                        return;
                    }

                    $ctx->user()->update(['current_panel' => 'admin']);
                    $name = $ctx->from()?->firstName ?? 'Admin';

                    $ctx->reply("🔧 Admin panel, {$name}.", [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::inline([
                            [\Devflow\TelegramBot\Support\Keyboard::button('📊 Bot Status', 'bot_status')],
                        ]),
                    ]);
                });

                // ─── Bot Status ───────────────────────────────────────────────────
                Bot::onCallbackQuery('bot_status', function (Context $ctx) {
                    if (!$ctx->user()?->isAdmin()) {
                        $ctx->answerCallback('Access denied.', true);
                        return;
                    }

                    $total  = TelegramUser::count();
                    $today  = TelegramUser::whereDate('last_activity_at', date('Y-m-d'))->count();
                    $banned = TelegramUser::where('is_banned', true)->count();

                    try {
                        $me       = Bot::getMe();
                        $username = '@' . ($me->username ?? 'unknown');
                    } catch (\Throwable) {
                        $username = 'unavailable';
                    }

                    $ctx->answerCallback();
                    $ctx->reply(
                        "📊 *Bot Status*\n\n" .
                        "🤖 Bot: {$username}\n" .
                        "📦 Library: devflow/telegram-bot v1.3.0\n\n" .
                        "👥 Total users: {$total}\n" .
                        "✅ Active today: {$today}\n" .
                        "🚫 Banned: {$banned}",
                        ['parse_mode' => 'Markdown']
                    );
                });
            }
        }
        PHP;
    }

    private function welcomeText(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Texts;

        use Devflow\TelegramBot\Support\BotText;

        class WelcomeText extends BotText
        {
            protected static function translations(): array
            {
                return [
                    'en' => 'Hello, {name}! Welcome to the bot.',
                    // 'fa' => 'سلام، {name}! به ربات خوش آمدید.',
                ];
            }
        }
        PHP;
    }

    private function langEn(): string
    {
        return <<<'PHP'
        <?php

        // Dot-notation keys are supported: 'menu' => ['account' => '...'] is
        // reachable as $ctx->t('menu.account').
        return [
            'welcome' => 'Hello, {name}! Welcome to the bot.',
            'menu'    => [
                'account' => '📋 My Account',
                'help'    => '❓ Help',
            ],
        ];
        PHP;
    }

    private function langFa(): string
    {
        return <<<'PHP'
        <?php

        return [
            'welcome' => 'سلام، {name}! به ربات خوش آمدید.',
            'menu'    => [
                'account' => '📋 حساب من',
                'help'    => '❓ راهنما',
            ],
        ];
        PHP;
    }

    private function migrationTelegramUsers(): string
    {
        return <<<'SQL'
        CREATE TABLE IF NOT EXISTS `telegram_users` (
            `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `telegram_id`      BIGINT UNSIGNED NOT NULL,
            `chat_id`          BIGINT          NOT NULL,
            `first_name`       VARCHAR(255)    NOT NULL,
            `last_name`        VARCHAR(255)    NULL,
            `username`         VARCHAR(255)    NULL,
            `language_code`    VARCHAR(10)     NULL,
            `language`         VARCHAR(10)     NULL,
            `role`             VARCHAR(50)     NOT NULL DEFAULT 'user',
            `permissions`      JSON            NULL,
            `is_banned`        TINYINT(1)      NOT NULL DEFAULT 0,
            `ban_reason`       TEXT            NULL,
            `banned_at`        TIMESTAMP       NULL,
            `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
            `step`             VARCHAR(255)    NULL,
            `temp_data`        JSON            NULL,
            `rate_hits`        JSON            NULL,
            `current_panel`    VARCHAR(20)     NOT NULL DEFAULT 'user',
            `referral_code`    VARCHAR(32)     NULL,
            `invited_by`       BIGINT UNSIGNED NULL,
            `joined_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_activity_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            UNIQUE KEY `telegram_users_telegram_id_unique` (`telegram_id`),
            UNIQUE KEY `telegram_users_referral_code_unique` (`referral_code`),
            KEY `telegram_users_invited_by_foreign` (`invited_by`),
            CONSTRAINT `telegram_users_invited_by_foreign`
                FOREIGN KEY (`invited_by`) REFERENCES `telegram_users` (`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
    }

    private function migrationBotSettings(): string
    {
        return <<<'SQL'
        CREATE TABLE IF NOT EXISTS `bot_settings` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key`        VARCHAR(255) NOT NULL,
            `value`      TEXT         NULL,
            `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            UNIQUE KEY `bot_settings_key_unique` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
    }

    private function migrationBroadcasts(): string
    {
        return <<<'SQL'
        CREATE TABLE IF NOT EXISTS `telegram_broadcasts` (
            `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `message`          TEXT            NOT NULL,
            `type`             VARCHAR(50)     NOT NULL DEFAULT 'text',
            `options`          JSON            NULL,
            `status`           ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
            `total_recipients` INT UNSIGNED    NOT NULL DEFAULT 0,
            `sent_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
            `failed_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
            `scheduled_at`     TIMESTAMP       NULL,
            `started_at`       TIMESTAMP       NULL,
            `completed_at`     TIMESTAMP       NULL,
            `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            KEY `telegram_broadcasts_status_index` (`status`),
            KEY `telegram_broadcasts_scheduled_at_index` (`scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
    }

    private function info(string $msg): void
    {
        echo "\033[34mℹ\033[0m {$msg}";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }
}
