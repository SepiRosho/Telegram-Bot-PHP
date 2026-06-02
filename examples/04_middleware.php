<?php

/**
 * Middleware examples — auth guard, rate limiting, logging.
 *
 * Demonstrates:
 *  - Closure-based middleware
 *  - Class-based middleware (MiddlewareInterface)
 *  - Short-circuiting (stopping the chain)
 *  - Chaining multiple middleware
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;

Bot::init('YOUR_BOT_TOKEN');

// ---- Class-based middleware -------------------------------------------------

class BanCheckMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        $user = $ctx->user();

        if ($user?->is_banned) {
            $ctx->reply('You are banned from using this bot.');
            return; // stops the chain — handler never runs
        }

        $next($ctx);
    }
}

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        $userId = $ctx->userId();
        $text   = $ctx->text() ?? $ctx->callbackData() ?? '[non-text]';
        error_log("[Bot] user:{$userId} — {$text}");

        $next($ctx);
    }
}

// ---- Closure-based middleware -----------------------------------------------

$adminOnly = function (Context $ctx, callable $next): void {
    $user = $ctx->user();

    if (!$user?->isAdmin()) {
        $ctx->reply('⛔ This command is for admins only.');
        return;
    }

    $next($ctx);
};

// ---- Register middleware (runs for every update) ----------------------------

Bot::use(LogMiddleware::class);
Bot::use(BanCheckMiddleware::class);

// ---- Routes -----------------------------------------------------------------

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('Welcome! You passed the ban check ✅');
});

// Admin-only command — attach middleware inline by wrapping the handler
Bot::onCommand('broadcast', function (Context $ctx) use ($adminOnly) {
    // The $adminOnly middleware only applies to this handler
    $adminOnly($ctx, function (Context $ctx) {
        $ctx->reply('📢 Broadcast feature coming soon!');
    });
});

Bot::run();
