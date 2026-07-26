# 06 — Database Setup

The database is **optional**. Your bot works without it. You only need it if you want:

- `$ctx->user()` — look up the current user in your database
- `$ctx->step()` / `$ctx->setStep()` — multi-step wizard flows
- `$ctx->temp()` / `$ctx->setTemp()` — store temporary data across messages
- Banning users
- Tracking last activity
- Referral codes

---

## The three tables

| Table | Purpose |
|---|---|
| `telegram_users` | One row per user. Stores name, username, role, ban status, wizard step, temp data, referral code, and rate-limit hit timestamps. |
| `bot_settings` | Key/value store for bot configuration (welcome message, feature flags, etc.) |
| `telegram_broadcasts` | Queue for bulk messages sent to all users. |

---

## Step 1: Create the database

### Option A — phpMyAdmin (Windows/XAMPP)

1. Open `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Enter `my_bot` as the database name, select `utf8mb4_unicode_ci`, click **Create**

### Option B — Command line (Linux/Mac)

```bash
mysql -u root -p -e "CREATE DATABASE my_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## Step 2: Run the migrations

```bash
vendor/bin/devflow migrate
```

This creates the three base tables (`telegram_users`, `bot_settings`, `telegram_broadcasts`) from migrations bundled with the library, plus anything you've added under your own project's `database/migrations/` (see below). It's safe to re-run — already-applied migrations are tracked in a `migrations` table and skipped. `vendor/bin/devflow migrate:status` shows what has and hasn't run yet.

### Adding your own tables/columns

Drop a new PHP file into `database/migrations/`, named so it sorts after the bundled ones (e.g. `2026_01_01_000000_add_something_to_telegram_users.php`):

```php
<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return new class {
    public function up(): void
    {
        Capsule::schema()->table('telegram_users', function ($table) {
            $table->string('anon_token')->nullable();
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('telegram_users', function ($table) {
            $table->dropColumn('anon_token');
        });
    }
};
```

Then run `vendor/bin/devflow migrate` again.

### Legacy: importing the raw SQL by hand

If you'd rather not use the migration runner, the equivalent `.sql` files still ship under `vendor/devflow/telegram-bot/database/migrations/*.sql` and can be imported directly (phpMyAdmin's Import tab, or `mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_users.sql`). Note this path won't track schema changes across library upgrades the way `devflow migrate` does.

> **Warning:** Tables created this way are invisible to the migration runner's tracking table (`migrations`) — it has no record that they were ever applied. `vendor/bin/devflow migrate:status` will report the bundled migrations as pending/untracked even though the tables already exist, and running `vendor/bin/devflow migrate` afterward can then try to create tables that are already there. If you mix the two approaches (or aren't sure which one a given environment used), write your own migrations defensively — guard with `hasTable()`/`hasColumn()` before creating, the way the bundled migrations already do:
>
> ```php
> use Illuminate\Database\Capsule\Manager as Capsule;
>
> return new class {
>     public function up(): void
>     {
>         if (Capsule::schema()->hasTable('telegram_users')) {
>             return;
>         }
>
>         Capsule::schema()->create('telegram_users', function ($table) {
>             // ...
>         });
>     }
> };
> ```
>
> The safest fix is to pick one path per environment and stick with it — prefer `devflow migrate` going forward, and only use the raw `.sql` import for a fresh database that has never seen either.

---

## Step 3: Fill in your .env credentials

```
BOT_DATABASE=true

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_bot
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

For XAMPP, the default MySQL user is `root` with an empty password.

---

## Step 4: Uncomment the DB block in bootstrap/app.php

Open `bootstrap/app.php`. Find the commented database block and uncomment it:

```php
// BEFORE (commented out):
// use Illuminate\Database\Capsule\Manager as Capsule;
//
// $capsule = new Capsule;
// $capsule->addConnection([...]);
// $capsule->setAsGlobal();
// $capsule->bootEloquent();

