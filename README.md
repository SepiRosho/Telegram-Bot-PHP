# devflow/telegram-bot

A clean PHP 8.1+ library for building Telegram bots. Static facade syntax, fluent routing, middleware pipeline, premade database schemas, and a project scaffolder. Works standalone or inside Laravel.

## Requirements

- PHP 8.1+
- Composer
- MySQL/PostgreSQL (optional, for user/session tracking)

## Installation

```bash
composer require devflow/telegram-bot
```

---

## Scaffolding a New Project

The library ships a `devflow` CLI tool that generates a ready-to-use project structure — similar to `laravel new`.

```bash
composer require devflow/telegram-bot
vendor/bin/devflow new my-telegram-bot
cd my-telegram-bot
composer install
cp .env.example .env   # fill in BOT_TOKEN + DB credentials
```

This creates:

```
my-telegram-bot/
├── app/
│   ├── Commands/           ← /command handlers (StartCommand, HelpCommand pre-made)
│   ├── Callbacks/          ← callback_data handlers
│   ├── Middleware/         ← middleware (AuthMiddleware pre-made)
│   ├── Flows/              ← multi-step wizard handlers
│   └── Services/           ← business logic
├── bootstrap/
│   ├── app.php             ← Bot init, DB setup, all handler registrations
│   └── helpers.php         ← env() helper
├── config/bot.php          ← token, DB config
├── public/webhook.php      ← entry point — point your webhook here
├── .env + .env.example
└── composer.json
```

Point your Telegram webhook at `https://yourdomain.com/public/webhook.php` and the bot is live.

### Code generators

Run these from inside your project to generate boilerplate files:

```bash
# Generate a /command handler → app/Commands/BroadcastCommand.php
vendor/bin/devflow make:command BroadcastCommand

# Generate a callback handler → app/Callbacks/ConfirmCallback.php
vendor/bin/devflow make:callback ConfirmCallback

# Generate a middleware class → app/Middleware/RateLimitMiddleware.php
vendor/bin/devflow make:middleware RateLimitMiddleware

# Generate a multi-step wizard → app/Flows/RegistrationFlow.php
vendor/bin/devflow make:flow RegistrationFlow
```

---

## Quick Start (without scaffolding)

```php
<?php
require 'vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

Bot::init('YOUR_BOT_TOKEN');

Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('Hello, ' . $ctx->from()->firstName . '!');
});

Bot::onText(function (Context $ctx) {
    $ctx->reply('You said: ' . $ctx->text());
});

Bot::run();
```

Point your webhook at this file and you're live.

---

## Core Concepts

### Initialization

```php
Bot::init(token: 'YOUR_TOKEN');

// With config options
Bot::init('YOUR_TOKEN', [
    'database' => true,
]);
```

### Routing

```php
Bot::onCommand('start', $handler);          // /start
Bot::onText($handler);                       // any plain text (non-command)
Bot::onMessage($handler);                    // any message (text, photo, etc.)
Bot::onCallbackQuery('pattern_*', $handler); // callback data matching a pattern
Bot::onPhoto($handler);                      // message with photo
Bot::onDocument($handler);                   // message with document
Bot::onInlineQuery($handler);                // inline query
Bot::onUpdate($handler);                     // catch-all fallback
```

Handlers can be **closures** or **class names** implementing `HandlerInterface`:

```php
// Closure
Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('Hi!');
});

// Class
Bot::onCommand('start', StartHandler::class);

// StartHandler.php
class StartHandler implements \Devflow\TelegramBot\Handlers\HandlerInterface
{
    public function handle(Context $ctx): void
    {
        $ctx->reply('Hi!');
    }
}
```

### Context

Every handler receives a `Context` object:

