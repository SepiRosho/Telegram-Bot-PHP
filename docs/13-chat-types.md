# 13 — Chat Types (private-only bots)

By default a Telegram bot responds anywhere it can see a message — a private chat, a group, a
supergroup, or a channel. Most bots only ever want private, one-to-one chats.

## Why this matters

It isn't just noise. A scaffolded bot's `/start` handler registers the user:

```php
User::create([
    'telegram_id' => $ctx->userId(),
    'chat_id'     => $ctx->chatId(),   // ← the chat the command arrived in
    // ...
]);
```

If someone adds your bot to a group and types `/start` there, `chat_id` becomes the **group's** id.
That row now:

- inflates your user count,
- receives every future broadcast, because `broadcast:run` sends to `chat_id`,
- and looks like a normal user in your admin panel.

## The fix

```php
Bot::init(env('BOT_TOKEN'), [
    'allowed_chat_types' => ['private'],
]);
```

Projects generated with `vendor/bin/devflow new` already have this line in `bootstrap/app.php`.

Valid values are any of `'private'`, `'group'`, `'supergroup'`, `'channel'`. Leave the key out
entirely (the library default) and no filtering happens at all — existing bots are unaffected by
this feature until they opt in.

## What is never filtered

Two categories always reach your handlers, regardless of the setting.

**1. Updates with no chat at all.** There is nothing to compare against:

`inline_query`, `chosen_inline_result`, `poll`, `poll_answer`, `shipping_query`,
`pre_checkout_query`, `business_connection`, `purchased_paid_media`.

**2. Route types that only ever fire outside a private chat.** Filtering these to `'private'` would
mean registering them could never do anything:

`channel_post`, `edited_channel_post`, `my_chat_member`, `chat_member`, `chat_join_request`,
`chat_boost`, `removed_chat_boost`, `message_reaction_count`.

`my_chat_member` being in that list is the important one — it is how your bot finds out it was
added somewhere, which is exactly what a private-only bot needs in order to leave again:

```php
Bot::onMyChatMember(function (Context $ctx) {
    $chat = $ctx->chat();

    if ($chat === null || $chat->isPrivate()) {
        return;
    }

    if ($ctx->update()->myChatMember?->userJoined()) {
        Bot::leaveChat($chat->id);
    }
});
```

The scaffold generates this handler in `app/Handlers/UserHandlers.php`. Delete it if you later want
the bot to stay in groups.

## Allowing specific routes in groups

A mostly-private bot can still expose a few group commands. Wrap their registration:

```php
Bot::chatTypes(['group', 'supergroup'], function () {
    Bot::onCommand('stats', function (Context $ctx) {
        $ctx->reply('Group stats…');
    });
});

// ['*'] means "any chat type"
Bot::chatTypes(['*'], function () {
    Bot::onCommand('ping', fn(Context $ctx) => $ctx->reply('pong'));
});
```

Routes registered inside the closure use the given list; everything outside falls back to the
global `allowed_chat_types`. The scope is restored afterwards, even if the closure throws.

## Checking the chat type inside a handler

```php
$ctx->chat();        // ?Chat — null when the update carries no chat
$ctx->chatType();    // 'private' | 'group' | 'supergroup' | 'channel' | null
$ctx->isPrivate();
$ctx->isGroup();     // true for both 'group' and 'supergroup'
$ctx->isChannel();
```

## Debugging

`vendor/bin/devflow routes` shows the effective chat types per route, marking the ones that were
widened past the default and the ones that are exempt:

```
#  TYPE            PATTERN     HANDLER                        CHATS
1  command         start       Closure @ UserHandlers.php:14  private
4  my_chat_member  *           Closure @ UserHandlers.php:66  any (exempt)
```

With `'debug' => true`, an update dropped by the filter says so explicitly in the log, instead of
looking identical to a missing handler:

```
No route matched update #42 (type: message) — chat type "supergroup" is not in allowed_chat_types [private]
```

`vendor/bin/devflow doctor` warns when `allowed_chat_types` is not set at all.
