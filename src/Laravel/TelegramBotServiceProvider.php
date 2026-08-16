<?php

namespace Devflow\TelegramBot\Laravel;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Laravel\Console\DeleteWebhookCommand;
use Devflow\TelegramBot\Laravel\Console\SetWebhookCommand;
use Devflow\TelegramBot\Laravel\Console\WebhookInfoCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelegramBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/telegram.php', 'telegram');

        $this->app->singleton(BotInstance::class, function ($app) {
            return Bot::init(
                token: $app['config']['telegram.token'],
                config: $app['config']['telegram'],
            );
        });
    }

    public function boot(): void
    {
        // README.md and examples/05_laravel.php both show calling the static
        // Bot:: facade (Bot::onCommand(), etc.) directly from a consuming
        // app's own boot(). Bot::init() only happens as a side effect of the
        // BotInstance::class singleton factory in register() above, and
        // nothing forces that factory to resolve on its own — so without this,
        // the static facade is never actually initialized by the time that
        // documented code runs, and it throws BotNotInitializedException.
        // Resolving it here, in this provider's own boot(), guarantees it
        // happens before any other provider's boot() gets a chance to call
        // Bot::* (Laravel always boots providers in registration order).
        $this->app->make(BotInstance::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/telegram.php' => config_path('telegram.php'),
            ], 'telegram-config');

            $this->publishes([
                __DIR__ . '/../Database/Migrations/' => database_path('migrations'),
            ], 'telegram-migrations');

            $this->commands([
                SetWebhookCommand::class,
                DeleteWebhookCommand::class,
                WebhookInfoCommand::class,
            ]);
        }

        $this->registerWebhookRoute();
    }

    private function registerWebhookRoute(): void
    {
        $uri = $this->app['config']['telegram.webhook_route'];
        if (empty($uri)) {
            return;
        }

        Route::post($uri, function () {
            $this->app->make(BotInstance::class)->run();
        })->name('telegram.webhook');
    }
}
