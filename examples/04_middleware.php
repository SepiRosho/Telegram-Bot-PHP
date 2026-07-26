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
 *  - Per-route middleware (admin-only guard applied to a single command)
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

// ---- Register global middleware (runs for every update) ---------------------

Bot::use(LogMiddleware::class);
Bot::use(BanCheckMiddleware::class);

// DB-backed rate limiter: max 10 messages per 60 seconds per user.
// Requires database. Silently passes through if DB is unavailable.
Bot::use(new RateLimitMiddleware(maxHits: 10, windowSeconds: 60));

// ---- Routes -----------------------------------------------------------------

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('Welcome! You passed all middleware checks.');
});

// Admin-only command — every registration method accepts an optional trailing
// array of route-scoped middleware, applied only to this one route on top of
// the global middleware registered above via Bot::use(). This is the
// preferred way to guard a handful of admin routes, instead of checking
// $ctx->user()?->isAdmin() inline or wrapping the handler by hand.
Bot::onCommand('broadcast', function (Context $ctx) {
    $ctx->reply('Broadcast feature coming soon!');
}, [$adminOnly]);

// Per-route middleware composes: stack the closure with a tighter rate limit
// for this specific command, using named arguments for readability.
Bot::onCommand(
    'stats',
    function (Context $ctx) {
        $ctx->reply('📊 Stats: all systems normal.');
    },
    middleware: [$adminOnly, new RateLimitMiddleware(maxHits: 3, windowSeconds: 60)],
);

Bot::run();
