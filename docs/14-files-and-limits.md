# 14 — Sending Files & Handling Rate Limits

## Sending a local file

Telegram's `photo`, `document`, `video`, `audio`, `voice`, `sticker` and `video_note` parameters
accept three things: a `file_id` of something already on Telegram's servers, a public URL, or an
actual file upload.

A plain string is treated as the first two. To upload a file from your server, wrap it:

```php
use Devflow\TelegramBot\Api\InputFile;

// From disk
$ctx->replyWithDocument(InputFile::path('/var/www/invoices/2026-07.pdf'));

// With a nicer filename than the one on disk
$ctx->replyWithDocument(InputFile::path($tmpPath, 'Invoice July 2026.pdf'));

// Generated in memory — a rendered chart, a CSV export, a QR code
$ctx->replyWithPhoto(InputFile::contents($pngBytes, 'chart.png'));
```

This works anywhere a file parameter is accepted:

```php
Bot::sendDocument($chatId, InputFile::path($path), ['caption' => 'Your report']);
$ctx->api()->sendVideo($chatId, InputFile::path($path), ['supports_streaming' => true]);
```

`InputFile::path()` throws `InvalidArgumentException` immediately if the file is missing or
unreadable, rather than failing later inside the HTTP layer. Files opened from a path are streamed,
not read into memory.

### The array rule still holds

Switching to an upload changes the request from a JSON body to a multipart one, where every field
must be a flat string. The library handles that conversion for you — **keep passing plain PHP
arrays**:

```php
$ctx->replyWithPhoto(InputFile::path($path), [
    'caption'      => 'Pick one:',
    'reply_markup' => Keyboard::inline([[Keyboard::button('OK', 'ok')]]),  // still an array
]);
```

Never call `json_encode()` yourself, on either path.

## Rate limits (HTTP 429)

Telegram allows roughly 30 messages per second overall, and about one per second to any given chat.
Exceed that and it replies with error 429 and a `retry_after` telling you how many seconds to wait.

The library honours that automatically: a 429 is retried up to `max_retries` times, sleeping for
the `retry_after` Telegram asked for — or, if the response didn't include one, an exponential
backoff (1s, 2s, 4s, …) instead. **Uploads are retried too**: `InputFile::open()` returns a fresh
handle or string on every attempt, so a retry sends a new body rather than replaying an already-
consumed stream.

```php
Bot::init(env('BOT_TOKEN'), [
    'max_retries'     => 2,    // default; 0 disables retrying
    'max_retry_after' => 60,   // default; longer waits throw instead of blocking
]);
```

One deliberate limit: **waits longer than `max_retry_after` are not slept through.** A webhook
request holding a connection open for five minutes is worse than a failed send — it throws instead,
and `retryAfter()` on the caught exception says how long Telegram actually wanted.

### Retrying 5xx and network failures too

429 is the only thing retried by default — a 5xx or a network hiccup surfaces immediately as
`TelegramApiException` with `isTransient()` true, for you to handle (see
[15-errors.md](15-errors.md)). Set `retry_transient` to fold those into the same retry loop instead,
using the same exponential backoff a 429 without a `retry_after` falls back to:

```php
Bot::init(env('BOT_TOKEN'), ['retry_transient' => true]);
```

Off by default because it changes what used to be an immediate throw into a wait — worth opting into
deliberately rather than as a side effect of upgrading.

### Customizing the backoff

Three hooks, none required:

```php
Bot::init(env('BOT_TOKEN'), [
    // Extra random jitter on top of the computed wait, as a fraction of it.
    // 0.1 = up to +10% — smooths out several workers retrying on the exact
    // same second after they all got rate-limited together.
    'retry_jitter' => 0.1,

    // Overrides the wait entirely when set. Runs before jitter, so pick one
    // or the other rather than stacking your own jitter on top of this.
    'retry_strategy' => function (int $attempt, int $baseWaitSeconds, TelegramApiException $e): int {
        return min(30, $baseWaitSeconds * 2);
    },

    // Pure observer, fired right before sleeping — logging/metrics only,
    // it cannot change the wait.
    'on_retry' => function (int $attempt, int $waitSeconds, string $method, TelegramApiException $e) {
        saveLog("Retrying {$method} in {$waitSeconds}s (attempt {$attempt})", 'WARNING');
    },
]);
```

### The wait itself is still a blocking `sleep()` by default

This library is synchronous — a long wait blocks whatever process hit it (a webhook worker, the
`poll` loop, `broadcast:run`). `max_retry_after` bounds how bad that gets, and `sleeper` lets you
replace the actual wait mechanism if your runtime can do better than blocking:

```php
Bot::init(env('BOT_TOKEN'), [
    'sleeper' => function (int $seconds) {
        \Fiber::suspend(); // or a ReactPHP/Swoole timer — whatever your event loop provides
    },
]);
```

This does not make the library asynchronous end to end — it only makes the one blocking call inside
the retry loop swappable, for callers whose runtime already supports suspending instead of blocking.

### Handling one that still gets through

```php
use Devflow\TelegramBot\Exceptions\TelegramApiException;

try {
    $ctx->reply('Hello');
} catch (TelegramApiException $e) {
    $e->telegramErrorCode();   // 429
    $e->retryAfter();          // int|null — seconds Telegram asked for
    $e->migrateToChatId();     // int|null — set when a group became a supergroup
    $e->parameters();          // the raw `parameters` object
}
```

`migrateToChatId()` covers a separate case worth knowing about: when a group is upgraded to a
supergroup its chat id changes, and Telegram returns the new one in the error. Update your stored
`chat_id` and resend.

## Broadcasts

`vendor/bin/devflow broadcast:run` paces itself with `BROADCAST_RATE` in `.env` (default 25/sec,
Telegram's ceiling is 30). Automatic 429 handling is a safety net beneath that, not a replacement —
keep the rate under the limit rather than relying on retries.
