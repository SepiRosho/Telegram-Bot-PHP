<?php

namespace Devflow\TelegramBot\Laravel;

use Devflow\TelegramBot\Api\MissingTokenHttpClient;
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\DeleteWebhookCommand;
use Devflow\TelegramBot\Laravel\Console\SetWebhookCommand;
use Devflow\TelegramBot\Laravel\Console\WebhookInfoCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelegramBotServiceProvider extends ServiceProvider
{
    /** Env var config/telegram.php reads the token from — named in the missing-token error. */
    private const TOKEN_ENV = 'TELEGRAM_BOT_TOKEN';

    /**
     * Bundled migration => the name it is published under (minus the timestamp
     * Laravel's migrator requires). Listed in dependency order.
     */
    private const MIGRATIONS = [
        'CreateTelegramUsersTable.php'      => 'create_telegram_users_table.php',
        'CreateBotSettingsTable.php'        => 'create_bot_settings_table.php',
        'CreateTelegramBroadcastsTable.php' => 'create_telegram_broadcasts_table.php',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/telegram.php', 'telegram');

        $this->app->singleton(BotInstance::class, function ($app) {
            $config = $app['config']['telegram'];
            $token  = $config['token'] ?? null;

            // An unset token must not stop the app from booting: `composer
            // install` (package:discover), `config:cache` and CI all run
            // artisan before any secret exists. Hand the bot a client that
            // throws MissingTokenException on first use instead, so the
            // failure lands on the code that actually needs the API.
            if ($token === null || trim((string) $token) === '') {
                $bot = new BotInstance('missing-token', $config, new MissingTokenHttpClient(self::TOKEN_ENV));
            } else {
                $bot = new BotInstance($token, $config);
            }

            Bot::setInstance($bot);

            return $bot;
        });
    }

    public function boot(): void
    {
        // README.md and examples/05_laravel.php both show calling the static
        // Bot:: facade (Bot::onCommand(), etc.) directly from a consuming
        // app's own boot(). The static facade only learns about the instance
        // when the singleton factory in register() runs, and nothing forces
        // that to happen on its own — so resolve it here, in this provider's
        // own boot(), before any other provider's boot() can call Bot::*
        // (Laravel boots providers in registration order). It also re-points
        // Bot:: at *this* app's instance, which matters when several apps are
        // built in one process (a test suite creates one per test).
        $this->app->make(BotInstance::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/telegram.php' => config_path('telegram.php'),
            ], 'telegram-config');

            $this->publishes($this->migrationPublishMap(), 'telegram-migrations');

            $this->commands([
                SetWebhookCommand::class,
                DeleteWebhookCommand::class,
                WebhookInfoCommand::class,
            ]);
        }

        $this->registerWebhookRoute();
    }

    /**
     * Laravel's migrator only runs files named `<timestamp>_<name>.php`, so
     * publishing the bundled files under their own names would leave them
     * silently ignored. Stamp them on the way out, one second apart so they
     * keep their order. A file already published earlier keeps its name, so
     * re-running the publish (or --force) never creates a second copy.
     */
    private function migrationPublishMap(): array
    {
        $map  = [];
        $time = time();
        $i    = 0;

        foreach (self::MIGRATIONS as $source => $name) {
            $existing = glob(database_path('migrations/*_' . $name)) ?: [];

            $map[__DIR__ . '/../Database/Migrations/' . $source] = $existing[0]
                ?? database_path('migrations/' . date('Y_m_d_His', $time + $i) . '_' . $name);
            $i++;
        }

        return $map;
    }

    private function registerWebhookRoute(): void
    {
        $uri = $this->app['config']['telegram.webhook_route'];
        if (empty($uri)) {
            return;
        }

        // The cached route table already holds this route.
        if ($this->app->routesAreCached()) {
            return;
        }

        // A controller class rather than a closure: a closure over $this (the
        // provider) can't be serialized, which broke `route:cache`.
        $route = Route::post($uri, WebhookController::class)->name('telegram.webhook');

        $middleware = $this->app['config']['telegram.webhook_middleware'] ?? [];
        if (!empty($middleware)) {
            $route->middleware($middleware);
        }
    }
}
