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
exactly the `retry_after` Telegram asked for.

```php
Bot::init(env('BOT_TOKEN'), [
    'max_retries'     => 2,    // default; 0 disables retrying
    'max_retry_after' => 60,   // default; longer waits throw instead of blocking
]);
```

Two deliberate limits:

- **Waits longer than `max_retry_after` are not slept through.** A webhook request holding a
  connection open for five minutes is worse than a failed send.
- **Uploads are never retried.** An `InputFile` opened as a stream is consumed by the first
  attempt, so replaying the request would send an empty body. The 429 surfaces to you instead.

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