// AFTER (active):
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_DRIVER', 'mysql'),
    'host'      => env('DB_HOST', '127.0.0.1'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_DATABASE', 'my_bot'),
    'username'  => env('DB_USERNAME', 'root'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

Also update `Bot::init()` to enable the database:

```php
// Change this:
Bot::init(env('BOT_TOKEN'));

// To this:
Bot::init(env('BOT_TOKEN'), ['database' => true]);
```

And uncomment AuthMiddleware:

```php
Bot::use(\App\Middleware\AuthMiddleware::class);
```

---

## Step 5: Verify it works

Add a temporary test to your `/start` handler:

```php
public function handle(Context $ctx): void
{
    $user = $ctx->user();

    if ($user === null) {
        $ctx->reply('DB not working yet.');
        return;
    }

    $ctx->reply("Hello! Your DB ID is: {$user->id}, joined: {$user->joined_at}");
}
```

Send `/start` to your bot. If you see the DB ID, everything is wired up correctly.

---

## Working with the TelegramUser model

Once the database is active, `$ctx->user()` returns a full Eloquent model:

```php
$user = $ctx->user();

// Read data
$user->first_name       // Telegram first name
$user->username         // @username or null
$user->role             // 'user', 'admin', or 'superadmin'
$user->is_banned        // true or false
$user->step             // current wizard step (null if not in a flow)

// Check role
$user->isAdmin()        // true if role is 'admin' or 'superadmin'
$user->isSuperAdmin()   // true if role is 'superadmin'
$user->hasPermission('can_broadcast') // check a specific permission

// Moderation
$user->ban('Spamming');
$user->unban();

// Referrals
$user->referral_code    // the user's unique referral code
$user->inviter          // the TelegramUser who referred them (or null)
$user->referrals        // collection of users referred by this user
```

Only the `id` column is guarded — every other column, including custom ones your own migrations add, is mass-assignable via `TelegramUser::create([...])` or `$user->update([...])` with no extra config.

### Adding your own columns/relationships

`app/Models/User.php` (scaffolded by `devflow new`) extends `TelegramUser` and is wired in via the `user_model` config key in `bootstrap/app.php`:

```php
// app/Models/User.php
namespace App\Models;

use Devflow\TelegramBot\Database\Models\TelegramUser;

class User extends TelegramUser
{
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }
}
```

```php
// bootstrap/app.php
Bot::init(env('BOT_TOKEN'), [
    'database'   => true,
    'user_model' => \App\Models\User::class,
]);
```

Once configured, `$ctx->user()` and auto-registration on first `/start` both return instances of your subclass instead of the base `TelegramUser`.

### Seeding fields on first contact

Auto-registration runs on **every** update and only sets a fixed set of columns (name, username, language code). To seed additional fields — a role, a referral code, a joined-at timestamp — the moment a user is first seen, without an idempotent check inside `/start`, use the `user_defaults` config key:

```php
Bot::init(env('BOT_TOKEN'), [
    'database'       => true,
    'user_defaults'  => fn(\Devflow\TelegramBot\Types\Update $update): array => [
        'role'      => (string) $update->message?->from?->id === env('ADMIN_CHAT_ID') ? 'superadmin' : 'user',
        'joined_at' => date('Y-m-d H:i:s'),
    ],
]);
```

The callback only runs the first time a `telegram_id` is seen — it has no effect on returning users.

## Working with BotSetting

Store and retrieve bot-wide settings:

```php
use Devflow\TelegramBot\Database\Models\BotSetting;

// Save a setting
BotSetting::set('welcome_message', 'Hello {name}!');

// Read a setting (with a default)
$msg = BotSetting::get('welcome_message', 'Hello!');

// Delete a setting
BotSetting::forget('welcome_message');
```

## Sending broadcasts

Queue a broadcast row, then process the queue with `vendor/bin/devflow broadcast:run` (rate-limited via `BROADCAST_RATE` in `.env`, default 25 msg/s):

```php
use Devflow\TelegramBot\Database\Models\Broadcast;

// Plain text (the default `type`)
Broadcast::create(['message' => 'Hello everyone!']);

// Media — `media` is a file_id, `options` is passed straight through to the
// matching TelegramApi send*() method (e.g. `caption` for photo/video/etc)
Broadcast::create([
    'type'    => 'photo',
    'media'   => $fileId,
    'options' => ['caption' => 'Check this out!'],
]);

// Re-send (copy) an existing message to everyone, exactly as it appeared —
// works for any message type, not just the ones with a dedicated `type`
Broadcast::create([
    'type'    => 'copy',
    'options' => ['from_chat_id' => $adminChatId, 'message_id' => $messageId],
]);

// Get a summary message once the run finishes
Broadcast::create(['message' => 'Hello!', 'notify_chat_id' => $adminChatId]);
```

Supported `type` values: `text` (default), `photo`, `document`, `video`, `audio`, `voice`, `animation`, `copy`. Progress (`sent_count`/`failed_count`/`status`) is written back to the row as the run proceeds, so an admin panel can poll it live.

### Running broadcasts in production

`vendor/bin/devflow broadcast:run` processes whatever is currently `pending` and then exits — it isn't a daemon. In production, nobody is going to SSH in and run it by hand every time an admin queues a broadcast from inside Telegram, so schedule it instead. A cron entry that checks every minute is the simplest reliable setup:

```
* * * * * cd /path/to/bot && vendor/bin/devflow broadcast:run >> logs/broadcast.log 2>&1
```

Since the command exits immediately when there's nothing `pending`, running it every minute costs nothing when idle. If a broadcast is already `running` when cron fires again a minute later, the new invocation only picks up rows still `pending` — it won't double-send an in-progress one.

Control the send rate with the `BROADCAST_RATE` env var (messages/second, default 25, clamped to 1–30 to stay under Telegram's hard limit):

```
BROADCAST_RATE=20
```

On Windows (no cron), use **Task Scheduler** with an action equivalent to the cron line above, e.g. `php vendor\bin\devflow broadcast:run` with "Start in" set to your project root, on a 1-minute trigger.

---

## Next step

[07-flows.md](07-flows.md) — Build multi-step conversations (wizards) using step and temp data.
