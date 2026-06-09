# 03 — Handlers

A **handler** is the code that runs when a user sends a specific type of message. You register handlers in `bootstrap/app.php` and write their logic in `app/`.

---

## The two styles: closures and classes

**Closure** — inline, good for short logic:

```php
Bot::onCommand('ping', function (Context $ctx) {
    $ctx->reply('Pong!');
});
```

**Class** — separate file, good for anything longer than a few lines:

```php
// bootstrap/app.php
Bot::onCommand('ping', \App\Commands\PingCommand::class);
```

```php
// app/Commands/PingCommand.php
<?php

namespace App\Commands;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;

class PingCommand implements HandlerInterface
{
    public function handle(Context $ctx): void
    {
        $ctx->reply('Pong!');
    }
}
```

Both are equivalent. Use closures for quick one-liners; use classes once the logic grows.

---

## All registration methods

### Commands — `/commandname`

```php
Bot::onCommand('start',  \App\Commands\StartCommand::class);
Bot::onCommand('help',   \App\Commands\HelpCommand::class);
Bot::onCommand('cancel', function (Context $ctx) {
    $ctx->reply('Cancelled.');
});
```

Matches when the user sends `/start`, `/help`, etc. The slash is optional in the registration — `'start'` and `'/start'` both work.

### Text messages

```php
Bot::onText(function (Context $ctx) {
    $ctx->reply('You said: ' . $ctx->text());
});
```

Matches any plain text message that is **not** a command. Does not match `/start`, but matches `hello` or `what time is it?`.

### Any message (text, photo, video, sticker, etc.)

```php
Bot::onMessage(function (Context $ctx) {
    $ctx->reply('Got your message!');
});
```

Matches every message type. Usually used as a catch-all fallback.

### Inline button presses (callback queries)

```php
Bot::onCallbackQuery('confirm', function (Context $ctx) {
    $ctx->answerCallback('Confirmed!');
});
```

Matches when the user presses an inline keyboard button whose `callback_data` equals `confirm`. See [how to create inline keyboards](README.md#inline-keyboards).

**Wildcard matching** — use `*` to match variable parts:

```php
// Matches: delete_1, delete_42, delete_999
Bot::onCallbackQuery('delete_*', function (Context $ctx) {
    $id = str_replace('delete_', '', $ctx->callbackData());
    $ctx->reply("Deleting item {$id}");
    $ctx->answerCallback();
});
```

### Photos

```php
Bot::onPhoto(function (Context $ctx) {
    $ctx->reply('Nice photo!');
});
```

### Documents / files

```php
Bot::onDocument(function (Context $ctx) {
    $ctx->reply('Got your file.');
});
```

### Inline queries (when users type `@YourBot something`)

```php
Bot::onInlineQuery(function (Context $ctx) {
    // $ctx->update()->inlineQuery->query contains the search term
    Bot::answerInlineQuery($ctx->update()->inlineQuery->id, []);
});
```

### Catch-all fallback

```php
Bot::onUpdate(function (Context $ctx) {
    // Runs for anything that didn't match above
});
```

---

## How matching works

Handlers are checked **in registration order**. The first match wins; the rest are skipped.

```php
Bot::onCommand('start', $handler1);  // checked first
Bot::onText($handler2);              // checked second
Bot::onMessage($handler3);           // checked third — only runs if no match above
```

Put specific handlers before general ones, and `onMessage` / `onUpdate` last.

---

## Adding a new command (full workflow)

Say you want a `/joke` command. Here's the complete process:

**1. Generate the file:**

```bash
vendor/bin/devflow make:command JokeCommand
```

This creates `app/Commands/JokeCommand.php`.

**2. Write the logic:**

```php
<?php

namespace App\Commands;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;

class JokeCommand implements HandlerInterface
{
    public function handle(Context $ctx): void
    {
        $ctx->reply("Why don't scientists trust atoms?\nBecause they make up everything.");
    }
}
```

**3. Register it in `bootstrap/app.php`:**

```php
Bot::onCommand('joke', \App\Commands\JokeCommand::class);
```

**4. Rebuild the autoload map:**

```bash
composer dump-autoload
```

> This step is easy to forget. If you get "Class App\Commands\JokeCommand not found", this is why.

Send `/joke` to your bot — done.

---

## Commands with arguments

If the user sends `/ban 123456`, you can read the argument:

```php
Bot::onCommand('ban', function (Context $ctx) {
    $args = $ctx->message()->commandArgs(); // ['123456']
    $userId = $args[0] ?? null;

    if (!$userId) {
        $ctx->reply('Usage: /ban <user_id>');
        return;
    }

    $ctx->reply("Banned user {$userId}");
});
```

---

## Next step

[04-context.md](04-context.md) — Everything you can do with the `$ctx` object inside a handler.
