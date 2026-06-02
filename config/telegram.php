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
];
