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

        // Generated rather than templated, so the manifest always describes the
        // library version actually installed.
        if ((new AiManifestCommand())->writeTo($root) !== null) {
            $this->line("  \033[32m+\033[0m .ai/api.json");
        }

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
        echo "  \033[33m6.\033[0m Fill in DB_ credentials in .env  (\033[36mDB_DRIVER=mariadb\033[0m for MariaDB)\n";
        echo "  \033[33m7.\033[0m Run \033[36mvendor/bin/devflow migrate\033[0m to create the database tables\n";
        echo "     (add your own tables/columns as new files in \033[36mdatabase/migrations/\033[0m — see docs/06-database.md)\n";
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
            'app/Models',
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
            'app/Models/User.php'                       => $this->userModel(),
            'lang/en.php'                                => $this->langEn(),
            'lang/fa.php'                                => $this->langFa(),
            // Coding agents read AGENTS.md (Cursor, Codex) or CLAUDE.md
            // (Claude Code) from the project root. Shipping both here means an
            // agent working on this bot never has to read the library's source
            // to learn the conventions.
            'AGENTS.md'                                  => $this->agentsMd($projectName),
            'CLAUDE.md'                                  => $this->claudeMd(),
            'logs/.gitignore'                           => "*.log\n",
            'app/Callbacks/.gitkeep'                    => '',
            'app/Flows/.gitkeep'                        => '',
            'app/Services/.gitkeep'                     => '',
            // database/migrations/ starts empty — the base tables
            // (telegram_users, bot_settings, telegram_broadcasts) ship as
            // migrations bundled with the package itself; `devflow migrate`
            // picks those up automatically. Add your own project-specific
            // migrations here (see docs/06-database.md).
            'database/migrations/.gitkeep'               => '',
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
        //   1. Fill in DB_ credentials in .env (MariaDB: set DB_DRIVER=mariadb)
        //   2. Run: vendor/bin/devflow migrate
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
        // ─── Chat types ────────────────────────────────────────────────────────
        // allowed_chat_types restricts every handler to private (one-to-one)
        // chats. This is the safe default: without it, anyone can add the bot
        // to a group and /start there, which registers a telegram_users row
        // whose chat_id is the *group's* id — inflating your user count and
        // sending every future broadcast into that group.
        //
        // To serve groups too, add 'group' and 'supergroup' here, or expose
        // just a few routes with Bot::chatTypes([...], fn() => ...).
        Bot::init(env('BOT_TOKEN'), [
            'database'           => true,
            'webhook_secret'     => env('WEBHOOK_SECRET') ?: null,
            'proxy'              => env('PROXY_URL') ?: null,
            'lang_path'          => __DIR__ . '/../lang',
            'default_locale'     => 'en',
            'allowed_chat_types' => ['private'],
            // Point at your own model (see app/Models/User.php) to add
            // columns/relationships/scopes on top of the base TelegramUser.
            'user_model'         => \App\Models\User::class,
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
                $ctx->reply($ctx->t('help'));
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
                    $ctx->reply($ctx->t('banned'));
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

        use App\Models\User;
        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Context;

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

                        $user = User::create([
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

                    $ctx->reply($ctx->t('welcome', ['name' => $name]), [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::reply([
                            [$ctx->t('menu.account'), $ctx->t('menu.help')],
                        ]),
                    ]);
                });

                // ─── /user — Switch to user panel ────────────────────────────────
                Bot::onCommand('user', function (Context $ctx) {
                    $ctx->user()?->update(['current_panel' => 'user']);

                    $ctx->reply($ctx->t('user_panel'), [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::reply([
                            [$ctx->t('menu.account'), $ctx->t('menu.help')],
                        ]),
                    ]);
                });

                Bot::onCommand('help', \App\Commands\HelpCommand::class);

                // ─── Group guard ──────────────────────────────────────────────────
                // 'allowed_chat_types' => ['private'] in bootstrap/app.php keeps
                // every handler in this file private-only. my_chat_member is
                // deliberately exempt from that filter, because it is the one
                // update that tells a bot it was added somewhere — which is
                // exactly what a private-only bot needs in order to leave again.
                //
                // Delete this handler if you later want the bot to stay in groups.
                Bot::onMyChatMember(function (Context $ctx) {
                    $chat = $ctx->chat();

                    if ($chat === null || $chat->isPrivate()) {
                        return;
                    }

                    if ($ctx->update()->myChatMember?->userJoined()) {
                        Bot::leaveChat($chat->id);
                    }
                });

                // ─── Fallback ─────────────────────────────────────────────────────
                Bot::onText(function (Context $ctx) {
                    // Reply-keyboard buttons arrive as plain text in whatever
                    // locale rendered them, so match the label back to its key
                    // rather than comparing against one language's string.
                    $key = \Devflow\TelegramBot\Support\Lang::findKey((string) $ctx->text(), ['en', 'fa']);

                    match ($key) {
                        'menu.help'    => $ctx->reply($ctx->t('help')),
                        'menu.account' => $ctx->reply($ctx->t('user_panel')),
                        default        => $ctx->reply($ctx->t('echo', ['text' => $ctx->text()])),
                    };
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

        use App\Models\User;
        use Devflow\TelegramBot\Bot;
        use Devflow\TelegramBot\Context;

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

                    $ctx->reply($ctx->t('admin.panel', ['name' => $name]), [
                        'reply_markup' => \Devflow\TelegramBot\Support\Keyboard::inline([
                            [\Devflow\TelegramBot\Support\Keyboard::button($ctx->t('admin.bot_status_button'), 'bot_status')],
                        ]),
                    ]);
                });

                // ─── Bot Status ───────────────────────────────────────────────────
                Bot::onCallbackQuery('bot_status', function (Context $ctx) {
                    if (!$ctx->user()?->isAdmin()) {
                        $ctx->answerCallback($ctx->t('admin.access_denied'), true);
                        return;
                    }

                    $total  = User::count();
                    $today  = User::whereDate('last_activity_at', date('Y-m-d'))->count();
                    $banned = User::where('is_banned', true)->count();

                    try {
                        $me       = Bot::getMe();
                        $username = '@' . ($me->username ?? 'unknown');
                    } catch (\Throwable) {
                        $username = 'unavailable';
                    }

                    // Read from Composer rather than hardcoding, so this line
                    // doesn't go stale the first time the library is updated.
                    $version = class_exists(\Composer\InstalledVersions::class)
                        ? \Composer\InstalledVersions::getPrettyVersion('devflow/telegram-bot')
                        : 'unknown';

                    $ctx->answerCallback();
                    $ctx->reply(
                        $ctx->t('admin.status', [
                            'username' => $username,
                            'version'  => $version,
                            'total'    => $total,
                            'today'    => $today,
                            'banned'   => $banned,
                        ]),
                        ['parse_mode' => 'Markdown']
                    );
                });
            }
        }
        PHP;
    }

    private function userModel(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Models;

        use Devflow\TelegramBot\Database\Models\TelegramUser;

        /**
         * Extension point for your own columns/relationships/scopes on top of
         * the library's telegram_users table. Wired in via the `user_model`
         * config key in bootstrap/app.php, so $ctx->user() and
         * TelegramUser::firstOrCreate() (used for auto-registration) both
         * return instances of this class instead of the base model.
         */
        class User extends TelegramUser
        {
        }
        PHP;
    }

    /**
     * Project-scoped agent brief. Deliberately about *this project's* layout
     * and conventions, deferring the full library surface to the copy in the
     * package — duplicating that here would rot on the next library upgrade.
     */
    private function agentsMd(string $projectName): string
    {
        return <<<MARKDOWN
        # AGENTS.md — {$projectName}

        A Telegram bot built on [devflow/telegram-bot](https://packagist.org/packages/devflow/telegram-bot).

        ## Read this first

        The complete library reference is **`vendor/devflow/telegram-bot/AGENTS.md`** — routing,
        Context, config, keyboards, flows, i18n, middleware, database schema, CLI, testing.
        Read that file rather than browsing `vendor/devflow/telegram-bot/src/`.

        Every method signature is in `.ai/api.json` (regenerate: `vendor/bin/devflow ai:manifest`).

        ## Layout

        ```
        bootstrap/app.php        Bot::init(), database, middleware, loadHandlers()  ← wire things here
        bootstrap/helpers.php    env(), saveLog(), botLog()
        public/webhook.php       Entry point; always returns HTTP 200
        app/Commands/            One class per command (implements HandlerInterface)
        app/Callbacks/           Callback-query handlers
        app/Handlers/            Handler groups with a static register()
        app/Middleware/          MiddlewareInterface implementations
        app/Flows/               Multi-step wizards (onStep)
        app/Models/User.php      Extends TelegramUser — add your columns/relations here
        app/Services/            Business logic
        database/migrations/     devflow make:migration writes here
        lang/en.php, lang/fa.php Translation keys for \$ctx->t()
        logs/                    Daily log files
        ```

        ## Conventions for this project

        - **Private chats only.** `bootstrap/app.php` sets `'allowed_chat_types' => ['private']`, and
          `UserHandlers` leaves any group the bot is added to. To support groups, change that config
          and delete the `onMyChatMember` guard.
        - **Never `json_encode()`** anything passed to the library — `reply_markup` and every
          `\$options` value must be a plain PHP array. `Keyboard::*` already returns arrays.
        - **All user-facing strings go in `lang/`**, reached via `\$ctx->t('key')`. Never hardcode
          text in a handler; add the key to *both* `lang/en.php` and `lang/fa.php`.
        - **New handler groups** get a static `register()` and must be added to `Bot::loadHandlers()`
          in `bootstrap/app.php`.
        - **New classes** need `composer dump-autoload`.
        - **Schema changes** go through `vendor/bin/devflow make:migration <snake_case_name>`, never
          by editing the database by hand.

        ## Commands

        ```bash
        vendor/bin/devflow doctor              # diagnose env, token, DB, routes, webhook — run this first
        vendor/bin/devflow routes              # list routes in evaluation order
        vendor/bin/devflow poll                # local dev without a webhook
        vendor/bin/devflow migrate             # run pending migrations
        vendor/bin/devflow make:command <Name>
        vendor/bin/devflow make:migration <name>
        ```

        ## Before finishing a change

        1. `vendor/bin/devflow routes` — confirm the new route is registered and not shadowed.
        2. `vendor/bin/devflow doctor` — confirm nothing regressed.
        3. Check both `lang/` files have every key you referenced.

        MARKDOWN;
    }

    private function claudeMd(): string
    {
        return <<<'MARKDOWN'
        See [AGENTS.md](AGENTS.md) for this project's layout, conventions and commands.

        The full library reference is `vendor/devflow/telegram-bot/AGENTS.md`; every method
        signature is in `.ai/api.json`. Read those instead of browsing the library's `src/`.

        MARKDOWN;
    }

    private function langEn(): string
    {
        return <<<'PHP'
        <?php

        // Dot-notation keys are supported: 'menu' => ['account' => '...'] is
        // reachable as $ctx->t('menu.account').
        return [
            'welcome'    => '👋 Hello, {name}! Welcome to the bot.',
            'user_panel' => '👤 User panel.',
            'echo'       => 'You said: {text}',
            'banned'     => 'You are banned from using this bot.',
            'help'       => "Available commands:\n/start — Start the bot\n/help  — Show this message",
            'menu'       => [
                'account' => '📋 My Account',
                'help'    => '❓ Help',
            ],
            'admin' => [
                'panel'             => '🔧 Admin panel, {name}.',
                'bot_status_button' => '📊 Bot Status',
                'access_denied'     => 'Access denied.',
                'status'            => "📊 *Bot Status*\n\n🤖 Bot: {username}\n📦 Library: devflow/telegram-bot {version}\n\n👥 Total users: {total}\n✅ Active today: {today}\n🚫 Banned: {banned}",
            ],
        ];
        PHP;
    }

    private function langFa(): string
    {
        return <<<'PHP'
        <?php

        return [
            'welcome'    => '👋 سلام، {name}! به ربات خوش آمدید.',
            'user_panel' => '👤 پنل کاربری.',
            'echo'       => 'شما گفتید: {text}',
            'banned'     => 'شما از استفاده از این ربات مسدود شده‌اید.',
            'help'       => "دستورات موجود:\n/start — شروع ربات\n/help  — نمایش این پیام",
            'menu'       => [
                'account' => '📋 حساب من',
                'help'    => '❓ راهنما',
            ],
            'admin' => [
                'panel'             => '🔧 پنل مدیریت، {name}.',
                'bot_status_button' => '📊 وضعیت ربات',
                'access_denied'     => 'دسترسی غیرمجاز.',
                'status'            => "📊 *وضعیت ربات*\n\n🤖 ربات: {username}\n📦 کتابخانه: devflow/telegram-bot {version}\n\n👥 کل کاربران: {total}\n✅ فعال امروز: {today}\n🚫 مسدود: {banned}",
            ],
        ];
        PHP;
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
