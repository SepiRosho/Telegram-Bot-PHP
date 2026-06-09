<?php

/**
 * Middleware examples — auth guard, rate limiting, logging.
 *
 * Demonstrates:
 *  - Closure-based middleware
 *  - Class-based middleware (MiddlewareInterface)
 *  - Short-circuiting (stopping the chain)
 *  - DB-backed rate limiting via RateLimitMiddleware
 *  - Chaining multiple middleware
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;
use Devflow\TelegramBot\Middleware\RateLimitMiddleware;

Bot::init('YOUR_BOT_TOKEN', ['database' => true]);

// ---- Class-based middleware -------------------------------------------------

class BanCheckMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        if ($ctx->user()?->is_banned) {
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
    if (!$ctx->user()?->isAdmin()) {
        $ctx->reply('This command is for admins only.');
        return;
    }

    $next($ctx);
};

// ---- Register middleware (runs for every update) ----------------------------

Bot::use(LogMiddleware::class);
Bot::use(BanCheckMiddleware::class);

// DB-backed rate limiter: max 10 messages per 60 seconds per user.
// Requires database. Silently passes through if DB is unavailable.
Bot::use(new RateLimitMiddleware(maxHits: 10, windowSeconds: 60));

// ---- Routes -----------------------------------------------------------------

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('Welcome! You passed all middleware checks.');
});

// Admin-only command — wrap the handler with the $adminOnly closure
Bot::onCommand('broadcast', function (Context $ctx) use ($adminOnly) {
    $adminOnly($ctx, function (Context $ctx) {
        $ctx->reply('Broadcast feature coming soon!');
    });
});

Bot::run();
