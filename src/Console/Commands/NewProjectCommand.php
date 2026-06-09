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
        echo "  \033[33m3.\033[0m cp .env.example .env  (fill in BOT_TOKEN + DB credentials)\n";
        echo "  \033[33m4.\033[0m Run the SQL migrations from vendor/devflow/telegram-bot/database/migrations/\n";
        echo "  \033[33m5.\033[0m Point your Telegram webhook at: https://yourdomain.com/public/webhook.php\n";
        echo "\n";
    }

    private function makeDirectories(string $root): void
    {
        $dirs = [
            'app/Commands',
            'app/Callbacks',
            'app/Middleware',
            'app/Flows',
            'app/Services',
            'bootstrap',
            'config',
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
            'bootstrap/helpers.php'      => $this->bootstrapHelpers(),
            'bootstrap/app.php'          => $this->bootstrapApp(),
            'config/bot.php'             => $this->configBot(),
            'public/webhook.php'         => $this->publicWebhook(),
            'app/Commands/StartCommand.php'         => $this->startCommand(),
            'app/Commands/HelpCommand.php'          => $this->helpCommand(),
            'app/Middleware/AuthMiddleware.php'     => $this->authMiddleware(),
            'app/Callbacks/.gitkeep'                => '',
            'app/Flows/.gitkeep'                    => '',
            'app/Services/.gitkeep'                 => '',
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
        use Illuminate\Database\Capsule\Manager as Capsule;

        // ─── Database ──────────────────────────────────────────────────────────
        // Remove this block entirely if you are not using the database features.

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
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // ─── Bot init ──────────────────────────────────────────────────────────

        Bot::init(env('BOT_TOKEN'), [
            'database' => (bool) env('BOT_DATABASE', true),
        ]);

        // ─── Middleware ────────────────────────────────────────────────────────
        // Middleware runs before every matched handler, in registration order.

        Bot::use(\App\Middleware\AuthMiddleware::class);

        // ─── Commands ──────────────────────────────────────────────────────────

        Bot::onCommand('start', \App\Commands\StartCommand::class);
        Bot::onCommand('help',  \App\Commands\HelpCommand::class);

        // ─── Callback queries ──────────────────────────────────────────────────
        // Use * as a wildcard in the pattern.

        // Bot::onCallbackQuery('confirm_*', \App\Callbacks\ConfirmCallback::class);

        // ─── Text / wizard flows ───────────────────────────────────────────────
        // Uncomment and route to your flow handler once you add one.

        // Bot::onText(function (\Devflow\TelegramBot\Context $ctx) {
        //     // Route to the active flow based on the user's current step
        //     match (true) {
        //         str_starts_with((string) $ctx->step(), 'register.') => (new \App\Flows\RegisterFlow())->handle($ctx),
        //         default => $ctx->reply("Use /start to begin."),
        //     };
        // });
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

        // Load .env
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        // Bootstrap the bot (init, DB, middleware, handlers)
        require __DIR__ . '/../bootstrap/app.php';

        // Dispatch the incoming Telegram update
        \Devflow\TelegramBot\Bot::run();
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
                $name = $ctx->user()?->first_name ?? $ctx->from()?->firstName ?? 'there';

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

    private function info(string $msg): void
    {
        echo "\033[34mℹ\033[0m {$msg}";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }
}
