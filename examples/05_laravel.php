<?php

/**
 * Laravel integration guide (not a runnable standalone file).
 *
 * 1. INSTALL
 * ----------
 *    composer require devflow/telegram-bot
 *
 *    The ServiceProvider and TelegramBot facade are auto-discovered.
 *
 * 2. PUBLISH CONFIG & MIGRATIONS
 * --------------------------------
 *    php artisan vendor:publish --tag=telegram-config
 *    php artisan vendor:publish --tag=telegram-migrations
 *    php artisan migrate
 *
 * 3. ENV
 * ------
 *    TELEGRAM_BOT_TOKEN=your_bot_token_here
 *    TELEGRAM_WEBHOOK_SECRET=optional_secret
 *    TELEGRAM_DATABASE=true
 *    TELEGRAM_WEBHOOK_ROUTE=telegram/webhook
 *
 * 4. REGISTER YOUR HANDLERS
 * --------------------------
 *    The webhook route is auto-registered at POST /telegram/webhook.
 *    Register your handlers in a service provider or routes/telegram.php.
 */

// ---- app/Providers/TelegramServiceProvider.php ----

namespace App\Providers;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Middleware\AuthMiddleware;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Bot::use(AuthMiddleware::class);

        Bot::onCommand('start', StartHandler::class);

        Bot::onCommand('help', function (Context $ctx) {
            $ctx->reply('Use /start to begin.');
        });

        Bot::onText(function (Context $ctx) {
            $ctx->reply('Got your message: ' . $ctx->text());
        });

        Bot::onCallbackQuery('confirm_*', function (Context $ctx) {
            $ctx->answerCallback('Confirmed!');
        });
    }
}

// ---- app/Telegram/Handlers/StartHandler.php ----

namespace App\Telegram\Handlers;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;

class StartHandler implements HandlerInterface
{
    public function handle(Context $ctx): void
    {
        $user = $ctx->user(); // TelegramUser Eloquent model
        $name = $user?->first_name ?? $ctx->from()?->firstName ?? 'there';

        $ctx->reply(
            text: "Hello, <b>{$name}</b>! Welcome to the bot.",
            options: ['parse_mode' => 'HTML'],
        );
    }
}

// ---- app/Telegram/Middleware/AuthMiddleware.php ----

namespace App\Telegram\Middleware;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        $user = $ctx->user();

        if ($user?->is_banned) {
            $ctx->reply('You are banned.');
            return;
        }

        // Update last activity
        $user?->touchActivity();

        $next($ctx);
    }
}

// ---- Register the provider in config/app.php (if not auto-discovered) ----
// 'providers' => [
//     App\Providers\TelegramServiceProvider::class,
// ],

// ---- Set your webhook (one-time) ----
// php artisan telegram:set-webhook https://yourdomain.com/telegram/webhook
// php artisan telegram:webhook-info
