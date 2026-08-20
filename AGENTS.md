# AGENTS.md — devflow/telegram-bot

Reference for coding agents building Telegram bots with this library.
**Read this file only. Do not read `src/` or `docs/` unless something here is missing** — everything
needed to write a correct bot is below.

- PHP 8.1+ · Composer package `devflow/telegram-bot` · namespace `Devflow\TelegramBot`
- `docs/` is prose for humans; this file is the machine reference.
- `.ai/api.json` (run `vendor/bin/devflow ai:manifest`) has every method signature by reflection.

---

## 1. Rules that cause silent failures

These are the mistakes that produce no exception and no log line. Read them before writing code.

1. **Never `json_encode()` anything you pass to the library.** Every `$options` array — especially
   `reply_markup` — must be a plain PHP array. The HTTP layer encodes it. Pre-encoding yields a
   double-encoded value that Telegram ignores without error.
2. **`Keyboard::*` already returns arrays.** Pass the result straight to `reply_markup`.
3. **`onStep()` needs `'database' => true`.** Without it `$ctx->step()` is always `null` and no step
   route ever matches.
4. **Set `'allowed_chat_types' => ['private']`** unless the bot is meant for groups. Without it,
   anyone can add the bot to a group and `/start` there — which writes a `telegram_users` row whose
   `chat_id` is the *group's* id, inflating user counts and sending every future broadcast into
   that group.
5. **Step routes run before all other route types** (config `step_routes_first`, default `true`), so
   a broad `onText()` cannot swallow a mid-wizard message. Commands still escape an active flow.
6. **The first matching route wins and dispatch stops.** Register specific routes before broad ones.
7. **`$ctx->user()` is `null` when `'database' => false`.** Always `?->` it.
8. **New classes need `composer dump-autoload`** in a scaffolded project.

---

## 2. Minimal working bot

```php
<?php
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

require __DIR__ . '/vendor/autoload.php';

Bot::init($token, ['allowed_chat_types' => ['private']]);

Bot::onCommand('start', fn(Context $ctx) => $ctx->reply('Hello!'));
Bot::onText(fn(Context $ctx) => $ctx->reply('You said: ' . $ctx->text()));

Bot::run();   // reads php://input, dispatches one webhook update
```

Handlers are normally grouped in classes with a static `register()`:

```php
class UserHandlers {
    public static function register(): void {
        Bot::onCommand('start', function (Context $ctx) { /* ... */ });
    }
}
Bot::loadHandlers([UserHandlers::class, AdminHandlers::class]);
```

`devflow make:handler <ClassName>` scaffolds a new group under `app/Handlers/` — add it to
`Bot::loadHandlers()` afterward, it isn't wired in automatically.

---

## 3. Project layout (`vendor/bin/devflow new my-bot`)

```
bootstrap/app.php        Bot::init(), Capsule DB, middleware, loadHandlers()  ← wire everything here
bootstrap/helpers.php    env(), saveLog(), botLog()
public/webhook.php       Entry point; always returns HTTP 200
app/Commands/            One class per command, implements HandlerInterface
app/Callbacks/           Callback-query handlers
app/Handlers/            Handler groups with static register()
app/Middleware/          MiddlewareInterface implementations
app/Flows/               Multi-step wizards
app/Models/User.php      Extends TelegramUser; wired via 'user_model'
app/Services/            Your own business logic
app/Keyboards/           Reusable KeyboardInterface classes — see §8
database/migrations/     devflow make:migration writes here
lang/{locale}.php        i18n key files
logs/                    Daily log files
.ai/api.json             Generated API index (devflow ai:manifest)
```

---

## 4. Config — `Bot::init($token, [...])`

