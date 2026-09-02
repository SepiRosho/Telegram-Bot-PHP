# 15 — Errors & Reliability

Most Telegram errors are not bugs. A user blocking your bot, a button pressed a minute too late, an
edit that changed nothing — Telegram reports all of these the same way it reports a genuinely
broken request, and treating them alike is how a bot ends up retrying something that can never work.

---

## The one rule

**Never match on the error text yourself.** `TelegramApiException` classifies itself:

```php
use Devflow\TelegramBot\Exceptions\TelegramApiException;

try {
    $ctx->reply('Hello!');
} catch (TelegramApiException $e) {
    if ($e->isChatUnavailable()) {
        // They blocked the bot or deleted their account. Stop sending.
        $ctx->user()?->update(['is_active' => false]);
        return;
    }

    throw $e;
}
```

| Method | Means | What to do |
|---|---|---|
| `isChatUnavailable()` | Blocked, deactivated, bot kicked, chat not found | Stop sending. Mark inactive. |
| `isPermissionDenied()` | The bot is in the chat but can't post | Grant rights — the user isn't gone |
| `isIgnorable()` | No-op edit, stale callback query, message already deleted | Swallow it |
| `isRateLimited()` | 429 | Wait `retryAfter()` seconds |
| `isTransient()` | 5xx or a network failure | Retry later — or set `retry_transient` to have the library do it |
| `isExpected()` | Any of the above | Don't log it as a crash |

`isChatUnavailable()` and `isPermissionDenied()` are mutually exclusive on purpose. "Not enough
rights to post in this group" is not the same as "this person is gone", and deactivating a user
over it would quietly shrink your audience for a reason that has nothing to do with them.

The exception also exposes the raw details when you need them: `telegramErrorCode()`,
`parameters()`, `retryAfter()`, and `migrateToChatId()` (set when a group becomes a supergroup —
resend to the new id).

---

## What the library handles for you

### Polling never gets stuck on one bad update

`getUpdates` returns everything above the last offset you confirmed. The offset therefore has to
advance **before** the update is handled, not after — otherwise a handler that throws leaves the
offset where it was, and Telegram hands back the identical update on the next poll, for ever.

That's what this does:

```
✗ Forbidden: bot was blocked by the user
  Retrying in 5 seconds...
✗ Forbidden: bot was blocked by the user
  Retrying in 5 seconds...          ← the same update, not a new one
```

Fixed in v1.12.0. A handler that throws is now reported once and the loop moves on.

### Fetch failures back off

Consecutive `getUpdates` failures wait 1s, 2s, 5s, 10s, 30s, then 60s — long enough not to hammer a
dead network, short enough to notice when it comes back. If Telegram sends a `retry_after`, that
wins over the schedule.

### Some errors stop polling instead of looping

`401` (token rejected), `404` (unknown bot) and `409` (a webhook is still registered) will never fix
themselves. Polling stops and tells you what to do rather than reprinting the same line for ever:

```
✗ Conflict: can't use getUpdates method while webhook is active

  A webhook is still registered — Telegram won't serve getUpdates while one is set.
  Remove it first: vendor/bin/devflow webhook:delete
```

### Webhook dispatch absorbs expected errors

A scaffolded project alerts `ADMIN_CHAT_ID` on any uncaught exception. Without filtering, every user
who blocks the bot pages you about it. Expected errors are logged at `WARNING` and dropped; anything
else still reaches your handler.

### Broadcasts prune themselves

`broadcast:run` sets `is_active = 0` on any recipient that reports `isChatUnavailable()`:

```
✓ Broadcast #4 complete — sent: 1,203, failed: 47 (47 unreachable, marked inactive) / 1,250
```

Without this, a blocked user stays in the recipient set for ever and every future broadcast pays
again for a send that cannot succeed. `/start` sets the flag back to `true` if they return.

---

## Skipping the backlog

Telegram queues updates while your bot is offline. Restart after an hour and you'll answer an hour
of stale messages all at once — usually the wrong thing during development.

```bash
vendor/bin/devflow poll --drop-pending
```

This is the polling equivalent of `setWebhook`'s `drop_pending_updates`. It costs one extra API call
at startup and leaves any registered webhook alone.

In your own code:

```php
Bot::poll(dropPending: true);
```

---

## Other exceptions

| Exception | Cause |
|---|---|
| `MissingTokenException` | `BOT_TOKEN` is empty — check `.env` |
| `WebhookException` | Bad secret token, empty body, or malformed JSON |
| `BotNotInitializedException` | `Bot::init()` was never called |

---

## Next step

[README.md](README.md) — back to the guide index. When something is wrong and you don't know what,
start with `vendor/bin/devflow doctor`.
