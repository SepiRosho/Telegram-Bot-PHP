# devflow/telegram-bot

A clean PHP 8.1+ library for building Telegram bots. Static facade syntax, fluent routing, middleware pipeline, premade database schemas, and a project scaffolder. Works standalone or inside Laravel.

## Requirements

- PHP 8.1+
- Composer
- MySQL/PostgreSQL (optional, for user/session tracking, rate limiting, wizard flows)

## Installation

```bash
composer require devflow/telegram-bot
```

---

## Scaffolding a New Project

The library ships a `devflow` CLI tool that generates a ready-to-use project structure — similar to `laravel new`.

**Linux / Mac / Git Bash:**
```bash
vendor/bin/devflow new my-telegram-bot
cd my-telegram-bot
composer install
```

**Windows (Command Prompt or PowerShell):**
```
vendor\bin\devflow new my-telegram-bot
cd my-telegram-bot
composer install
```

> **Windows tip:** Composer generates a `.bat` wrapper automatically, so `vendor\bin\devflow` works in CMD and PowerShell. If it does not, use `php vendor/bin/devflow new my-telegram-bot` instead.

After scaffolding, edit `.env` and fill in your `BOT_TOKEN`:

```
BOT_TOKEN=your_bot_token_here
```

Then point your Telegram webhook at `https://yourdomain.com/public/webhook.php` — done.

### Generated structure

```
my-telegram-bot/
├── app/
│   ├── Commands/           <- /command handlers (StartCommand, HelpCommand pre-made)
│   ├── Callbacks/          <- callback_data handlers
│   ├── Middleware/         <- middleware (AuthMiddleware pre-made)
│   ├── Handlers/           <- handler group classes (UserHandlers, AdminHandlers pre-made)
│   ├── Texts/              <- localized text classes (WelcomeText pre-made)
│   ├── Flows/              <- multi-step wizard handlers
│   └── Services/           <- business logic
├── bootstrap/
│   ├── app.php             <- Bot init, DB setup, handler loading
│   └── helpers.php         <- env() helper
├── public/
│   └── webhook.php         <- entry point — point your webhook here
├── .htaccess               <- blocks all requests except public/webhook.php
├── .env + .env.example
└── composer.json
```

### Code generators

Run these from inside your project to generate boilerplate files:

**Linux / Mac / Git Bash:**
```bash
vendor/bin/devflow make:command  BroadcastCommand   # -> app/Commands/BroadcastCommand.php
vendor/bin/devflow make:callback ConfirmCallback    # -> app/Callbacks/ConfirmCallback.php
vendor/bin/devflow make:middleware MyMiddleware     # -> app/Middleware/MyMiddleware.php
vendor/bin/devflow make:flow     RegistrationFlow   # -> app/Flows/RegistrationFlow.php
vendor/bin/devflow make:text     WelcomeText        # -> app/Texts/WelcomeText.php
```

**Windows:**
```
vendor\bin\devflow make:command  BroadcastCommand
vendor\bin\devflow make:text     WelcomeText
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
Bot::init('YOUR_TOKEN');

// With database enabled
Bot::init('YOUR_TOKEN', ['database' => true]);
```

### Routing

```php
Bot::onCommand('start', $handler);           // /start
Bot::onText($handler);                        // any plain text (non-command)
Bot::onMessage($handler);                     // any message (text, photo, etc.)
Bot::onCallbackQuery('pattern_*', $handler);  // callback data matching a pattern
Bot::onPhoto($handler);                       // message with photo
Bot::onDocument($handler);                    // message with document
Bot::onInlineQuery($handler);                 // inline query
Bot::onStep('wizard.step1', $handler);        // text message AND user's step matches (requires DB)
Bot::onUpdate($handler);                      // catch-all fallback
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

### Handler Groups

Split a large bot into multiple handler files. Each group is a class with a static `register()` method:

```php
// app/Handlers/UserHandlers.php
class UserHandlers
{
    public static function register(): void
    {
        Bot::onCommand('start', \App\Commands\StartCommand::class);
        Bot::onText(fn(Context $ctx) => $ctx->reply($ctx->text()));
    }
}

// app/Handlers/AdminHandlers.php
class AdminHandlers
{
    public static function register(): void
    {
        Bot::onCommand('stats', function (Context $ctx) {
            if (!$ctx->user()?->isAdmin()) return;
            $ctx->reply('All systems normal.');
        });
    }
}

