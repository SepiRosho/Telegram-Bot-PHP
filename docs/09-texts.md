# 09 — Localized Text

There are two ways to localize bot messages:

1. **`Support\Lang` + `$ctx->t()`** (recommended) — key-based, per-locale array files. Scales to real apps with dozens or hundreds of strings. See [12 — i18n](12-i18n.md) for the full guide.
2. **`Support\BotText`** (deprecated, still supported) — one PHP class per message, described below. Fine for a handful of strings; becomes unwieldy past that.

---

## `BotText` (legacy)

Each text class returns a different version of a message depending on the user's language. Variables like `{name}` are substituted automatically.

### Generating a text class

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

### Adding translations

Fill in the `translations()` array with one entry per language code (using [IETF BCP 47](https://en.wikipedia.org/wiki/IETF_language_tag) codes — `'en'`, `'fa'`, `'de'`, `'ru'`, etc.):

```php
protected static function translations(): array
{
    return [
        'en' => 'Hello, {name}! You have {count} new messages.',
        'fa' => 'سلام، {name}! شما {count} پیام جدید دارید.',
    ];
}
```

If a user's language is not listed, `'en'` is used as the fallback.

### Using text classes in handlers

```php
use App\Texts\WelcomeText;

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply(
        WelcomeText::forContext($ctx, ['name' => $ctx->from()?->firstName ?? 'there'])
    );
});
```

`forContext()` reads the language from `$ctx->from()->languageCode` (Telegram's reported language), falling back to `$ctx->user()->language_code` (stored in DB), then to `'en'`. Note this ignores an explicit user-chosen locale — that's exactly what `Support\Lang` fixes (see [12 — i18n](12-i18n.md)).

### Tips

- **One class per message** — keep each text class focused on a single message or closely related set of messages.
- **Always define `'en'`** — it is the fallback language.
- Once you have more than a handful of strings, migrate to `Support\Lang` — see [12 — i18n](12-i18n.md).
