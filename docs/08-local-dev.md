# 08 — Local Development

Telegram requires your webhook URL to use **HTTPS**. On localhost you have plain HTTP, so historically that meant a tunnel — a tool that creates a temporary public HTTPS URL that forwards traffic to your local machine. **You don't need one to get started**: `vendor/bin/devflow poll` runs your bot in long-polling mode instead, with no webhook, no tunnel, and no public URL at all. Reach for a tunnel (Option A/B below) only once you specifically need to test webhook delivery.

---

## Option 0: `devflow poll` (recommended for local dev)

Long-polling means your bot repeatedly asks Telegram "any new updates?" instead of Telegram pushing them to a webhook URL — so there's nothing to expose publicly. This is strictly less setup than a tunnel and is the fastest way to iterate locally.

```bash
vendor/bin/devflow poll
```

This boots `bootstrap/app.php` (same as a webhook request would) and then loops, calling `getUpdates()` and dispatching through your registered handlers exactly like a real webhook would. Press `Ctrl+C` to stop.

> **Delete your webhook first.** Telegram only delivers updates one way at a time — if a webhook is set, `getUpdates()` returns nothing (or errors). Delete it with a one-off script before polling (see [Stopping the bot](#stopping-the-bot) below):
> ```php
> Bot::init(env('BOT_TOKEN'));
> Bot::deleteWebhook();
> ```

Polling is meant for local development, not production — a long-running PHP process without a supervisor (systemd, supervisord, etc.) is a worse fit for a live server than a webhook behind your normal web server. Use polling to develop, then switch to a webhook (Option A/B, or a real HTTPS domain) to deploy.

---

## Testing without a token, webhook, or database

For verifying handler logic in CI (or just locally without any live bot), use `Bot::fake()` instead of `Bot::init()`. It swaps in an in-memory HTTP client (no real network calls) and an in-memory user repository (no real database), while your production handler code — `Bot::onCommand()`, `Bot::onText()`, etc. inside a `register()` method — keeps working unmodified, since it all routes through the same static `Bot` facade.

```php
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Testing\UpdateFactory;
use PHPUnit\Framework\TestCase;

class StartCommandTest extends TestCase
{
    public function test_start_replies_with_a_greeting(): void
    {
        $fake = Bot::fake();
        $fake->onCommand('start', function (Context $ctx) {
            $ctx->reply('Hello, ' . $ctx->from()?->firstName);
        });

        $fake->dispatch(UpdateFactory::command('start'));

        $fake->assertSent('sendMessage', fn(array $params) => $params['text'] === 'Hello, Test');
    }
}
```

`UpdateFactory` builds synthetic updates — `::text()`, `::command()`, `::callback()`, `::photo()`, `::voice()`, `::document()`, and `::raw()` as an escape hatch for anything else. `$fake->assertNotSent('sendMessage')` asserts the opposite. `$fake->users()` exposes the in-memory `FakeUserRepository`, so `step()`/`temp()`/`setLocale()` flows can be exercised across multiple `dispatch()` calls exactly like a real multi-message conversation.

If your bot is registered via a real `App\Handlers\*::register()` class (the scaffolded pattern), call it after `Bot::fake()` and before `dispatch()`:

```php
$fake = Bot::fake();
\App\Handlers\UserHandlers::register();
$fake->dispatch(UpdateFactory::command('start'));
```

### Works with or without PHPUnit

`Bot::fake()` and its `assertSent()`/`assertNotSent()` don't require `phpunit/phpunit` to be installed. If PHPUnit is present (the normal case inside a test suite), assertions delegate to `PHPUnit\Framework\Assert`, so failures show up as regular PHPUnit assertion failures with the usual diff output and count toward your suite's assertion total. If PHPUnit is **not** installed — e.g. you're driving `Bot::fake()` from a throwaway CLI script, or a consuming project that only requires this library without `require-dev` — a failed assertion throws `Devflow\TelegramBot\Exceptions\AssertionFailedException` (a plain `\RuntimeException`) instead, with the same message. Either way, `Bot::fake()` itself never requires PHPUnit to be autoloadable just to construct or dispatch.

### Fake users are not your real `telegram_users` table

`Bot::fake()`'s user repository (`FakeUserRepository`, exposed via `$fake->users()`) is a plain in-memory array of `FakeUser` objects — it is **not** a stand-in for your project's real `telegram_users` table. `FakeUser` supports `step()`/`temp()`/`setLocale()`-style flows and has a surrogate `id`, but if your own app has tables with foreign keys into `telegram_users.id` (orders, subscriptions, etc.), your app's real Eloquent queries against those tables will not see anything `Bot::fake()` creates — there's no database row to join against.

For that case, test against a real (ideally disposable/test) database instead: keep the fake HTTP client so no real Telegram calls go out, but compose `BotInstance` directly with `Api\FakeHttpClient` and the real `Database\UserRepository`, so `$ctx->user()` returns actual Eloquent models backed by your test database:

```php
use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Database\UserRepository;
use Devflow\TelegramBot\Testing\UpdateFactory;

// Point Capsule at a real test database before this, same as bootstrap/app.php.

$config = ['database' => true];
$http = new FakeHttpClient();
$instance = new BotInstance('fake-token', $config, $http);

// Register routes on this specific instance rather than through Bot::init()/
// Bot::fake() — a hand-built BotInstance isn't the static Bot:: facade's
// active instance, so handler classes that internally call Bot::onCommand()
// wouldn't reach it. Register closures against $instance directly instead:
$instance->onCommand('start', function (Context $ctx) {
    $ctx->reply('Hello, ' . $ctx->from()?->firstName . '! Your DB id is ' . $ctx->user()?->id);
});

$userRepository = new UserRepository($config);

$instance->router()->dispatch(
    UpdateFactory::command('start'),
    $instance->api(),
    $config,
    $userRepository,
);

// $http->calls() / $http->callsTo('sendMessage') gives you the same raw data
// FakeBot's assertSent()/assertNotSent() build on — inspect it directly, or
// wrap this composition in your own thin test helper.
$sent = $http->callsTo('sendMessage');
```

This gets you a real `TelegramUser` row (visible to your app's own queries and foreign keys) while still avoiding any real Telegram API calls.

---

## Option A: ngrok (for testing real webhook delivery)

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

## SSL certificate errors (Windows)

If `devflow poll`, `broadcast:run`, or any live API call fails with:

```
cURL error 60: SSL certificate problem: self-signed certificate in certificate chain
```

this is almost always **not** a man-in-the-middle proxy problem — it's PHP's `curl` extension on Windows having no CA bundle configured at all. Native `curl.exe` uses Windows' own Schannel + the OS trusted-certificate store, but PHP's `curl` extension links against OpenSSL, which never looks at the Windows store. If `curl.cainfo` / `openssl.cafile` are empty in `php.ini`, PHP rejects **any** valid HTTPS certificate as untrusted — including Telegram's real one — whether or not you're behind a proxy (`PROXY_URL`).

### Confirm it's this issue

```powershell
php -i | findstr "cainfo cafile"
```

If both show `no value`, that's the cause.

### Fix (machine-local — no code or project change needed)

1. Download the public Mozilla CA bundle: https://curl.se/ca/cacert.pem — save it somewhere permanent, e.g. `C:\php\cacert.pem`
2. Open your `php.ini` (find its path with `php --ini`) and set:
   ```ini
   [curl]
   curl.cainfo = "C:\php\cacert.pem"

   [openssl]
   openssl.cafile = "C:\php\cacert.pem"
   ```
3. Restart Apache/your web server (webhook mode) or just re-run the CLI command (`devflow poll`).

### Verify

```powershell
php -r "var_dump(curl_exec(curl_init('https://api.telegram.org/')) !== false);"
```

Should print `bool(true)`.

### If you're behind a proxy (PROXY_URL / countries where Telegram is blocked)

This same fix covers the proxy case too, as long as your proxy is a plain relay (most SOCKS5/HTTP proxies are). Genuine TLS interception (a proxy that decrypts and re-signs traffic with its own certificate) is rare — you can tell the two apart by running, from a normal terminal:

```powershell
curl -v -x http://127.0.0.1:<port> https://api.telegram.org/
```

and checking the `issuer:`/`subject:` line. If it names a real public CA (DigiCert, GoDaddy, ISRG/Let's Encrypt, Google Trust Services, etc.), you just need the CA bundle fix above. If it names your proxy software itself, that proxy is intercepting traffic — either disable its HTTPS decryption/sniffing feature, or export its root certificate and append it to the `cacert.pem` from step 1.

---

## Stopping the bot

To stop Telegram from sending requests (e.g. while you're rebuilding):

```php
Bot::init(env('BOT_TOKEN'));
Bot::deleteWebhook();
```

Or just stop ngrok — Telegram will queue retries for a while then give up.