// bootstrap/app.php
Bot::loadHandlers([
    \App\Handlers\UserHandlers::class,
    \App\Handlers\AdminHandlers::class,
]);
```

### Context

Every handler receives a `Context` object:

```php
// Reading the update
$ctx->chatId()        // int
$ctx->userId()        // int
$ctx->text()          // ?string
$ctx->callbackData()  // ?string
$ctx->from()          // ?User  — live Telegram data (always available)
$ctx->user()          // ?TelegramUser — DB record (requires database)
$ctx->message()       // ?Message
$ctx->callbackQuery() // ?CallbackQuery
$ctx->update()        // Update

// Shorthand API calls
$ctx->reply('text', $options)
$ctx->replyWithPhoto('file_id', $options)
$ctx->replyWithDocument('file_id', $options)
$ctx->answerCallback('Toast text', showAlert: false)
$ctx->sendChatAction('typing')

// Wizard flow state (requires DB)
$ctx->step()                    // ?string
$ctx->setStep('wizard.step1')
$ctx->temp('key')               // mixed
$ctx->setTemp('key', $value)
$ctx->clearFlow()               // reset step + temp_data
```

### Middleware

Middleware runs before every matched handler:

```php
// Class-based
Bot::use(LogMiddleware::class);

// Closure
Bot::use(function (Context $ctx, callable $next) {
    if ($ctx->user()?->is_banned) {
        $ctx->reply('You are banned.');
        return;
    }
    $next($ctx);
});
```

#### Built-in rate limiter

DB-backed rolling-window rate limiter. Requires database.

```php
use Devflow\TelegramBot\Middleware\RateLimitMiddleware;

Bot::use(new RateLimitMiddleware(maxHits: 10, windowSeconds: 60));
// Optional: custom message
Bot::use(new RateLimitMiddleware(maxHits: 5, windowSeconds: 30, message: 'Slow down!'));
```

### Localized Texts

Keep all bot messages in dedicated classes with `{variable}` placeholders. Each class returns the correct language automatically.

```php
use Devflow\TelegramBot\Support\BotText;

class WelcomeText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => 'Hello, {name}! Welcome to the bot.',
            'fa' => 'سلام، {name}! به ربات خوش آمدید.',
            'de' => 'Hallo, {name}! Willkommen beim Bot.',
        ];
    }
}

// In a handler — language auto-detected from Telegram:
$ctx->reply(WelcomeText::forContext($ctx, ['name' => $ctx->from()->firstName]));

// Manual language:
$ctx->reply(WelcomeText::get(['name' => 'John'], 'fa'));
```

Generate with: `vendor/bin/devflow make:text WelcomeText` (Windows: `vendor\bin\devflow make:text WelcomeText`)

### Input Helpers

Static helpers for validating and sanitizing user input:

```php
use Devflow\TelegramBot\Support\Input;

Input::isInt($value)          // true if the string is a whole number
Input::isFloat($value)        // true if the string is a decimal number
Input::isEmail($value)        // true if valid email
Input::isUrl($value)          // true if valid URL
Input::isPhone($value)        // true if looks like a phone number

Input::toInt($value)          // cast to int
Input::toFloat($value)        // cast to float
Input::clean($value)          // trim + strip HTML tags
Input::truncate($value, 100)  // cut at word boundary, max 100 chars
Input::between($n, 1, 100)    // true if 1 <= n <= 100
Input::minLength($str, 3)     // true if mb_strlen >= 3
Input::maxLength($str, 255)   // true if mb_strlen <= 255
```

### Multi-Step Flows (Wizard)

Use `onStep()` to match a specific step name cleanly:

```php
use Devflow\TelegramBot\Support\Input;

Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('reg.name');
    $ctx->reply('What is your name?');
});

Bot::onStep('reg.name', function (Context $ctx) {
    $ctx->setTemp('name', $ctx->text());
    $ctx->setStep('reg.age');
    $ctx->reply('How old are you?');
});

