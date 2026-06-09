# 08 — Local Development

Telegram requires your webhook URL to use **HTTPS**. On localhost you have plain HTTP, so you need a tunnel — a tool that creates a temporary public HTTPS URL that forwards traffic to your local machine.

---

## Option A: ngrok (recommended)

### Install ngrok on Windows

1. Download from [ngrok.com/download](https://ngrok.com/download) — pick the Windows version
2. Unzip it anywhere (e.g. `C:\ngrok\`)
3. Optional: add that folder to your PATH so you can run `ngrok` from anywhere

### Start the tunnel

If XAMPP runs on port 80:

```bash
ngrok http 80
```

If it runs on port 8080:

```bash
ngrok http 8080
```

You'll see output like:

```
Forwarding  https://a1b2c3d4.ngrok.io -> http://localhost:80
```

Copy the `https://` URL — that's your public webhook URL.

### Set your webhook

Create a temporary `set-webhook.php` in your project root:

```php
<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Devflow\TelegramBot\Bot;

Bot::init(env('BOT_TOKEN'));
Bot::setWebhook('https://a1b2c3d4.ngrok.io/my-bot/public/webhook.php');

echo "Done!\n";
```

Run it:

```bash
php set-webhook.php
```

> **Note:** The ngrok URL changes every time you restart ngrok on the free plan. You'll need to run `set-webhook.php` again each time. A paid ngrok account gives you a fixed URL.

### Your webhook path

The URL depends on where your project lives in your web root:

| Project location | Webhook URL |
|---|---|
| `htdocs/my-bot/` | `https://xxxx.ngrok.io/my-bot/public/webhook.php` |
| Web root is `my-bot/public/` | `https://xxxx.ngrok.io/webhook.php` |
| `htdocs/` directly | `https://xxxx.ngrok.io/public/webhook.php` |

---

## Option B: Cloudflare Tunnel (free, no URL change)

Cloudflare Tunnel gives a permanent free HTTPS URL tied to your account.

1. Install [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
2. Login: `cloudflared tunnel login`
3. Create a tunnel: `cloudflared tunnel create my-bot`
4. Start it: `cloudflared tunnel --url http://localhost:80`

The URL won't change between restarts, so you only set the webhook once.

---

## Debugging requests

When something goes wrong, Telegram silently retries — your bot just appears dead. Add error logging to `public/webhook.php` to see what's happening:

```php
<?php
// At the top of public/webhook.php, before the require lines:
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require __DIR__ . '/../vendor/autoload.php';
// ... rest of the file
```

Create the `logs/` folder and check `logs/error.log` when the bot doesn't respond. Common errors:

| Error | Cause |
|---|---|
| `Class "App\Commands\X" not found` | Forgot to run `composer dump-autoload` |
| `SQLSTATE[HY000]` | Database credentials wrong or tables not created |
| `Call to a member function on null` | Accessing `$ctx->user()` without database active |
| `Empty webhook payload` | Visiting webhook.php in a browser instead of Telegram sending a POST |

---

## Checking what Telegram is sending

To see the raw JSON Telegram sends:

```php
// Temporarily in public/webhook.php, after safeLoad():
file_put_contents(__DIR__ . '/../logs/last-update.json', file_get_contents('php://input'));
```

Open `logs/last-update.json` after sending a message. It shows the exact structure of the Update object.

---

## Stopping the bot

To stop Telegram from sending requests (e.g. while you're rebuilding):

```php
Bot::init(env('BOT_TOKEN'));
Bot::deleteWebhook();
```

Or just stop ngrok — Telegram will queue retries for a while then give up.