| Key | Type | Default | Notes |
|-----|------|---------|-------|
| `database` | bool | `false` | Enables `$ctx->user()`, flow state, auto-registration. Required by `onStep()`. |
| `allowed_chat_types` | ?array | `null` | e.g. `['private']`. `null` = no filtering. See §7. |
| `webhook_secret` | ?string | `null` | Validates `X-Telegram-Bot-Api-Secret-Token`. Must match `setWebhook`'s `secret_token`. |
| `proxy` | ?string | `null` | `http://…` or `socks5://host:1080`. |
| `lang_path` | ?string | `null` | Directory of `{locale}.php` files. |
| `default_locale` | string | `'en'` | Fallback locale. |
| `language_code` | string | `'auto'` | `'auto'` records each user's own Telegram language. Any other value (e.g. `'fa'`) forces every user's `language_code` *and* `language` columns to it, overriding what Telegram reports. See §11. |
| `lang_auto_fallback` | bool | `false` | When a `$ctx->t()` key is missing from both the resolved locale and `default_locale`, search every other lang file before falling back to the raw key. Logs a `WARNING` (never throws) when it actually fires. See §11. |
| `user_model` | class-string | `TelegramUser::class` | Your Eloquent subclass. |
| `user_defaults` | ?callable | `null` | `callable(Update): array`, merged in on first insert. |
| `debug` | bool | `false` | Logs every route match/no-match, including chat-filter drops. |
| `step_routes_first` | bool | `true` | Evaluate step routes first, regardless of registration order. |
| `auto_answer_callbacks` | bool | `true` | Auto-answers unrouted callback queries so the tap spinner clears. |
| `max_retries` | int | `2` | Retries after a 429, honouring `retry_after`. Uploads are never retried. |
| `max_retry_after` | int | `60` | Longest `retry_after` (seconds) worth waiting on. |
| `timeout` | int | `30` | HTTP timeout, seconds. |

Env vars in a scaffolded `.env`: `BOT_TOKEN`, `ADMIN_CHAT_ID`, `LANGUAGE_CODE`, `LANG_AUTO_FALLBACK`,
`WEBHOOK_SECRET`, `PROXY_URL`, `BROADCAST_RATE`, `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`. `lang_path` and `default_locale` are **not** env vars — they're
hardcoded in `bootstrap/app.php`. `ADMIN_CHAT_ID` is not read directly by the library either — the
scaffolded `bootstrap/app.php` wires it into `user_defaults` to promote that Telegram user id to
`'superadmin'` on first contact.

---

## 5. Routes

Every `on*()` takes an optional trailing `array $middleware = []`.

| Method | Fires on |
|--------|----------|
| `onCommand('start', $h)` | `/start`. `'*'` matches any command. |
| `onUnknownCommand($h)` | A command no `onCommand()` registered — order-independent. |
| `onText($h)` | Any non-command text. |
| `onText('buy_*', $h)` | Text matching a glob. |
| `onText('/^buy_\d+$/', $h)` | Text matching a PCRE regex. |
| `onMessage($h)` | Any message. |
| `onCallbackQuery('prefix_*', $h)` | Button press; glob or regex on `callback_data`. |
| `onCallbackQuery($h)` | Any button press. |
| `onStep('name.step', $h, types: ['text'])` | Flow step — **needs `database`**. |
| `onPhoto($h)` / `onDocument($h)` | Photo / document message. |
| `onEditedMessage($h)` / `onChannelPost($h)` | Edited message / channel post. |
| `onInlineQuery($h)` / `onChosenInlineResult($h)` | Inline mode. |
| `onPoll($h)` / `onPollAnswer($h)` | Poll update / vote. |
| `onMyChatMember($h)` | **Bot** added to or removed from a chat. |
| `onChatMember($h)` / `onChatJoinRequest($h)` | Member status / join request. |
| `onShippingQuery($h)` / `onPreCheckoutQuery($h)` | Payments. |
| `onUpdate($h)` | Catch-all. |

`onStep` `types:` accepts `text`, `photo`, `document`, `audio`, `video`, `voice`, `video_note`,
`sticker`, `animation`, `contact`, `location`, `venue`, `dice`, or `'*'`.

Also on `BotInstance` (work via `Bot::__callStatic`, no IDE autocomplete):
`onBusinessConnection`, `onBusinessMessage`, `onEditedBusinessMessage`, `onDeletedBusinessMessages`,
`onGuestMessage`, `onMessageReaction`, `onMessageReactionCount`, `onPurchasedPaidMedia`,
`onChatBoost`, `onRemovedChatBoost`, `onManagedBot`.