Bot::onStep('reg.age', function (Context $ctx) {
    if (!Input::isInt($ctx->text()) || !Input::between((int) $ctx->text(), 13, 120)) {
        $ctx->reply('Please enter a valid age (13-120):');
        return;
    }
    $ctx->setTemp('age', $ctx->text());
    $ctx->clearFlow();
    $ctx->reply('Done! Name: ' . $ctx->temp('name') . ', Age: ' . $ctx->temp('age'));
});
```

> `onStep()` only matches plain text messages (not commands), and only when the user's stored step matches. Requires database.

### Sending Messages

The `Bot::` facade gives you static access to all Telegram API methods anywhere:

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
Bot::sendChatAction($chatId, 'typing');

Bot::editMessageText($chatId, $messageId, 'Updated text');
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
Bot::banChatMember($chatId, $userId);
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
            ['text' => 'Confirm', 'callback_data' => 'confirm'],
            ['text' => 'Cancel',  'callback_data' => 'cancel'],
        ],
    ],
];

Bot::sendMessage($chatId, 'Are you sure?', ['reply_markup' => $keyboard]);

Bot::onCallbackQuery('confirm', function (Context $ctx) {
    $ctx->answerCallback('Confirmed!');
    Bot::editMessageText($ctx->chatId(), $ctx->callbackQuery()->message->messageId, 'Done');
});
```

---

## Database Setup

### Standalone (XAMPP / VPS)

**Step 1 — Create the database**

*phpMyAdmin (Windows/XAMPP):* Open `http://localhost/phpmyadmin`, click **New**, enter `my_bot`, choose `utf8mb4_unicode_ci`, click **Create**.

*Command line (Linux/Mac):*
```bash
mysql -u root -p -e "CREATE DATABASE my_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Step 2 — Import the SQL migrations**

*phpMyAdmin:* Select `my_bot` → **Import** tab → choose each file from `vendor/devflow/telegram-bot/database/migrations/` → **Go**.

*Linux/Mac/Git Bash:*
```bash
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_users.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/bot_settings.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_broadcasts.sql
```

*PowerShell (Windows):*
```powershell
Get-Content vendor\devflow\telegram-bot\database\migrations\telegram_users.sql | mysql -u root my_bot
Get-Content vendor\devflow\telegram-bot\database\migrations\bot_settings.sql | mysql -u root my_bot
Get-Content vendor\devflow\telegram-bot\database\migrations\telegram_broadcasts.sql | mysql -u root my_bot
```

> **XAMPP default:** username `root`, no password — omit `-p`.

**Step 3 — Activate in bootstrap/app.php**

Uncomment the Capsule block and update `Bot::init()`:

```php
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_DRIVER', 'mysql'),
    'host'      => env('DB_HOST', '127.0.0.1'),
    'database'  => env('DB_DATABASE', 'my_bot'),
    'username'  => env('DB_USERNAME', 'root'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Change this:
Bot::init(env('BOT_TOKEN'));
// To:
Bot::init(env('BOT_TOKEN'), ['database' => true]);
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
TELEGRAM_DATABASE=true
```

Register handlers in a service provider:

```php
public function boot(): void
{
    Bot::loadHandlers([
        \App\Handlers\UserHandlers::class,
    ]);
}
```

Set your webhook:

```bash
php artisan telegram:set-webhook https://yourdomain.com/telegram/webhook
```

---

## Database Models

### TelegramUser

```php
use Devflow\TelegramBot\Database\Models\TelegramUser;

$user = TelegramUser::where('telegram_id', 123456)->first();

$user->isAdmin();
$user->isSuperAdmin();
$user->hasPermission('can_broadcast');

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

---

## Examples

| File | Description |
|---|---|
| `examples/01_basic_bot.php` | `/start`, text echo, fallback handler |
| `examples/02_inline_keyboards.php` | Inline buttons, callback handling, message editing |
| `examples/03_wizard_flow.php` | Multi-step registration wizard |
| `examples/04_middleware.php` | Ban check, logging, DB-backed rate limiting |
| `examples/05_laravel.php` | Full Laravel integration walkthrough |
| `examples/06_handler_groups.php` | Splitting handlers across files with `loadHandlers()` |
| `examples/07_texts_and_input.php` | Localized text classes and input validation |

---

## Guides

Step-by-step documentation in [`docs/`](docs/README.md):

- [01 — Installation & project structure](docs/01-installation.md)
- [02 — Your first bot & webhook setup](docs/02-first-bot.md)
- [03 — Handlers (commands, text, callbacks, onStep, handler groups)](docs/03-handlers.md)
- [04 — The Context object](docs/04-context.md)
- [05 — Middleware (including built-in rate limiter)](docs/05-middleware.md)
- [06 — Database setup](docs/06-database.md)
- [07 — Wizard flows](docs/07-flows.md)
- [08 — Local development with ngrok](docs/08-local-dev.md)
- [09 — Localized text classes](docs/09-texts.md)
- [10 — Handler groups](docs/10-handler-groups.md)

---

## License

MIT
