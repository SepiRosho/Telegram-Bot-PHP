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

## Pagination

`Keyboard::paginate()` builds a page of item rows plus a prev/page-number/next navigation row — for things like a banned-users list or a channel list where you don't want to dump everything into one message.

```php
$page = (int) ($ctx->callbackData() ? str_replace('banned_page_', '', $ctx->callbackData()) : 1);

$ctx->reply('Banned users:', [
    'reply_markup' => Keyboard::paginate(
        items: $bannedUsers,                 // any array/list
        page: $page,
        renderRow: fn($user) => [Keyboard::button("🚫 {$user->first_name}", "unban_{$user->id}")],
        cbPrefix: 'banned_',                  // nav buttons send "banned_page_{N}"
        perPage: 10,
    ),
]);

Bot::onCallbackQuery('banned_page_*', function (Context $ctx) {
    // re-render the same list at the requested page
});
```

`renderRow` receives one item and returns one keyboard row (usually a single button, but can be more). The nav row (`⬅️ 2/5 ➡️`) only appears when there's more than one page; an out-of-range `page` is clamped into range automatically.

The middle page-indicator button (`2/5`) is not decorative — it carries real `callback_data` (`"{$cbPrefix}noop"`), so tapping it fires a callback query like any other button. If you don't register a handler for it, the router now auto-answers unrouted callback queries (see below), which clears the tap spinner on the user's client instead of leaving it spinning until Telegram times it out. Registering your own handler is still the explicit option if you want to, say, show a toast:

```php
Bot::onCallbackQuery('banned_noop', function (Context $ctx) {
    $ctx->answerCallback(); // no-op — just dismiss the spinner
});
```

---

## Reusable keyboards (providers)

A keyboard that shows up in several handlers — a main menu, an admin panel — is easy to let drift:
reorder a button in one place and you have to remember every other handler that renders the same
rows. Put it in one class instead:

```bash
vendor/bin/devflow make:keyboard MainMenuKeyboard   # -> app/Keyboards/MainMenuKeyboard.php
```

```php
namespace App\Keyboards;

use Devflow\TelegramBot\Keyboards\KeyboardInterface;
use Devflow\TelegramBot\Support\Keyboard;

class MainMenuKeyboard implements KeyboardInterface
{
    public static function build(array $vars = []): array
    {
        $rows = [
            [Keyboard::button('📊 Stats', 'menu_stats')],
        ];

        // $vars carries whatever the call site knows — the class itself
        // never touches Context, so it stays trivial to unit test.
        if ($vars['isAdmin'] ?? false) {
            $rows[] = [Keyboard::button('⚙️ Admin', 'menu_admin')];
        }

        return Keyboard::inline($rows);
    }
}
```

Use it anywhere, passing whatever varies the output:

```php
use App\Keyboards\MainMenuKeyboard;

Bot::onCommand('menu', function (Context $ctx) {
    $ctx->reply('Main menu:', [
        'reply_markup' => MainMenuKeyboard::build(['isAdmin' => $ctx->user()?->isAdmin()]),
    ]);
});
```

`build()` returns the same `reply_markup` array `Keyboard::inline()`/`reply()` always returns — pass
it straight through, same as any other keyboard.

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

### Unrouted callback queries are answered automatically

Telegram spins the tap indicator on the user's client until the bot answers a callback query. If no route matches (a stale button after a redeploy, the `noop` pagination button with no registered handler, etc.), the router now calls `answerCallbackQuery` for you so the spinner clears instead of hanging until Telegram gives up. Opt out with:

```php
Bot::init(env('BOT_TOKEN'), ['auto_answer_callbacks' => false]);
```

This is a safety net, not a substitute for calling `$ctx->answerCallback()` yourself in a matched handler — do that when you want to control the toast text or show an alert.

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
| `Keyboard::paginate(array $items, int $page, callable $renderRow, string $cbPrefix, int $perPage = 10)` | `array` | Paginated inline keyboard with nav row |
| `KeyboardInterface::build(array $vars = [])` | `array` | Contract for a reusable keyboard class — scaffold with `make:keyboard` |
