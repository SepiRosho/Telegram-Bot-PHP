# 04 — The Context Object

Every handler receives one argument: `$ctx`. It is your window into the current update — who sent the message, what they said, and how to reply.

```php
Bot::onText(function (Context $ctx) {
    // $ctx is available here
});
```

---

## Reading the incoming message

```php
$ctx->text()          // The text the user sent. null if no text (e.g. a photo).
$ctx->chatId()        // The chat ID — where to send replies.
$ctx->userId()        // The Telegram user ID of the sender.
$ctx->callbackData()  // The data from an inline button press. null otherwise.
$ctx->message()       // The full Message object (see below).
$ctx->callbackQuery() // The full CallbackQuery object. null if not a callback.
$ctx->update()        // The raw Update object — everything Telegram sent.
```

---

## Sending responses

```php
// Send a text reply to the current chat
$ctx->reply('Hello!');

// With formatting
$ctx->reply('<b>Bold</b> and <i>italic</i>', ['parse_mode' => 'HTML']);

// Send a photo
$ctx->replyWithPhoto('https://example.com/photo.jpg');
$ctx->replyWithPhoto('file_id_from_telegram', ['caption' => 'My photo']);

// Send a file
$ctx->replyWithDocument('file_id_or_url');

// Answer an inline button press (the little "loading" spinner on the button)
$ctx->answerCallback();                    // dismiss spinner silently
$ctx->answerCallback('Done!');             // show a toast notification
$ctx->answerCallback('Error!', showAlert: true); // show a blocking popup

// Show "typing..." indicator
$ctx->sendChatAction('typing');
$ctx->sendChatAction('upload_photo');
```

---

## `$ctx->from()` vs `$ctx->user()`

This is one of the most confusing parts of the library. Here's the clear explanation:

### `$ctx->from()` — Telegram data, always available

```php
$from = $ctx->from();

$from->id            // Telegram user ID (integer)
$from->firstName     // First name (camelCase)
$from->lastName      // Last name or null
$from->username      // @username or null
$from->languageCode  // 'en', 'fa', etc. or null
```

This comes directly from Telegram with every message. It is **not stored in your database**. It is always available (even without a database). Note the camelCase property names.

### `$ctx->user()` — Your database record, requires DB setup

```php
$user = $ctx->user(); // returns a TelegramUser Eloquent model, or null

$user->first_name      // snake_case — Eloquent model convention
$user->step            // current wizard step
$user->temp_data       // stored temporary data (array)
$user->role            // 'user', 'admin', 'superadmin'
$user->is_banned       // true/false
```

This is a row in your `telegram_users` database table. It is **only available after you set up the database** (see [06-database.md](06-database.md)) and uncomment the DB block in `bootstrap/app.php`.

If the database is not set up, `$ctx->user()` always returns `null`.

### Quick comparison

| | `$ctx->from()` | `$ctx->user()` |
|---|---|---|
| Source | Telegram (live) | Your database |
| Always available | Yes | Only with DB |
| Property style | camelCase | snake_case |
| Contains | Name, username, language | Name + role, step, temp data, ban status |
| Use for | Greeting the user, checking language | Checking bans, reading flow state, admin checks |

### Safe pattern when DB may or may not be active

```php
// Get the name from DB if available, fall back to Telegram data
$name = $ctx->user()?->first_name ?? $ctx->from()?->firstName ?? 'there';
```

The `?->` operator means "if this is null, skip and return null" — so it won't crash if the user isn't in the database yet.

---

## The Message object

`$ctx->message()` gives you the full message:

```php
$msg = $ctx->message();

$msg->messageId   // The message ID (useful for editing/deleting it)
$msg->text        // The text (same as $ctx->text())
$msg->from        // The sender (same as $ctx->from())
$msg->chat        // The chat object
$msg->chat->id    // The chat ID (same as $ctx->chatId())
$msg->photo       // Array of photo sizes, or null
$msg->document    // Document object, or null
$msg->isCommand() // true if the message is a /command
$msg->command()   // 'start' (without the slash)
$msg->commandArgs() // ['arg1', 'arg2'] from '/command arg1 arg2'
```

---

## Calling the full Telegram API from a handler

The `Bot::` facade works anywhere, including inside handlers:

```php
Bot::onCommand('pin', function (Context $ctx) {
    $messageId = $ctx->message()->messageId;
    Bot::pinChatMessage($ctx->chatId(), $messageId);
    $ctx->reply('Message pinned.');
});
```

---

## Next step

[05-middleware.md](05-middleware.md) — Run code before every handler (ban checks, logging, admin guards).