```php
// Reading the update
$ctx->chatId()        // int
$ctx->userId()        // int
$ctx->text()          // ?string
$ctx->callbackData()  // ?string
$ctx->from()          // ?User
$ctx->message()       // ?Message
$ctx->callbackQuery() // ?CallbackQuery
$ctx->update()        // Update

// Shorthand API calls
$ctx->reply('text', $options)
$ctx->replyWithPhoto('file_id', $options)
$ctx->replyWithDocument('file_id', $options)
$ctx->answerCallback('Toast text', showAlert: false)
$ctx->sendChatAction('typing')

// Flow state (requires DB)
$ctx->user()                    // TelegramUser model
$ctx->step()                    // ?string
$ctx->setStep('wizard.step1')
$ctx->temp('key')               // mixed
$ctx->setTemp('key', $value)
$ctx->clearFlow()               // reset step + temp_data
```

### Middleware

Middleware runs before every matched handler. Register with `Bot::use()`.

```php
// Closure
Bot::use(function (Context $ctx, callable $next) {
    if ($ctx->user()?->is_banned) {
        $ctx->reply('You are banned.');
        return; // stops the chain
    }
    $next($ctx);
});

// Class
Bot::use(LogMiddleware::class);

// LogMiddleware.php
class LogMiddleware implements \Devflow\TelegramBot\Middleware\MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void
    {
        error_log('user:' . $ctx->userId() . ' — ' . ($ctx->text() ?? 'no text'));
        $next($ctx);
    }
}
```

### Sending Messages

The `Bot::` facade gives you static access to all Telegram API methods anywhere in your code — not just inside handlers:

```php
Bot::sendMessage($chatId, 'Hello!');
Bot::sendMessage($chatId, '<b>Bold</b>', ['parse_mode' => 'HTML']);

Bot::sendPhoto($chatId, 'https://...', ['caption' => 'Look at this']);
Bot::sendDocument($chatId, $fileId);
Bot::sendAudio($chatId, $fileId);
Bot::sendVideo($chatId, $fileId);
Bot::sendSticker($chatId, $fileId);
Bot::sendLocation($chatId, 35.6892, 51.3890);
Bot::sendVenue($chatId, 35.6892, 51.3890, 'Azadi Tower', 'Tehran, Iran');
Bot::sendContact($chatId, '+1234567890', 'John');
Bot::sendPoll($chatId, 'Favorite language?', ['PHP', 'Python', 'Go']);
Bot::sendDice($chatId);
Bot::sendChatAction($chatId, 'typing');

Bot::editMessageText($chatId, $messageId, 'Updated text');
Bot::editMessageReplyMarkup($chatId, $messageId, $newMarkup);
Bot::deleteMessage($chatId, $messageId);
Bot::forwardMessage($chatId, $fromChatId, $messageId);
Bot::copyMessage($chatId, $fromChatId, $messageId);

Bot::sendMediaGroup($chatId, [
    ['type' => 'photo', 'media' => 'file_id_1'],
    ['type' => 'photo', 'media' => 'file_id_2'],
]);

Bot::answerCallbackQuery($callbackQueryId, ['text' => 'Done!']);
Bot::answerInlineQuery($inlineQueryId, $results);

Bot::getChatMember($chatId, $userId);
Bot::getChatMemberCount($chatId);
Bot::banChatMember($chatId, $userId);
Bot::unbanChatMember($chatId, $userId);
Bot::restrictChatMember($chatId, $userId, $permissions);
Bot::promoteChatMember($chatId, $userId, ['can_manage_chat' => true]);

Bot::setMyCommands([
    ['command' => 'start', 'description' => 'Start the bot'],
    ['command' => 'help',  'description' => 'Get help'],
]);

Bot::getMe();
Bot::setWebhook('https://yourdomain.com/webhook.php');
Bot::deleteWebhook();
Bot::getWebhookInfo();
```

### Inline Keyboards