Handlers are `fn(Context $ctx)`, or a class name implementing `HandlerInterface::handle(Context)`.

Run `vendor/bin/devflow routes` to print a project's routes in evaluation order.

---

## 6. `Context` — the `$ctx` in every handler

```php
// Update accessors
$ctx->update(); $ctx->message(); $ctx->callbackQuery(); $ctx->api();
$ctx->chatId(); $ctx->userId(); $ctx->from(); $ctx->text(); $ctx->callbackData();

// Chat type
$ctx->chat();        // ?Chat — null for inline queries, poll answers, etc.
$ctx->chatType();    // 'private' | 'group' | 'supergroup' | 'channel' | null
$ctx->isPrivate(); $ctx->isGroup(); $ctx->isChannel();

// Sending
$ctx->reply($text, $options);
$ctx->replyWithPhoto($fileIdOrUrlOrInputFile, $options);
$ctx->replyWithDocument(...); $ctx->replyWithVideo(...); $ctx->replyWithAudio(...);
$ctx->replyWithVoice(...); $ctx->replyWithSticker(...);
$ctx->replyWithLocation($lat, $lng, $options);
$ctx->typing();                     // sendChatAction('typing')
$ctx->sendChatAction($action);

// Editing the message that triggered a callback
$ctx->editReply($text, $options);
$ctx->editReplySafe($text, $options);   // swallows "not modified"; falls back to caption edit
$ctx->removeKeyboard();
$ctx->deleteCurrentMessage();
$ctx->answerCallback($text = '', $showAlert = false);

// DB user (null unless 'database' => true)
$ctx->user();                        // TelegramUser (or your 'user_model')

// Flow state
$ctx->step(); $ctx->setStep('checkout.address');
$ctx->temp($key); $ctx->setTemp($key, $value);
$ctx->clearFlow();                   // clears step + temp_data — call when a wizard finishes

// i18n
$ctx->locale(); $ctx->setLocale('fa');
$ctx->t('welcome', ['name' => 'Ali']);
```

---

## 7. Chat types

```php
Bot::init($token, ['allowed_chat_types' => ['private']]);
```

Routes only fire for the listed chat types. Two things always pass:

- **Updates with no chat**: inline queries, chosen inline results, polls, poll answers, shipping and
  pre-checkout queries, business connections, paid-media purchases. Nothing to filter against.
- **Group/channel-only route types**, which would be meaningless filtered to `private`:
  `channel_post`, `edited_channel_post`, `my_chat_member`, `chat_member`, `chat_join_request`,
  `chat_boost`, `removed_chat_boost`, `message_reaction_count`.

`my_chat_member` being exempt is deliberate — it's how a private-only bot learns it was added to a
group so it can leave:

```php
Bot::onMyChatMember(function (Context $ctx) {
    $chat = $ctx->chat();
    if ($chat && !$chat->isPrivate() && $ctx->update()->myChatMember?->userJoined()) {
        Bot::leaveChat($chat->id);
    }
});
```

Widen specific routes past the global default:

```php
Bot::chatTypes(['group', 'supergroup'], function () {
    Bot::onCommand('stats', $handler);
});
Bot::chatTypes(['*'], fn() => Bot::onCommand('ping', $handler));   // any chat
```

---

## 8. Keyboards — always arrays, never `json_encode()`

```php
use Devflow\TelegramBot\Support\Keyboard;

$ctx->reply('Choose:', ['reply_markup' => Keyboard::inline([
    [Keyboard::button('📊 Stats', 'admin_stats'), Keyboard::button('👥 Users', 'admin_users')],
    [Keyboard::url('📖 Docs', 'https://example.com')],
])]);

$ctx->reply('Menu:', ['reply_markup' => Keyboard::reply([
    ['📋 My Account', '🔔 Notifications'],
    ['❓ Help'],
])]);

$ctx->reply('Done.', ['reply_markup' => Keyboard::remove()]);

// Paginated list; out-of-range pages are clamped.
// The middle page-indicator carries callback_data "{prefix}noop" — register a handler
// for it or rely on auto_answer_callbacks (on by default).
Keyboard::paginate($items, $page, fn($i) => [Keyboard::button($i->name, "sel_{$i->id}")], 'list_');
```

