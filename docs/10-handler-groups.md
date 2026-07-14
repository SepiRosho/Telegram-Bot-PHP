# 10 — Handler Groups

Handler groups let you split your bot's logic across multiple files instead of putting everything in `bootstrap/app.php`. This is useful once your bot grows beyond a handful of commands.

---

## The pattern

Each group is a PHP class with one static method, `register()`, that calls `Bot::on*()` methods:

```php
<?php

namespace App\Handlers;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

class UserHandlers
{
    public static function register(): void
    {
        Bot::onCommand('start', \App\Commands\StartCommand::class);
        Bot::onCommand('help',  \App\Commands\HelpCommand::class);
        Bot::onText(function (Context $ctx) {
            $ctx->reply($ctx->text());
        });
    }
}
```

Then load it in `bootstrap/app.php`:

```php
Bot::loadHandlers([
    \App\Handlers\UserHandlers::class,
]);
```

`loadHandlers()` calls `::register()` on each class in order. The handlers are registered exactly as if you had written them inline.

---

## A scaffolded project already has two groups

When you run `vendor/bin/devflow new my-bot`, you get:

```
app/
└── Handlers/
    ├── UserHandlers.php   ← /start, /help, onText
    └── AdminHandlers.php  ← /stats, /ban (admin-guarded)
```

`AdminHandlers` is commented out in `bootstrap/app.php` by default. Uncomment it when you're ready:

```php
Bot::loadHandlers([
    \App\Handlers\UserHandlers::class,
    \App\Handlers\AdminHandlers::class,  // uncomment to activate admin commands
]);
```

---

## Practical ways to split

### By user type

```
app/Handlers/
├── UserHandlers.php     — normal user commands and text flows
└── AdminHandlers.php    — /stats, /ban, /broadcast (guarded by isAdmin())
```

### By feature

```
app/Handlers/
├── OnboardingHandlers.php   — /start, registration wizard steps
├── ShopHandlers.php         — /shop, /cart, /checkout
└── SupportHandlers.php      — /help, /contact, ticket flows
```

### By update type

```
app/Handlers/
├── CommandHandlers.php      — all onCommand()
├── CallbackHandlers.php     — all onCallbackQuery()
└── TextHandlers.php         — onStep() and onText()
```

Choose whatever split keeps related logic together.

---

## Registration order matters

Handlers are checked in the order they are registered — first match wins. `loadHandlers()` calls each class's `register()` in the order you pass them. So:

```php
Bot::loadHandlers([
    \App\Handlers\UserHandlers::class,   // registered first — checked first
    \App\Handlers\AdminHandlers::class,  // registered second
]);
```

If `UserHandlers` has an `onText` catch-all and `AdminHandlers` also has one, the `UserHandlers` version wins. Put general catch-alls last.

An `onMessage()` catch-all matches **every** message type, including commands — so an early `onMessage()` handler can silently swallow `/admin` before it ever reaches a later-registered command route, with no error or warning. If you want an "unknown command" responder specifically, use `onUnknownCommand()` instead of `onMessage()`:

```php
Bot::onUnknownCommand(function (Context $ctx) {
    $ctx->reply("Unknown command: /{$ctx->message()->command()}");
});
```

Unlike `onMessage()`, `onUnknownCommand()` is **order-independent** — it's checked against the full set of commands registered anywhere via `onCommand()`, so it never fires for a command that *is* handled elsewhere, no matter where you register it relative to that handler.

### Debugging which route matched

Pass `'debug' => true` to `Bot::init()` to log which route matched every update (or that none did) via `Support\Log` — helpful when a handler you expect to fire doesn't:

```php
Bot::init(env('BOT_TOKEN'), ['debug' => true, /* ... */]);
```

---

## Full example: a shop bot

```php
// app/Handlers/ShopHandlers.php
class ShopHandlers
{
    public static function register(): void
    {
        Bot::onCommand('shop', function (Context $ctx) {
            $ctx->reply("Welcome to the shop!\n/cart — view your cart\n/checkout — place an order");
        });

        Bot::onCommand('cart', function (Context $ctx) {
            // show cart from DB
        });

        Bot::onCallbackQuery('add_item_*', function (Context $ctx) {
            $itemId = str_replace('add_item_', '', $ctx->callbackData());
            // add to cart
            $ctx->answerCallback('Added to cart!');
        });
    }
}

// app/Handlers/CheckoutHandlers.php
class CheckoutHandlers
{
    public static function register(): void
    {
        Bot::onStep('checkout.confirm_address', function (Context $ctx) {
            $ctx->setTemp('address', $ctx->text());
            $ctx->setStep('checkout.confirm_payment');
            $ctx->reply('Which payment method? (card / cash)');
        });

        Bot::onStep('checkout.confirm_payment', function (Context $ctx) {
            $ctx->setTemp('payment', $ctx->text());
            $ctx->clearFlow();
            $ctx->reply("Order placed!\nAddress: {$ctx->temp('address')}\nPayment: {$ctx->temp('payment')}");
        });
    }
}

// bootstrap/app.php
Bot::loadHandlers([
    \App\Handlers\UserHandlers::class,
    \App\Handlers\ShopHandlers::class,
    \App\Handlers\CheckoutHandlers::class,
    \App\Handlers\AdminHandlers::class,
]);
```

---

## Next step

[12-i18n.md](12-i18n.md) — Localize your bot's messages with key-based translations.
