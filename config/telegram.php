<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot Token
    |--------------------------------------------------------------------------
    | Your Telegram bot token from @BotFather.
    */
    'token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Optional secret token sent by Telegram in the X-Telegram-Bot-Api-Secret-Token
    | header to verify that the request genuinely came from Telegram.
    */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Database Integration
    |--------------------------------------------------------------------------
    | When enabled, the library automatically upserts telegram_users on every
    | update and wires up $ctx->user(), $ctx->step(), $ctx->setStep(), etc.
    */
    'database' => env('TELEGRAM_DATABASE', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    | The URI that Telegram will POST updates to. Set to null to disable
    | auto-registration (manage the route yourself in routes/api.php).
    */
    'webhook_route' => env('TELEGRAM_WEBHOOK_ROUTE', 'telegram/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Middleware
    |--------------------------------------------------------------------------
    | The webhook route is registered outside the "web"/"api" groups, so it has
    | no CSRF check (Telegram can't send a token) and no throttling. Add
    | middleware here, e.g. ['throttle:600,1'], by name or class string.
    */
    'webhook_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Chat Types
    |--------------------------------------------------------------------------
    | Restrict which chats the bot answers: ['private'], ['group', 'supergroup'],
    | ... null = no filtering. Set ['private'] unless the bot is meant for groups.
    */
    'allowed_chat_types' => null,

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    | Directory of {locale}.php files used by $ctx->t(), and the fallback locale.
    | e.g. 'lang_path' => lang_path('telegram')
    */
    'lang_path' => null,
    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | Your Eloquent model extending Devflow\TelegramBot\Database\Models\TelegramUser,
    | returned by $ctx->user(). null = the bundled TelegramUser.
    */
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    | 'proxy' routes API calls through a proxy (http://user:pass@host:port).
    | A 429 is retried up to 'max_retries' times, honouring Telegram's
    | retry_after; 'retry_transient' extends that to 5xx / network failures.
    */
    'proxy' => env('TELEGRAM_PROXY'),
    'max_retries' => 2,
    'retry_transient' => false,
];