Reply-keyboard buttons arrive as **plain text**, in whatever locale rendered them. Match the label
back to its key rather than comparing one language's string:

```php
$key = \Devflow\TelegramBot\Support\Lang::findKey((string) $ctx->text(), ['en', 'fa']);
match ($key) {
    'menu.help'    => $ctx->reply($ctx->t('help')),
    'menu.account' => $ctx->reply($ctx->t('user_panel')),
    default        => $ctx->reply($ctx->t('unknown')),
};
```

### Reusable keyboards

A keyboard that appears in several handlers (a main menu, an admin panel) belongs in its own
`KeyboardInterface` class instead of being rebuilt at every call site — `devflow make:keyboard
<Name>` scaffolds `app/Keyboards/<Name>.php`. `build()` is static and takes a `$vars` array so the
caller can vary it (e.g. an admin-only button) without the class knowing about `Context`:

```php
namespace App\Keyboards;

use Devflow\TelegramBot\Keyboards\KeyboardInterface;
use Devflow\TelegramBot\Support\Keyboard;

class MainMenuKeyboard implements KeyboardInterface
{
    public static function build(array $vars = []): array
    {
        $rows = [[Keyboard::button('📊 Stats', 'menu_stats')]];

        if ($vars['isAdmin'] ?? false) {
            $rows[] = [Keyboard::button('⚙️ Admin', 'menu_admin')];
        }

        return Keyboard::inline($rows);
    }
}

// anywhere in a handler:
$ctx->reply('Main menu:', [
    'reply_markup' => \App\Keyboards\MainMenuKeyboard::build(['isAdmin' => $ctx->user()?->isAdmin()]),
]);
```

---

## 9. Flows (wizards)

```php
Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('register.name');
    $ctx->reply('What is your name?');
});

Bot::onStep('register.name', function (Context $ctx) {
    $ctx->setTemp('name', $ctx->text());
    $ctx->setStep('register.photo');
    $ctx->reply('Now send a photo.');
});

Bot::onStep('register.photo', function (Context $ctx) {
    $fileId = $ctx->message()->photo[0]['file_id'] ?? null;
    $ctx->reply('Thanks, ' . $ctx->temp('name'));
    $ctx->clearFlow();               // always clear when done
}, types: ['photo']);
```

Needs `'database' => true`. `onStep('*')` never matches a user with no active step.

`vendor/bin/devflow make:flow <Name>` generates a class with a `match ($ctx->step())` dispatcher
instead — a different pattern from the closures above. It needs manual wiring: register a command
that calls `$ctx->setStep(...)`, then route matching text to it from `onText()` by step prefix:

```php
Bot::onText(function (Context $ctx) {
    if (str_starts_with((string) $ctx->step(), 'register.')) {
        (new \App\Flows\RegisterFlow())->handle($ctx);
        return;
    }
    $ctx->reply($ctx->text());
});
```

