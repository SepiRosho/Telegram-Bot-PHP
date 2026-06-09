# 05 — Middleware

## What is middleware?

Middleware is code that runs **before every handler**, no matter which one matches. Think of it like a bouncer at the door — every user walks past the bouncer before they reach the handler.

```
Telegram update arrives
        ↓
  [ Middleware 1 ]   ← runs first (e.g. log the request)
        ↓
  [ Middleware 2 ]   ← runs second (e.g. check if user is banned)
        ↓
  [ Your handler ]   ← only runs if all middleware called $next()
```

A middleware can:
- Let the request through (call `$next($ctx)`)
- Block the request (don't call `$next`, optionally send a reply)

---

## Anatomy of a middleware class

```php
<?php

namespace App\Middleware;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        // Code here runs BEFORE the handler
        error_log('Incoming from user: ' . $ctx->userId());

        $next($ctx); // Pass control to the next middleware or handler

        // Code here runs AFTER the handler (optional)
    }
}
```

The `$next` callable is what passes control forward. If you don't call it, the chain stops — the handler never runs.

---

## Registering middleware

In `bootstrap/app.php`, use `Bot::use()` **before** your handlers:

```php
Bot::use(\App\Middleware\LogMiddleware::class);
Bot::use(\App\Middleware\AuthMiddleware::class);

Bot::onCommand('start', \App\Commands\StartCommand::class);
Bot::onText($handler);
```

Middleware runs in registration order. In this example, `LogMiddleware` runs first, then `AuthMiddleware`, then the matching handler.

---

## Example 1: Logging

```php
// app/Middleware/LogMiddleware.php

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        $userId = $ctx->userId();
        $text   = $ctx->text() ?? '[non-text]';
        error_log("[{$userId}] {$text}");

        $next($ctx);
    }
}
```

---

## Example 2: Ban check (requires database)

The pre-generated `AuthMiddleware` already does this:

```php
// app/Middleware/AuthMiddleware.php

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        if ($ctx->user()?->is_banned) {
            $ctx->reply('You are banned from using this bot.');
            return; // Stop here — handler never runs
        }

        $ctx->user()?->touchActivity(); // Update last_activity_at in DB

        $next($ctx); // Continue to the handler
    }
}
```

> **Note:** This middleware requires the database to be set up. Uncomment it in `bootstrap/app.php` only after completing [06-database.md](06-database.md).

---

## Example 3: Admin-only guard

```php
// app/Middleware/AdminMiddleware.php

class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        if (!$ctx->user()?->isAdmin()) {
            $ctx->reply('This command is for admins only.');
            return;
        }

        $next($ctx);
    }
}
```

You can register this only for specific commands by applying it inline, or by creating separate bot instances for admin routes. The simplest approach for most bots is to check inside the handler:

```php
Bot::onCommand('broadcast', function (Context $ctx) {
    if (!$ctx->user()?->isAdmin()) {
        $ctx->reply('Admin only.');
        return;
    }

    // ... broadcast logic
});
```

---

## Example 4: Rate limiting — built-in DB-backed version

The library ships a ready-made rate limiter that uses the database to track request timestamps. It survives across requests (unlike in-memory solutions).

**Requirements:** Database must be active (see [06-database.md](06-database.md)).

```php
use Devflow\TelegramBot\Middleware\RateLimitMiddleware;

// In bootstrap/app.php:
Bot::use(new RateLimitMiddleware(
    maxHits: 10,          // max 10 requests...
    windowSeconds: 60,    // ...per 60-second rolling window
    message: 'Slow down! You are sending too many messages.',
));
```

The `message` and `windowSeconds` are optional — defaults are 10 hits / 60 seconds / "Too many requests. Please slow down."

Hit timestamps are stored in the `rate_hits` JSON column on the `telegram_users` table. Old timestamps are pruned on each request.

> **Note:** `RateLimitMiddleware` silently passes through if `$ctx->user()` is null (no DB or untracked user), so it never breaks bots that have the database disabled.

---

## Generating a middleware

```bash
vendor/bin/devflow make:middleware MyMiddleware
```

Creates `app/Middleware/MyMiddleware.php` with the correct stub. Then register it in `bootstrap/app.php` with `Bot::use(\App\Middleware\MyMiddleware::class)`.

---

## Next step

[06-database.md](06-database.md) — Set up the database to unlock user tracking, banning, and wizard flows.