```php
$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '✅ Confirm', 'callback_data' => 'confirm'],
            ['text' => '❌ Cancel',  'callback_data' => 'cancel'],
        ],
    ],
];

Bot::sendMessage($chatId, 'Are you sure?', ['reply_markup' => $keyboard]);

Bot::onCallbackQuery('confirm', function (Context $ctx) {
    $ctx->answerCallback('Confirmed!');
    Bot::editMessageText($ctx->chatId(), $ctx->callbackQuery()->message->messageId, '✅ Done');
});
```

---

## Multi-Step Flows (Wizard)

Use `step` and `temp_data` on the `TelegramUser` to guide users through multi-step flows:

```php
Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('register.ask_name');
    $ctx->reply('What is your name?');
});

Bot::onText(function (Context $ctx) {
    match ($ctx->step()) {
        'register.ask_name' => function () use ($ctx) {
            $ctx->setTemp('name', $ctx->text());
            $ctx->setStep('register.ask_age');
            $ctx->reply('How old are you?');
        },
        'register.ask_age' => function () use ($ctx) {
            // validate, save, clearFlow
            $ctx->clearFlow();
            $ctx->reply('Registered! Name: ' . $ctx->temp('name'));
        },
        default => $ctx->reply('Use /register to begin.'),
    };
});
```

See `examples/03_wizard_flow.php` for the full example.

---

## Database Setup

### Standalone

```php
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'my_bot',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

Run the SQL migrations from `database/migrations/`:

```bash
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_users.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/bot_settings.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_broadcasts.sql
```

### Laravel

```bash
php artisan vendor:publish --tag=telegram-config
php artisan vendor:publish --tag=telegram-migrations
php artisan migrate
```

Add to `.env`:
```
TELEGRAM_BOT_TOKEN=your_token
TELEGRAM_WEBHOOK_SECRET=optional_secret
TELEGRAM_DATABASE=true
TELEGRAM_WEBHOOK_ROUTE=telegram/webhook
```

Register your handlers in a service provider:

```php
// app/Providers/TelegramServiceProvider.php
public function boot(): void
{
    Bot::onCommand('start', StartHandler::class);
    Bot::onText(fn(Context $ctx) => $ctx->reply('Hello!'));
}
```

Set your webhook:

```bash
php artisan telegram:set-webhook https://yourdomain.com/telegram/webhook
php artisan telegram:webhook-info
php artisan telegram:delete-webhook
```

---

## Database Models

### TelegramUser

```php
use Devflow\TelegramBot\Database\Models\TelegramUser;

$user = TelegramUser::where('telegram_id', 123456)->first();

$user->isAdmin();          // bool
$user->isSuperAdmin();     // bool
$user->hasPermission('can_broadcast');  // bool

$user->ban('Spamming');
$user->unban();
$user->touchActivity();

$user->referrals;  // HasMany — users who joined via this user's referral code
$user->inviter;    // BelongsTo — who referred this user
```

### BotSetting

```php
use Devflow\TelegramBot\Database\Models\BotSetting;

BotSetting::set('welcome_message', 'Hello {name}!');
$msg = BotSetting::get('welcome_message', 'Hello!');
BotSetting::forget('welcome_message');
```

### Broadcast

```php
use Devflow\TelegramBot\Database\Models\Broadcast;

$broadcast = Broadcast::create([
    'message' => 'Big announcement!',
    'type'    => 'text',
    'status'  => 'pending',
]);

$broadcast->isPending();      // bool
$broadcast->progressPercent(); // float 0-100
```

---

## Examples

| File | Description |
|---|---|
| `examples/01_basic_bot.php` | `/start`, text echo, fallback handler |
| `examples/02_inline_keyboards.php` | Inline buttons, callback handling, message editing |
| `examples/03_wizard_flow.php` | Multi-step registration wizard |
| `examples/04_middleware.php` | Ban check, logging, admin guard |
| `examples/05_laravel.php` | Full Laravel integration walkthrough |

For a full scaffolded project, run `vendor/bin/devflow new my-bot` — it generates the recommended folder structure with pre-made handlers and middleware.

---

## License

MIT
