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

## Step 2: Import the SQL migrations

The SQL files are inside the library, under `vendor/devflow/telegram-bot/database/migrations/`.

### Option A — phpMyAdmin

1. Select your `my_bot` database in the left sidebar
2. Click the **Import** tab at the top
3. Click **Choose File** and select `vendor/devflow/telegram-bot/database/migrations/telegram_users.sql`
4. Click **Go**
5. Repeat for `bot_settings.sql` and `telegram_broadcasts.sql`

### Option B — Command line

```bash
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_users.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/bot_settings.sql
mysql -u root -p my_bot < vendor/devflow/telegram-bot/database/migrations/telegram_broadcasts.sql
```

After this you should have three tables in your database.

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

---

## Next step

[07-flows.md](07-flows.md) — Build multi-step conversations (wizards) using step and temp data.
