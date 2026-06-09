# 02 — Your First Bot

## Step 1: Get your bot token

1. Open Telegram and search for [@BotFather](https://t.me/BotFather)
2. Send `/newbot` and follow the prompts
3. Copy the token it gives you (looks like `123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ`)
4. Paste it into your `.env` file:
   ```
   BOT_TOKEN=123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ
   ```

---

## Step 2: Set your webhook URL

Telegram needs to know where to send messages. You give it the URL of your `public/webhook.php` file.

Create a file called `set-webhook.php` in your project root:

```php
<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Devflow\TelegramBot\Bot;

Bot::init(env('BOT_TOKEN'));
Bot::setWebhook('https://yourdomain.com/public/webhook.php');

echo "Webhook set!\n";
```

Run it once from the command line:

```bash
php set-webhook.php
```

Then delete the file — you only need to run it once (or again if your domain changes).

> **Your URL must be HTTPS.** Telegram refuses plain HTTP. For local development without HTTPS, see [08-local-dev.md](08-local-dev.md).

To confirm your webhook is registered:

```php
Bot::init(env('BOT_TOKEN'));
$info = Bot::getWebhookInfo();
print_r($info);
```

---

## Step 3: Understand the request flow

Every time a user sends a message to your bot, Telegram makes an HTTP POST request to your webhook URL. Here's what happens:

```
User sends message
      ↓
Telegram calls POST https://yourdomain.com/public/webhook.php
      ↓
public/webhook.php loads .env, then requires bootstrap/app.php
      ↓
bootstrap/app.php initialises the bot and registers your handlers
      ↓
Bot::run() reads the incoming JSON, matches it to a handler, runs it
      ↓
Your handler calls $ctx->reply('...')
      ↓
The library makes an API call to Telegram
      ↓
User sees the reply
```

You only edit two things:
- **`bootstrap/app.php`** — to register handlers
- **Files in `app/`** — to write handler logic

You never touch `public/webhook.php` or `bootstrap/helpers.php`.

---

## Step 4: Write your first handler

Open `bootstrap/app.php`. You'll see the `/start` command is already registered:

```php
Bot::onCommand('start', \App\Commands\StartCommand::class);
```

Open `app/Commands/StartCommand.php`:

```php
class StartCommand implements HandlerInterface
{
    public function handle(Context $ctx): void
    {
        $name = $ctx->from()?->firstName ?? 'there';
        $ctx->reply("Hello, {$name}!\n\nUse /help to see what I can do.");
    }
}
```

Send `/start` to your bot — you should get a greeting back.

---

## Step 5: Add a simple echo

The generated `bootstrap/app.php` already has a text echo at the bottom:

```php
Bot::onText(function (Context $ctx) {
    $ctx->reply($ctx->text());
});
```

Send any non-command text to your bot. It echoes it back.

---

## Removing a webhook

If you need to stop the bot or switch to polling:

```php
Bot::init(env('BOT_TOKEN'));
Bot::deleteWebhook();
```

---

## Next step

[03-handlers.md](03-handlers.md) — All the ways to respond to different message types.