Full walkthrough: [docs/07-flows.md](docs/07-flows.md#a-complete-registration-flow).

---

## 10. Sending files

```php
use Devflow\TelegramBot\Api\InputFile;

$ctx->replyWithPhoto('AgACAgQAAxk...');                    // file_id
$ctx->replyWithPhoto('https://example.com/pic.jpg');       // URL
$ctx->replyWithDocument(InputFile::path('/tmp/report.pdf'));            // local file
$ctx->replyWithPhoto(InputFile::contents($pngBytes, 'chart.png'));      // generated in memory
```

A plain string is a `file_id` or URL. An `InputFile` switches the request to a multipart upload —
still pass `$options` as arrays; the library JSON-encodes them for multipart itself.

---

## 11. i18n

`lang/en.php`:
```php
return [
    'welcome' => 'Hello, {name}!',
    'menu'    => ['account' => 'My Account'],
];
```

`$ctx->t('welcome', ['name' => 'Ali'])`, `$ctx->t('menu.account')` (dot notation).
Locale resolves: stored `language` column → Telegram client language → `default_locale`.
BCP-47 subtags fall back (`fa-IR` → `fa`, `zh-Hans-CN` → `zh-hans` → `zh`).

A key missing from both the resolved locale and `default_locale` renders as the raw key — unless
`lang_auto_fallback` is `true`, in which case every other shipped lang file is searched first (and a
`WARNING` logged when that fires). `language_code` config (`'auto'` by default) can also force every
user onto one fixed language, ignoring what Telegram reports — see the config table in §4.

---

## 12. Middleware

```php
class AuthMiddleware implements MiddlewareInterface {
    public function handle(Context $ctx, callable $next): void {
        if ($ctx->user()?->is_banned) { $ctx->reply('You are banned.'); return; }
        $ctx->user()?->touchActivity();
        $next($ctx);            // omit $next() to stop the chain
    }
}

Bot::use(AuthMiddleware::class);                       // global — class name
Bot::use(new RateLimitMiddleware(limit: 10, seconds: 60));  // global — instance
Bot::onCommand('admin', $handler, [AdminOnly::class]);      // per-route
```

Global middleware wraps per-route middleware, which wraps the handler.
`Bot::use()` accepts `callable|string|MiddlewareInterface`.

Shipped: `RateLimitMiddleware` (DB-backed rolling window; `message` is `string|Closure(Context): string`)
and `ForceJoinMiddleware` (channel-join gate, membership cached in `bot_settings`).

---

## 13. Database

`'database' => true` + Capsule (Eloquent) configured in `bootstrap/app.php`.

**`telegram_users`** — `id`, `telegram_id` (unique), `chat_id`, `first_name`, `last_name`,
`username`, `language_code`, `language`, `role` (`user|admin|superadmin`), `permissions` (json),
`is_banned`, `ban_reason`, `banned_at`, `is_active`, `step`, `temp_data` (json), `rate_hits` (json),
`current_panel`, `referral_code`, `invited_by`, `joined_at`, `last_activity_at`.

Methods: `isAdmin()`, `isSuperAdmin()`, `hasPermission($key)`, `ban($reason)`, `unban()`,
`touchActivity()`. Only `id` is guarded.

**`bot_settings`** — `BotSetting::get($k)` / `set($k, $v)` / `forget($k)`. Also a general cache.

**`telegram_broadcasts`** — queue processed by `devflow broadcast:run`
(`pending → running → completed|failed`). `type` is `text|photo|document|video|audio|voice|animation|copy`.

Extend the user model:
```php
class User extends TelegramUser {}          // app/Models/User.php
// bootstrap/app.php: 'user_model' => \App\Models\User::class
```

---

## 14. CLI

```bash
vendor/bin/devflow new <name>              # scaffold a project
vendor/bin/devflow doctor                  # diagnose env, token, DB, routes, webhook
vendor/bin/devflow routes                  # list routes in evaluation order
vendor/bin/devflow upgrade                 # check/apply scaffold changes after a library update
vendor/bin/devflow upgrade --dry-run       # …preview only, no writes
vendor/bin/devflow poll                    # long-polling (needs webhook:delete first)
vendor/bin/devflow poll --drop-pending     # …skipping whatever queued while the bot was down
vendor/bin/devflow broadcast:run           # process pending broadcasts
vendor/bin/devflow migrate                 # run pending migrations
vendor/bin/devflow migrate:status          # applied / Pending / Untracked
vendor/bin/devflow migrate:rollback        # undo the most recent migration batch
vendor/bin/devflow migrate:rollback --step=2  # undo the 2 most recent batches
vendor/bin/devflow migrate:rollback --all  # undo every applied migration
vendor/bin/devflow webhook:set <https-url> # register webhook (sends WEBHOOK_SECRET if set)
vendor/bin/devflow webhook:delete
vendor/bin/devflow webhook:info
vendor/bin/devflow make:command <Name>     # → app/Commands/
vendor/bin/devflow make:callback <Name>    # → app/Callbacks/
vendor/bin/devflow make:middleware <Name>  # → app/Middleware/
vendor/bin/devflow make:flow <Name>        # → app/Flows/
vendor/bin/devflow make:text <Name>        # → app/Texts/
vendor/bin/devflow make:service <Name>     # → app/Services/
vendor/bin/devflow make:keyboard <Name>    # → app/Keyboards/, implements KeyboardInterface
vendor/bin/devflow make:model <Name>       # → app/Models/, plain Eloquent model
vendor/bin/devflow make:handler <Name>     # → app/Handlers/, handler group with static register()
vendor/bin/devflow make:migration <name>   # → database/migrations/ (snake_case)
vendor/bin/devflow make:migration create_orders_table --model  # …plus app/Models/Order.php
vendor/bin/devflow ai:manifest             # regenerate .ai/api.json
```

**When a bot misbehaves, run `devflow doctor` first** — it checks PHP extensions, `.env`, token
validity, DB connectivity, base tables, route count and webhook status in one pass.

**After bumping `devflow/telegram-bot` in an existing project, run `devflow upgrade`** — it creates
any scaffold files/directories a newer library version expects that this project predates (e.g.
`app/Keyboards/`, `app/Emojis.php`), and prints the exact snippet to paste into `bootstrap/app.php`
for anything it won't edit automatically (that file is almost always hand-customized by then).
Re-running it is always safe; already-applied checks just report OK.

---

## 15. Testing without a token, webhook or database

```php
$fake = Bot::fake(['database' => true]);
App\Handlers\UserHandlers::register();      // production code, unmodified

$fake->dispatch(UpdateFactory::command('start', userId: 1));
$fake->assertSent('sendMessage', fn($p) => str_contains($p['text'], 'Hello'));
$fake->assertNotSent('sendPhoto');
```

`Bot::fake()` swaps the static instance for one wired to `FakeHttpClient` (no network) and
`FakeUserRepository` (no DB). Works without PHPUnit installed — assertions throw
`AssertionFailedException` instead.

**`Bot::fake()`'s `database` config defaults to `true`** — unlike production, which defaults to
`false` (§1.7). A test written without setting `database` explicitly gets a working `$ctx->user()`
even if the handler would crash on `null` in production. Pass `['database' => false]` if you want the
fake to match a bot that never configured the database at all.

`UpdateFactory` builds updates: `command()`, `text()`, `callbackQuery()`, `photo()`.
Note `FakeUser` is an attribute bag, not your real `user_model` — full-stack tests against your own
tables still need a real database.

---

## 16. Laravel integration (optional)

The library is standalone-first; Laravel is an opt-in layer via `Devflow\TelegramBot\Laravel\TelegramBotServiceProvider`
(auto-discovered).

- **Register handlers with the `TelegramBot` facade, not the static `Bot::` facade**, from inside a
  Laravel app's own `boot()` (e.g. `AppServiceProvider::boot()`):
  ```php
  use Devflow\TelegramBot\Laravel\Facades\TelegramBot;

  TelegramBot::onCommand('start', function (Context $ctx) { $ctx->reply('Hi!'); });
  ```
  `Bot::onCommand()` also works as long as `TelegramBotServiceProvider` is registered (it forces
  `Bot::init()` during its own `boot()`, which Laravel always runs before a consuming app's provider).
- **Config**: `config/telegram.php` (publish with `--tag=telegram-config`) ships `token`,
  `webhook_secret`, `database`, `webhook_route`. The whole array is passed to `Bot::init()`, so add
  any other key from §1's config table (`proxy`, `lang_path`, `allowed_chat_types`, ...) to it
  directly — they aren't in the file by default.
- **Migrations**: `--tag=telegram-migrations` publishes the same `telegram_users` /
  `bot_settings` / `telegram_broadcasts` schema `devflow migrate` uses standalone.
- **Webhook route**: auto-registered at `telegram.webhook_route` (default `telegram/webhook`) —
  set it to `null` to wire your own route calling `app(BotInstance::class)->run()`.
- **Artisan commands**: `php artisan telegram:set-webhook <url>`, `telegram:delete-webhook`,
  `telegram:webhook-info`. Need `illuminate/console` on the classpath (always present in a real
  Laravel app).

---

## 17. Error handling

`TelegramApiException` carries `telegramErrorCode()`, `parameters()`, `retryAfter()`,
`migrateToChatId()`. 429s are retried automatically within `max_retries`/`max_retry_after`; if one
still surfaces, `retryAfter()` says how long Telegram wanted.

### Classifying an error

Telegram reports "this user blocked your bot" exactly the way it reports a malformed request, so
the exception classifies itself. **Use these instead of matching on `getMessage()`** — the tables
behind them cover every description Telegram is known to return.

| Predicate | Means | Do |
|---|---|---|
| `isChatUnavailable()` | Blocked, deactivated, kicked, chat not found | Stop sending; mark the user inactive |
| `isPermissionDenied()` | In the chat, but not allowed to post | Fix rights — *not* a dead user |
| `isIgnorable()` | No-op edit, stale callback query, already-deleted message | Swallow it |
| `isRateLimited()` | 429 | Wait `retryAfter()` |
| `isTransient()` | 5xx, network failure | Retry later |
| `isExpected()` | Any of the above | Don't treat as a crash |

```php
try {
    $ctx->reply($text);
} catch (TelegramApiException $e) {
    if ($e->isChatUnavailable()) {
        $ctx->user()?->update(['is_active' => false]);
        return;
    }
    throw $e;
}
```

`isChatUnavailable()` and `isPermissionDenied()` never both return true: deactivating a user over a
group permission problem would drop them from broadcasts for a reason unrelated to them.

### What the library already handles

- **Polling** advances its offset *before* dispatching, so a handler that throws can never cause the
  same update to be redelivered forever. Fetch failures back off 1→2→5→10→30→60s; 401/404/409 stop
  the loop with an explanation instead of retrying something a human has to fix.
- **Webhook dispatch** absorbs `isExpected()` errors, so a blocked user doesn't page `ADMIN_CHAT_ID`.
- **`broadcast:run`** sets `is_active = 0` on recipients that report `isChatUnavailable()`, and
  reports the count. `/start` reactivates them if they come back.

### Other exceptions

`MissingTokenException` means `BOT_TOKEN` is empty. `WebhookException` covers a bad secret token or
malformed payload. `BotNotInitializedException` means `Bot::init()` was never called.

`public/webhook.php` always returns HTTP 200 before dispatching, so Telegram never retries on a
crash; failures go to `logs/` and to `ADMIN_CHAT_ID` via `botLog()`.

---

## 18. Premium Emoji

`Devflow\TelegramBot\Support\Emoji` replaces raw `<tg-emoji emoji-id="...">🔥</tg-emoji>` /
`![🔥](tg://emoji?id=...)` markup with a `:name:` shortcode. Register once (a scaffolded project
does this in `bootstrap/app.php` from `app/Emojis.php`):

```php
Emoji::register('fire', '5368324170671202286', '🔥');
// or: Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');
```

Then anywhere you build message text:

```php
$ctx->reply(Emoji::text('Nice :fire: work!'), ['parse_mode' => 'HTML']);
```

`Emoji::get('fire')` returns markup for a single name. Second argument to either is `'HTML'`
(default) or `'MarkdownV2'` — those are the only two Telegram parse modes with custom-emoji syntax;
passing legacy `'Markdown'` throws `InvalidArgumentException` instead of sending broken markup. An
unregistered `:name:` (or an incidental colon-wrapped substring like a timestamp) is left untouched,
never warned about.

---

## 19. Contributing to this library

- Tests: `php vendor/bin/phpunit` (all must pass).
- Nothing under `src/` may reference PHPUnit unguarded — `ProductionDependencyTest` enforces it.
- Bumping a version means **both** `composer.json`'s `version` **and**
  `Console\Application::VERSION`, then tag and push (`git push origin main --tags`) — Packagist
  reads the tag, not the branch.
- Adding a CLI command means registering it in `Console\Application::$commands` **and** documenting
  it in `showHelp()`; `ConsoleApplicationTest` fails if help advertises an unregistered command.
- Scaffold templates all live in `Console\Commands\NewProjectCommand`.
- After changing the public surface, run `vendor/bin/devflow ai:manifest` and update this file.
