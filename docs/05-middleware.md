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

## Per-route middleware

Every route registration method — `onCommand()`, `onText()`, `onCallbackQuery()`, `onStep()`, and the rest — accepts an optional `array $middleware = []` as its last argument. It runs only for that one route, layered on top of whatever you registered with `Bot::use()`:

```php
Bot::onCommand('broadcast', \App\Commands\BroadcastCommand::class, [
    \App\Middleware\AdminMiddleware::class,
]);
```

Use named arguments when you're constructing the middleware inline, so it reads clearly at the call site:

```php
Bot::onCommand(
    'send',
    \App\Commands\SendCommand::class,
    middleware: [new RateLimitMiddleware(maxHits: 3)],
);
```

**Ordering:** global middleware (`Bot::use()`) wraps route middleware, which wraps the handler. A global auth check registered with `Bot::use()` always runs before a route's own middleware, and neither has to know about the other:

```
Bot::use(...)          ← runs first
  route middleware      ← runs second
    handler              ← runs last
```

`onCallbackQuery()` and `onStep()` take the middleware array after their other optional arguments:

```php
Bot::onCallbackQuery('confirm_*', $handler, middleware: [$adminOnly]);
Bot::onStep('checkout.confirm_payment', $handler, types: ['text'], middleware: [$rateLimit]);
```

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

The primary way to apply this is as **route middleware** — pass it in the `$middleware` array of just the routes that need it, instead of registering it globally with `Bot::use()` (which would run it, and pay for the `$ctx->user()` lookup, on every single update):

```php
Bot::onCommand('broadcast', \App\Commands\BroadcastCommand::class, [
    \App\Middleware\AdminMiddleware::class,
]);

Bot::onCommand('stats', \App\Commands\StatsCommand::class, [
    \App\Middleware\AdminMiddleware::class,
]);
```

This composes cleanly with other route middleware — e.g. rate-limit admin commands more loosely than public ones:

```php
Bot::onCommand('broadcast', \App\Commands\BroadcastCommand::class, [
    \App\Middleware\AdminMiddleware::class,
    new RateLimitMiddleware(maxHits: 3, windowSeconds: 60),
]);
```

> Checking `$ctx->user()?->isAdmin()` inline at the top of a handler still works fine for a one-off command, but route middleware is preferred once you have more than one admin-only route — it keeps the guard out of the handler body and reusable across all of them.

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

### Localizing the block message

`message` accepts a `string` or a `\Closure(Context): string`. The constructor runs at registration time — before there's any `Context`, and therefore before any resolvable locale — so a plain string can't call `$ctx->t()`. Pass a closure instead; it's resolved fresh on every blocked request:

```php
Bot::use(new RateLimitMiddleware(
    maxHits: 10,
    windowSeconds: 60,
    message: fn(Context $ctx) => $ctx->t('rate_limited'),
));
```

`maxHits`, `windowSeconds`, and `message` are `protected` properties, so subclass `RateLimitMiddleware` if you need to override its behavior (e.g. a per-role limit) rather than reimplementing it from scratch.

---

## Example 5: Force-join gate

The classic "join our channel(s) first" pattern, ready-made. It checks membership via `getChatMember`, caches the result (TTL-based, in `bot_settings`) so it doesn't cost one API call per channel on every single update, and builds the join-prompt keyboard for you.

**Requirements:** Database must be active (uses `bot_settings` for the membership cache).

```php
use Devflow\TelegramBot\Middleware\ForceJoinMiddleware;

$forceJoin = new ForceJoinMiddleware(
    channels: ['@my_channel', '@my_group'],
    cacheTtl: 60, // seconds a "joined"/"not joined" result is trusted before re-checking
);

Bot::use($forceJoin);

// The "✅ I've joined" button on the prompt sends this callback data by default.
Bot::onCallbackQuery('force_join_verify', $forceJoin->verifyCallback());
```

A user who hasn't joined every listed channel gets a message with a join link per channel plus a verify button; everyone else passes through untouched. Channels can be `@username` or a full `https://t.me/...` URL (e.g. for private channel invite links).

---

## Generating a middleware

```bash
vendor/bin/devflow make:middleware MyMiddleware
```

Creates `app/Middleware/MyMiddleware.php` with the correct stub. Then register it in `bootstrap/app.php` with `Bot::use(\App\Middleware\MyMiddleware::class)`.

---

## Next step

[06-database.md](06-database.md) — Set up the database to unlock user tracking, banning, and wizard flows.
