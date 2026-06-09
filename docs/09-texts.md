# 09 — Localized Text Classes

The text system lets you write bot messages as classes instead of inline strings. Each class returns a different version of the text depending on the user's language. Variables like `{name}` are substituted automatically.

---

## The problem it solves

Without this system, localization looks like this scattered across your handlers:

```php
$text = $lang === 'fa' ? "سلام {$name}!" : "Hello {$name}!";
```

With the text system, each message is its own class, language switching is automatic, and all translations live in one place.

---

## Generating a text class

```bash
vendor/bin/devflow make:text WelcomeText
```

Creates `app/Texts/WelcomeText.php`:

```php
<?php

namespace App\Texts;

use Devflow\TelegramBot\Support\BotText;

class WelcomeText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => 'Your text in English. Use {variable} for dynamic values.',
            // 'fa' => 'متن فارسی',
        ];
    }
}
```

---

## Adding translations

Fill in the `translations()` array with one entry per language code (using [IETF BCP 47](https://en.wikipedia.org/wiki/IETF_language_tag) codes — `'en'`, `'fa'`, `'de'`, `'ru'`, etc.):

```php
protected static function translations(): array
{
    return [
        'en' => 'Hello, {name}! You have {count} new messages.',
        'fa' => 'سلام، {name}! شما {count} پیام جدید دارید.',
        'de' => 'Hallo, {name}! Sie haben {count} neue Nachrichten.',
        'ru' => 'Привет, {name}! У вас {count} новых сообщений.',
    ];
}
```

If a user's language is not listed, `'en'` is used as the fallback.

---

## Using text classes in handlers

### Automatic language detection from context (recommended)

```php
use App\Texts\WelcomeText;

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply(
        WelcomeText::forContext($ctx, ['name' => $ctx->from()?->firstName ?? 'there'])
    );
});
```

`forContext()` reads the language from `$ctx->from()->languageCode` (Telegram's reported language), falling back to `$ctx->user()->language_code` (stored in DB), then to `'en'`.

### Manual language

```php
$ctx->reply(WelcomeText::get(['name' => 'John'], 'fa'));
```

### No variables

```php
$ctx->reply(HelpText::forContext($ctx));
```

---

## Complete example

```php
// app/Texts/HelpText.php
class HelpText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => "/start — Start the bot\n/help — Show this message",
            'fa' => "/start — شروع ربات\n/help — نمایش این پیام",
        ];
    }
}

// app/Texts/RegistrationCompleteText.php
class RegistrationCompleteText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => "Registration complete!\nName: {name}\nAge: {age}",
            'fa' => "ثبت‌نام کامل شد!\nنام: {name}\nسن: {age}",
        ];
    }
}

// Usage in a flow handler:
$ctx->reply(
    RegistrationCompleteText::forContext($ctx, [
        'name' => $ctx->temp('name'),
        'age'  => $ctx->temp('age'),
    ])
);
```

---

## Tips

- **One class per message** — keep each text class focused on a single message or closely related set of messages.
- **Always define `'en'`** — it is the fallback language. If you omit it, users with unlisted languages get an empty string.
- **Variable names are case-sensitive** — `{Name}` and `{name}` are different placeholders.
- **Telegram language codes** are the ones Telegram sends in user objects: `en`, `fa`, `de`, `ru`, `ar`, `zh`, `es`, `fr`, etc. They follow BCP 47 but Telegram only sends the primary subtag (no region).
