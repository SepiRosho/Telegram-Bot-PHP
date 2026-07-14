# 07 — Wizard Flows (Multi-Step Conversations)

A **flow** (or wizard) is a multi-step conversation where the bot asks a series of questions and the user answers them one by one.

Example:
```
Bot: What is your name?
User: John
Bot: How old are you?
User: 25
Bot: Great! You are registered as John, 25.
```

> **Requires database.** Complete [06-database.md](06-database.md) before using flows.

---

## How it works

Each user has a `step` column in the database. When a user is in the middle of a flow, their `step` is set to a string like `'register.ask_age'`. When they're not in any flow, `step` is `null`.

Your `onText` handler reads `$ctx->step()` and routes to the right code:

```php
Bot::onText(function (Context $ctx) {
    match ($ctx->step()) {
        'register.ask_name' => // handle name input
        'register.ask_age'  => // handle age input
        default             => $ctx->reply('Use /register to begin.'),
    };
});
```

---

## The five methods

```php
$ctx->step()                  // Read the current step. null if not in a flow.
$ctx->setStep('name.step1')   // Move the user to the next step.
$ctx->clearFlow()             // End the flow — sets step to null, clears temp data.

$ctx->temp('key')             // Read a stored temporary value.
$ctx->setTemp('key', $value)  // Store a temporary value for later steps.
```

`temp` data is stored in the `temp_data` JSON column. It persists across messages until you call `clearFlow()`.

---

## Steps that accept media

The manual `onText` + `match($ctx->step())` pattern above only ever sees text messages — a photo, voice note, or any other non-text message sent mid-flow silently falls through untouched. `Bot::onStep()` is the router-native alternative, and it can match specific message types:

```php
// Only matches text (the default — same behavior as before).
Bot::onStep('register.ask_name', function (Context $ctx) {
    // ...
});

// Matches text OR a photo while the user is in this step.
Bot::onStep('anon.compose', function (Context $ctx) {
    if ($ctx->message()->photo !== null) {
        // forward/copy the photo into the anonymous message flow
    } else {
        // handle the text case
    }
    $ctx->clearFlow();
}, types: ['text', 'photo']);

// Matches any message type at all.
Bot::onStep('support.attach_file', $handler, types: ['*']);
```

Supported `types` values: `text`, `photo`, `document`, `audio`, `video`, `voice`, `video_note`, `sticker`, `animation`, `contact`, `location`, `venue`, `dice`, or `*` for any. `onStep()` routes are still checked in registration order alongside your other routes — a `types`-restricted step route simply won't match a message type it wasn't given.

---

## A complete registration flow

### Using a Flow class (recommended for longer flows)

**Generate the file:**

```bash
vendor/bin/devflow make:flow RegisterFlow
```

**Write the logic** in `app/Flows/RegisterFlow.php`:

```php
<?php

namespace App\Flows;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;

class RegisterFlow implements HandlerInterface
{
    public function handle(Context $ctx): void
    {
        match ($ctx->step()) {
            'register.ask_name' => $this->askName($ctx),
            'register.ask_age'  => $this->askAge($ctx),
            'register.confirm'  => $this->confirm($ctx),
            default             => $ctx->reply('Use /register to start.'),
        };
    }

    private function askName(Context $ctx): void
    {
        $name = trim($ctx->text());

        if (strlen($name) < 2) {
            $ctx->reply('Please enter a valid name (at least 2 characters).');
            return; // Stay on the same step
        }

        $ctx->setTemp('name', $name);
        $ctx->setStep('register.ask_age');
        $ctx->reply('Great! How old are you?');
    }

    private function askAge(Context $ctx): void
    {
        $age = (int) $ctx->text();

        if ($age < 1 || $age > 120) {
            $ctx->reply('Please enter a valid age.');
            return;
        }

        $ctx->setTemp('age', $age);
        $ctx->setStep('register.confirm');

        $name = $ctx->temp('name');
        $ctx->reply(
            "Almost done! Please confirm:\n" .
            "Name: {$name}\nAge: {$age}\n\n" .
            "Send 'yes' to confirm or 'no' to cancel."
        );
    }

    private function confirm(Context $ctx): void
    {
        $answer = strtolower(trim($ctx->text()));

        if ($answer === 'yes') {
            $name = $ctx->temp('name');
            $age  = $ctx->temp('age');

            // Save to database or do whatever you need
            // $ctx->user()->update(['age' => $age]); // if you add an age column

            $ctx->clearFlow();
            $ctx->reply("You are registered! Name: {$name}, Age: {$age}.");

        } elseif ($answer === 'no') {
            $ctx->clearFlow();
            $ctx->reply('Registration cancelled.');

        } else {
            $ctx->reply("Please answer 'yes' or 'no'.");
        }
    }
}
```

**Register the flow trigger command** in `bootstrap/app.php`:

```php
Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('register.ask_name');
    $ctx->reply('Let\'s get you registered. What is your name?');
});
```

**Route text messages to the flow** in `bootstrap/app.php`:

```php
Bot::onText(function (Context $ctx) {
    $step = $ctx->step();

    if (str_starts_with((string) $step, 'register.')) {
        (new \App\Flows\RegisterFlow())->handle($ctx);
        return;
    }

    // Default: echo back
    $ctx->reply($ctx->text());
});
```

---

## Handling multiple flows

When your bot has more than one flow, route by step prefix:

```php
Bot::onText(function (Context $ctx) {
    $step = (string) $ctx->step();

    match (true) {
        str_starts_with($step, 'register.') => (new \App\Flows\RegisterFlow())->handle($ctx),
        str_starts_with($step, 'feedback.') => (new \App\Flows\FeedbackFlow())->handle($ctx),
        $step === ''                         => $ctx->reply('Use /start to begin.'),
        default                              => $ctx->reply('Use /start to begin.'),
    };
});
```

---

## The /cancel command

Always add a cancel command so users can escape:

```php
Bot::onCommand('cancel', function (Context $ctx) {
    if ($ctx->step() === null) {
        $ctx->reply('Nothing to cancel.');
        return;
    }

    $ctx->clearFlow();
    $ctx->reply('Cancelled. Use /start to begin again.');
});
```

---

## Tips

- **Name your steps like `flowname.stepname`** — the prefix lets you route multiple flows cleanly.
- **Always validate input** and re-ask on the same step if invalid (don't call `setStep`).
- **Always have a `default` case** in your `match` to handle unexpected states.
- **Call `clearFlow()` on success AND on cancel** — don't leave stale steps in the DB.
- **`temp()` data is wiped by `clearFlow()`** — don't rely on it after the flow ends.

---

## Next step

[08-local-dev.md](08-local-dev.md) — Test your bot on localhost without a public server.
