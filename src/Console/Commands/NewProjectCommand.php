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
        echo "  \033[33m3.\033[0m Edit \033[36m.env\033[0m — replace BOT_TOKEN with your bot token from @BotFather\n";
        echo "  \033[33m4.\033[0m Point your Telegram webhook at: https://yourdomain.com/public/webhook.php\n";
        echo "  \033[33m5.\033[0m When adding new classes, run: composer dump-autoload\n";
        echo "\n";
        echo "  To enable user tracking, banning, and wizard flows:\n";
        echo "  \033[33m6.\033[0m Import the SQL files from \033[36mdatabase/migrations/\033[0m into your MySQL database\n";
        echo "     (phpMyAdmin: select your DB → Import → choose each .sql file)\n";
        echo "  \033[33m7.\033[0m Fill in DB_ credentials in .env\n";
        echo "  \033[33m8.\033[0m Uncomment the DB block + AuthMiddleware in bootstrap/app.php\n";
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
            'public',
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
            'app/Commands/StartCommand.php'             => $this->startCommand(),
            'app/Commands/HelpCommand.php'              => $this->helpCommand(),
            'app/Middleware/AuthMiddleware.php'         => $this->authMiddleware(),
            'app/Handlers/UserHandlers.php'             => $this->userHandlers(),
            'app/Handlers/AdminHandlers.php'            => $this->adminHandlers(),
            'app/Texts/WelcomeText.php'                 => $this->welcomeText(),
            'database/migrations/telegram_users.sql'    => $this->migrationTelegramUsers(),
            'database/migrations/bot_settings.sql'      => $this->migrationBotSettings(),
            'database/migrations/telegram_broadcasts.sql' => $this->migrationBroadcasts(),
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
        BOT_DATABASE=true

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
        PHP;
    }

    private function bootstrapApp(): string
    {
        return <<<'PHP'
        <?php

        use Devflow\TelegramBot\Bot;

        // ─── Database (optional) ───────────────────────────────────────────────
        // Enables $ctx->user(), $ctx->step(), $ctx->setTemp(), banning, rate limiting.
        //
        // To activate:
        //   1. Import the SQL files from database/migrations/ into your MySQL database
        //      phpMyAdmin: select your DB → Import → choose each .sql file
        //      Command line: mysql -u root my_bot < database/migrations/telegram_users.sql
        //   2. Fill in DB_ credentials in .env
        //   3. Uncomment the block below
        //   4. Add ['database' => true] to Bot::init() below
        //
        // use Illuminate\Database\Capsule\Manager as Capsule;
        //
        // $capsule = new Capsule;
        // $capsule->addConnection([
        //     'driver'    => env('DB_DRIVER', 'mysql'),
        //     'host'      => env('DB_HOST', '127.0.0.1'),
        //     'port'      => env('DB_PORT', '3306'),
        //     'database'  => env('DB_DATABASE', 'my_bot'),
        //     'username'  => env('DB_USERNAME', 'root'),
        //     'password'  => env('DB_PASSWORD', ''),
        //     'charset'   => 'utf8mb4',
        //     'collation' => 'utf8mb4_unicode_ci',
        // ]);
        // $capsule->setAsGlobal();
        // $capsule->bootEloquent();

        // ─── Bot init ──────────────────────────────────────────────────────────

        Bot::init(env('BOT_TOKEN'));
        // With database: Bot::init(env('BOT_TOKEN'), ['database' => true]);

        // ─── Middleware ────────────────────────────────────────────────────────
        // AuthMiddleware checks bans and updates last_activity_at.
        // Requires database to be active (uncomment after DB setup).

        // Bot::use(\App\Middleware\AuthMiddleware::class);

        // ─── Handlers ──────────────────────────────────────────────────────────
        // Each handler group class has a static register() method.
        // Split complex bots by adding more groups to this array.

        Bot::loadHandlers([
            \App\Handlers\UserHandlers::class,
            // \App\Handlers\AdminHandlers::class,
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
        // Errors are logged to PHP's error log for debugging.
        http_response_code(200);

        try {
            \Devflow\TelegramBot\Bot::run();
        } catch (\Throwable $e) {
            error_log('[devflow-bot] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        PHP;
    }

    private function startCommand(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Commands;

        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Context;
        use Devflow\TelegramBot\Handlers\HandlerInterface;

        class StartCommand implements HandlerInterface
        {
            public function handle(Context $ctx): void
            {
                $name = $ctx->from()?->firstName ?? 'there';

                $ctx->reply(
                    "Hello, {$name}!\n\nUse /help to see what I can do.",
                );
            }
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

        class UserHandlers
        {
            public static function register(): void
            {
                Bot::onCommand('start', \App\Commands\StartCommand::class);
                Bot::onCommand('help',  \App\Commands\HelpCommand::class);

                // Example: handle a specific wizard step
                // Bot::onStep('register.ask_name', function (Context $ctx) {
                //     $ctx->setTemp('name', $ctx->text());
                //     $ctx->setStep('register.ask_age');
                //     $ctx->reply('How old are you?');
                // });

                Bot::onText(function (Context $ctx) {
                    $ctx->reply($ctx->text());
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

        class AdminHandlers
        {
            public static function register(): void
            {
                Bot::onCommand('stats', function (Context $ctx) {
                    if (!$ctx->user()?->isAdmin()) {
                        return;
                    }

                    $ctx->reply('Bot stats: everything is running.');
                });

                Bot::onCommand('ban', function (Context $ctx) {
                    if (!$ctx->user()?->isAdmin()) {
                        return;
                    }

                    $args   = $ctx->message()->commandArgs();
                    $userId = (int) ($args[0] ?? 0);

                    if (!$userId) {
                        $ctx->reply('Usage: /ban <user_id>');
                        return;
                    }

                    \Devflow\TelegramBot\Database\Models\TelegramUser::where('telegram_id', $userId)
                        ->first()
                        ?->ban('Banned by admin');

                    $ctx->reply("User {$userId} banned.");
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
            `role`             VARCHAR(50)     NOT NULL DEFAULT 'user',
            `permissions`      JSON            NULL,
            `is_banned`        TINYINT(1)      NOT NULL DEFAULT 0,
            `ban_reason`       TEXT            NULL,
            `banned_at`        TIMESTAMP       NULL,
            `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
            `step`             VARCHAR(255)    NULL,
            `temp_data`        JSON            NULL,
            `rate_hits`        JSON            NULL,
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
