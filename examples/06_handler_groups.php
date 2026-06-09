<?php

/**
 * Handler groups — split bot logic across multiple files.
 *
 * Demonstrates:
 *  - Handler group classes with a static register() method
 *  - Bot::loadHandlers() to load multiple groups at once
 *  - Splitting by user type (normal vs admin)
 *  - Splitting complex flows into a dedicated group
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

Bot::init('YOUR_BOT_TOKEN', ['database' => true]);

// ---- Handler group: normal user commands ------------------------------------

class UserHandlers
{
    public static function register(): void
    {
        Bot::onCommand('start', function (Context $ctx) {
            $name = $ctx->from()?->firstName ?? 'there';
            $ctx->reply("Hello, {$name}! Use /help to see what I can do.");
        });

        Bot::onCommand('help', function (Context $ctx) {
            $ctx->reply("/start — Start the bot\n/help — This message");
        });

        // Catch-all text fallback — echoes the user's message back
        Bot::onText(function (Context $ctx) {
            $ctx->reply('You said: ' . $ctx->text());
        });
    }
}

// ---- Handler group: admin commands ------------------------------------------

class AdminHandlers
{
    public static function register(): void
    {
        Bot::onCommand('stats', function (Context $ctx) {
            if (!$ctx->user()?->isAdmin()) {
                return; // silently ignore non-admins
            }

            $ctx->reply('Bot stats: all systems normal.');
        });

        Bot::onCommand('ban', function (Context $ctx) {
            if (!$ctx->user()?->isAdmin()) {
                return;
            }

            $args   = $ctx->message()->commandArgs();
            $userId = (int) ($args[0] ?? 0);

            if (!$userId) {
                $ctx->reply('Usage: /ban <user_id>');
                return;
            }

            \Devflow\TelegramBot\Database\Models\TelegramUser::where('telegram_id', $userId)
                ->first()
                ?->ban('Banned by admin');

            $ctx->reply("User {$userId} has been banned.");
        });
    }
}

// ---- Handler group: a multi-step flow split into its own file ---------------

class RegistrationHandlers
{
    public static function register(): void
    {
        Bot::onCommand('register', function (Context $ctx) {
            $ctx->setStep('register.ask_name');
            $ctx->reply("Let's get you registered.\n\nWhat is your full name?");
        });

        Bot::onStep('register.ask_name', function (Context $ctx) {
            $ctx->setTemp('name', $ctx->text());
            $ctx->setStep('register.ask_email');
            $ctx->reply('What is your email address?');
        });

        Bot::onStep('register.ask_email', function (Context $ctx) {
            if (!\Devflow\TelegramBot\Support\Input::isEmail($ctx->text())) {
                $ctx->reply('That does not look like a valid email. Try again:');
                return;
            }

            $ctx->setTemp('email', $ctx->text());
            $ctx->clearFlow();

            $name  = $ctx->temp('name');
            $email = $ctx->temp('email');
            $ctx->reply("Registration complete!\nName: {$name}\nEmail: {$email}");
        });
    }
}

// ---- Load all groups --------------------------------------------------------

// loadHandlers() calls ::register() on each class in order.
// Routes are checked in registration order — first match wins.
Bot::loadHandlers([
    RegistrationHandlers::class, // step routes registered first
    UserHandlers::class,
    AdminHandlers::class,
]);

Bot::run();
