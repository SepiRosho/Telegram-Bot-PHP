# 01 — Installation & Project Structure

## Prerequisites

- PHP 8.1 or higher
- [Composer](https://getcomposer.org/) installed globally
- A web server with a public HTTPS URL (or ngrok for local development — see [08-local-dev.md](08-local-dev.md))
- MySQL or MariaDB (optional, only needed for user tracking and flows)

---

## Creating a new project

Install the library globally or as a dev tool, then scaffold:

```bash
composer require devflow/telegram-bot
vendor/bin/devflow new my-bot
cd my-bot
composer install
```

This creates a complete project in a `my-bot/` folder and installs all dependencies.

---

## What each file and folder does

```
my-bot/
├── app/                    ← YOUR code lives here
│   ├── Commands/           ← One class per bot command (/start, /help, etc.)
│   ├── Callbacks/          ← Handlers for inline button presses
│   ├── Middleware/         ← Code that runs before every handler
│   ├── Flows/              ← Multi-step conversations (wizards)
│   └── Services/           ← Business logic (database queries, external APIs, etc.)
│
├── bootstrap/
│   ├── app.php             ← The "wiring file" — connects everything together
│   └── helpers.php         ← Defines the env() helper function (auto-loaded)
│
├── config/
│   └── (empty for now)
│
├── public/
│   └── webhook.php         ← The URL you give to Telegram. Every message hits this file.
│
├── .env                    ← Your secrets (token, DB password). Never commit this.
├── .env.example            ← A safe template showing what variables are needed
├── .gitignore              ← Tells git to ignore vendor/, .env, etc.
└── composer.json           ← Defines your project's dependencies and autoloading
```

---

## Understanding namespaces (plain English)

If you open any file in `app/Commands/`, you'll see this at the top:

```php
namespace App\Commands;
```

Think of a namespace like a folder path for classes. It tells PHP "this class lives under `App\Commands`". The backslash `\` is just PHP's separator (like `/` in a file path).

When you reference a class from another file, you use its full path:

```php
Bot::onCommand('start', \App\Commands\StartCommand::class);
```

Or you can import it at the top of the file with `use`:

```php
use App\Commands\StartCommand;

Bot::onCommand('start', StartCommand::class);
```

Both do exactly the same thing.

---

## Understanding autoloading

Normally in PHP you'd need `require 'app/Commands/StartCommand.php'` in every file that uses a class. Composer handles this automatically through a system called **autoloading**.

Your `composer.json` has this section:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    }
}
```

This tells Composer: *"any class whose name starts with `App\` can be found in the `app/` folder."*

So `App\Commands\StartCommand` maps to `app/Commands/StartCommand.php`. PHP finds it automatically.

> **Important:** Every time you add a new class file, run:
> ```bash
> composer dump-autoload
> ```
> This rebuilds the autoload map. Without it, PHP won't find your new class and you'll get a "Class not found" error.

---

## The .env file

Open `.env` and fill in your bot token at minimum:

```
BOT_TOKEN=123456:ABC-your-token-here
BOT_DATABASE=false

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_bot
DB_USERNAME=root
DB_PASSWORD=
```

- **BOT_TOKEN** — get this from [@BotFather](https://t.me/BotFather) on Telegram
- **BOT_DATABASE** — leave as `false` until you've set up the database (see [06-database.md](06-database.md))
- **DB_*** — only needed when BOT_DATABASE is enabled

The `env('BOT_TOKEN')` calls in your code read from this file. Your `.env` is in `.gitignore` so it's never committed to git — keep your real credentials here.

---

## Next step

[02-first-bot.md](02-first-bot.md) — Set your webhook URL and receive your first message.
