# 16 — Premium Emoji

Telegram Premium custom emoji need real markup to render — an `emoji-id` plus a fallback glyph for
clients that can't show it. Written by hand, that's a block like this at every place you want to use
one:

```php
$ctx->reply('Nice <tg-emoji emoji-id="5368324170671202286">🔥</tg-emoji> work!', ['parse_mode' => 'HTML']);
```

`Devflow\TelegramBot\Support\Emoji` lets you name that once and use `:fire:` everywhere instead.

---

## Registering emoji

A scaffolded project ships `app/Emojis.php`, loaded once in `bootstrap/app.php`:

```php
// app/Emojis.php
return [
    'fire' => ['id' => '5368324170671202286', 'fallback' => '🔥'],
    'star' => ['id' => '5368324170671202287', 'fallback' => '⭐'],
];
```

```php
// bootstrap/app.php
use Devflow\TelegramBot\Support\Emoji;

Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');
```

Or register one at a time, from anywhere:

```php
Emoji::register('fire', '5368324170671202286', '🔥');
```

To find an emoji-id: forward a message containing the premium emoji to [@userinfobot](https://t.me/userinfobot), or read it off `getCustomEmojiStickers` in the Bot API.

---

## Using it

Wrap any string with `Emoji::text()` — every `:name:` shortcode for a *registered* name expands, everything else is left exactly as it was:

```php
use Devflow\TelegramBot\Support\Emoji;

$ctx->reply(Emoji::text('Nice :fire: work!'), ['parse_mode' => 'HTML']);
```

For a single emoji on its own, `Emoji::get()` returns just the markup:

```php
$ctx->reply('Nice ' . Emoji::get('fire') . ' work!', ['parse_mode' => 'HTML']);
```

This composes with `$ctx->t()` — a translated string can contain `:fire:` shortcodes too:

```php
// lang/en.php: 'congrats' => 'Nice :fire: work, {name}!'
$ctx->reply(Emoji::text($ctx->t('congrats', ['name' => 'Ali'])), ['parse_mode' => 'HTML']);
```

An unregistered shortcode — or a colon-wrapped substring that was never meant as one, like `12:30:00` — is left untouched rather than warned about, since there's no reliable way to tell the two apart from the text alone.

---

## `parse_mode` matters

A premium emoji only renders under Telegram's `HTML` or `MarkdownV2` parse modes — pass whichever you're using as the second argument:

```php
Emoji::text('Nice :fire: work!', 'HTML');        // <tg-emoji emoji-id="...">🔥</tg-emoji>  (default)
Emoji::text('Nice :fire: work!', 'MarkdownV2');   // ![🔥](tg://emoji?id=...)
```

Legacy `Markdown` (not `MarkdownV2`) has no equivalent syntax — `Emoji::get()`/`Emoji::text()` throw an `InvalidArgumentException` if you pass it, rather than silently sending broken markup.

Whichever mode you use, you still need to set `'parse_mode'` on the `$ctx->reply()` (or other send) call yourself — `Emoji::text()` only transforms the string, it doesn't touch `$options`.

---

## Reference

| Method | Returns | Description |
|---|---|---|
| `Emoji::register(string $name, string $id, string $fallback)` | `void` | Register one emoji |
| `Emoji::registerMany(array $emojis)` | `void` | Bulk-register from `['name' => ['id' => ..., 'fallback' => ...]]` (or the positional `['id', 'fallback']` form) |
| `Emoji::has(string $name)` | `bool` | Whether a name is registered |
| `Emoji::get(string $name, string $parseMode = 'HTML')` | `string` | Markup for one emoji; an unregistered name returns the literal `:name:` |
| `Emoji::text(string $text, string $parseMode = 'HTML')` | `string` | Expands every registered `:name:` shortcode in a string |

---

## Next step

[README.md](README.md) — back to the guide index.
