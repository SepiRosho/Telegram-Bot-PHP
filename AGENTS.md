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
| `user_model` | class-string | `TelegramUser::class` | Your Eloquent subclass. |
| `user_defaults` | ?callable | `null` | `callable(Update): array`, merged in on first insert. |
| `debug` | bool | `false` | Logs every route match/no-match, including chat-filter drops. |
| `step_routes_first` | bool | `true` | Evaluate step routes first, regardless of registration order. |
| `auto_answer_callbacks` | bool | `true` | Auto-answers unrouted callback queries so the tap spinner clears. |
| `max_retries` | int | `2` | Retries after a 429, honouring `retry_after`. Uploads are never retried. |
| `max_retry_after` | int | `60` | Longest `retry_after` (seconds) worth waiting on. |
| `timeout` | int | `30` | HTTP timeout, seconds. |

Env vars in a scaffolded `.env`: `BOT_TOKEN`, `ADMIN_CHAT_ID`, `WEBHOOK_SECRET`, `PROXY_URL`,
`BROADCAST_RATE`, `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
`lang_path` and `default_locale` are **not** env vars — they're hardcoded in `bootstrap/app.php`.

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
vendor/bin/devflow poll                    # long-polling (needs webhook:delete first)
vendor/bin/devflow broadcast:run           # process pending broadcasts
vendor/bin/devflow migrate                 # run pending migrations
vendor/bin/devflow migrate:status          # applied / Pending / Untracked
vendor/bin/devflow webhook:set <https-url> # register webhook (sends WEBHOOK_SECRET if set)
vendor/bin/devflow webhook:delete
vendor/bin/devflow webhook:info
vendor/bin/devflow make:command <Name>     # → app/Commands/
vendor/bin/devflow make:callback <Name>    # → app/Callbacks/
vendor/bin/devflow make:middleware <Name>  # → app/Middleware/
vendor/bin/devflow make:flow <Name>        # → app/Flows/
vendor/bin/devflow make:text <Name>        # → app/Texts/
vendor/bin/devflow make:service <Name>     # → app/Services/
vendor/bin/devflow make:migration <name>   # → database/migrations/ (snake_case)
vendor/bin/devflow ai:manifest             # regenerate .ai/api.json
```

**When a bot misbehaves, run `devflow doctor` first** — it checks PHP extensions, `.env`, token
validity, DB connectivity, base tables, route count and webhook status in one pass.

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

`UpdateFactory` builds updates: `command()`, `text()`, `callbackQuery()`, `photo()`.
Note `FakeUser` is an attribute bag, not your real `user_model` — full-stack tests against your own
tables still need a real database.

---

## 16. Error handling

`TelegramApiException` carries `telegramErrorCode()`, `parameters()`, `retryAfter()`,
`migrateToChatId()`. 429s are retried automatically within `max_retries`/`max_retry_after`; if one
still surfaces, `retryAfter()` says how long Telegram wanted.

`MissingTokenException` means `BOT_TOKEN` is empty. `WebhookException` covers a bad secret token or
malformed payload. `BotNotInitializedException` means `Bot::init()` was never called.

`public/webhook.php` always returns HTTP 200 before dispatching, so Telegram never retries on a
crash; failures go to `logs/` and to `ADMIN_CHAT_ID` via `botLog()`.

---

## 17. Contributing to this library

- Tests: `php vendor/bin/phpunit` (all must pass).
- Nothing under `src/` may reference PHPUnit unguarded — `ProductionDependencyTest` enforces it.
- Bumping a version means **both** `composer.json`'s `version` **and**
  `Console\Application::VERSION`, then tag and push (`git push origin main --tags`) — Packagist
  reads the tag, not the branch.
- Adding a CLI command means registering it in `Console\Application::$commands` **and** documenting
  it in `showHelp()`; `ConsoleApplicationTest` fails if help advertises an unregistered command.
- Scaffold templates all live in `Console\Commands\NewProjectCommand`.
- After changing the public surface, run `vendor/bin/devflow ai:manifest` and update this file.
