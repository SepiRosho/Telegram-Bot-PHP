# Keyboards

`Devflow\TelegramBot\Support\Keyboard` builds reply-markup arrays for Telegram messages.

> **Important:** Always pass the result directly as `reply_markup` — do **not** wrap it in `json_encode()`. The library uses Guzzle's JSON body, so it encodes the entire request once. Passing a pre-encoded string causes double-encoding and Telegram silently ignores the keyboard.

---

## Reply Keyboard

Shows persistent buttons at the bottom of the screen.

```php
use Devflow\TelegramBot\Support\Keyboard;

$ctx->reply('Choose an option:', [
    'reply_markup' => Keyboard::reply([
        ['📋 My Account', '🔔 Notifications'],
        ['ℹ️ About',       '❓ Help'],
    ]),
]);
```

### Options

```php
Keyboard::reply(
    rows: [['Yes', 'No']],
    resize: true,    // shrink buttons to fit content (default: true)
    oneTime: true    // hide keyboard after one tap
)
```

Row items can be plain strings or Telegram button objects (e.g., for `request_contact`):

```php
Keyboard::reply([
    [['text' => '📱 Share contact', 'request_contact' => true]],
]);
```

---

## Inline Keyboard

Attaches buttons directly to a message. Tapping a button sends a callback query.

```php
$ctx->reply('Bot panel:', [
    'reply_markup' => Keyboard::inline([
        [
            Keyboard::button('📊 Stats',    'admin_stats'),
            Keyboard::button('👥 Users',    'admin_users'),
        ],
        [Keyboard::button('⚙️ Settings',   'admin_settings')],
        [Keyboard::url('📖 Docs', 'https://github.com/devflow/telegram-bot')],
    ]),
]);
```

### `Keyboard::button(string $text, string $callbackData): array`

Creates a button that sends a callback query with the given `$callbackData` when tapped.

### `Keyboard::url(string $text, string $url): array`

Creates a button that opens a URL in the browser.

---

## Removing the Reply Keyboard

```php
$ctx->reply('Keyboard removed.', [
    'reply_markup' => Keyboard::remove(),
]);
```

---

## Handling Callback Queries

Register handlers with `Bot::onCallbackQuery()`. Use `*` for wildcards.

```php
// Exact match
Bot::onCallbackQuery('admin_stats', function (Context $ctx) {
    $ctx->answerCallback('Loading…');   // dismisses the spinner on the button
    $ctx->reply('📊 Stats here.');
});

// Wildcard — matches admin_stats, admin_users, admin_settings, …
Bot::onCallbackQuery('admin_*', function (Context $ctx) {
    $data = $ctx->callbackData();       // the full callback_data string
    $ctx->answerCallback("You picked: {$data}", showAlert: true);
});
```

`$ctx->answerCallback(string $text = '', bool $showAlert = false)` — always call this to dismiss the loading indicator on the button.

---

## Full Example

```php
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\Keyboard;

Bot::init($token);

Bot::onCommand('menu', function (Context $ctx) {
    $ctx->reply('Main menu:', [
        'reply_markup' => Keyboard::inline([
            [Keyboard::button('📊 Stats', 'menu_stats')],
            [Keyboard::url('🌐 Website', 'https://example.com')],
        ]),
    ]);
});

Bot::onCallbackQuery('menu_stats', function (Context $ctx) {
    $ctx->answerCallback();
    $ctx->reply('Everything is running fine.');
});

Bot::run();
```

---

## Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `Keyboard::inline(array $rows)` | `array` | Inline keyboard markup |
| `Keyboard::reply(array $rows, bool $resize, bool $oneTime)` | `array` | Reply keyboard markup |
| `Keyboard::button(string $text, string $callbackData)` | `array` | Inline callback button |
| `Keyboard::url(string $text, string $url)` | `array` | Inline URL button |
| `Keyboard::remove()` | `array` | Remove reply keyboard |
