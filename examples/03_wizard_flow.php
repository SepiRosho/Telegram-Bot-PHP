<?php

/**
 * Multi-step wizard flow using step + temp_data on the users table.
 *
 * Demonstrates:
 *  - $ctx->setStep() / $ctx->step() for tracking where a user is
 *  - $ctx->setTemp() / $ctx->temp() for storing intermediate answers
 *  - $ctx->clearFlow() for resetting state when done
 *
 * Requires DB to be configured (see standalone setup below).
 *
 * Standalone DB setup (without Laravel):
 *   use Illuminate\Database\Capsule\Manager as Capsule;
 *   $capsule = new Capsule;
 *   $capsule->addConnection([
 *       'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'my_bot',
 *       'username' => 'root', 'password' => '', 'charset' => 'utf8mb4',
 *   ]);
 *   $capsule->setAsGlobal();
 *   $capsule->bootEloquent();
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

Bot::init('YOUR_BOT_TOKEN');

// ---- Step entry points ------------------------------------------------------

Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('register.ask_name');
    $ctx->reply('Let\'s get you registered! 📝\n\nWhat\'s your full name?');
});

Bot::onCommand('cancel', function (Context $ctx) {
    $ctx->clearFlow();
    $ctx->reply('Registration cancelled. Use /register to start again.');
});

// ---- Step routing via onText ------------------------------------------------

Bot::onText(function (Context $ctx) {
    match ($ctx->step()) {
        'register.ask_name' => handleAskName($ctx),
        'register.ask_age'  => handleAskAge($ctx),
        'register.confirm'  => handleConfirm($ctx),
        default             => $ctx->reply('Use /register to start the registration flow.'),
    };
});

// ---- Step handlers ----------------------------------------------------------

function handleAskName(Context $ctx): void
{
    $name = trim($ctx->text());

    if (strlen($name) < 2) {
        $ctx->reply('Name is too short. Please enter your full name:');
        return;
    }

    $ctx->setTemp('name', $name);
    $ctx->setStep('register.ask_age');
    $ctx->reply("Nice to meet you, {$name}! How old are you?");
}

function handleAskAge(Context $ctx): void
{
    $age = (int) $ctx->text();

    if ($age < 13 || $age > 120) {
        $ctx->reply('Please enter a valid age (13–120):');
        return;
    }

    $ctx->setTemp('age', $age);
    $ctx->setStep('register.confirm');

    $name = $ctx->temp('name');
    $ctx->reply(
        "Almost done! Please confirm your details:\n\n" .
        "👤 Name: {$name}\n🎂 Age: {$age}\n\n" .
        "Type *yes* to confirm or *no* to cancel.",
        ['parse_mode' => 'Markdown'],
    );
}

function handleConfirm(Context $ctx): void
{
    $answer = strtolower(trim($ctx->text()));

    if ($answer === 'yes') {
        $name = $ctx->temp('name');
        $age  = $ctx->temp('age');

        // TODO: save to your application table
        // User::create(['name' => $name, 'age' => $age, 'telegram_id' => $ctx->userId()]);

        $ctx->clearFlow();
        $ctx->reply("✅ You're registered, {$name}! Welcome aboard.");
    } elseif ($answer === 'no') {
        $ctx->clearFlow();
        $ctx->reply('Registration cancelled. Use /register to start again.');
    } else {
        $ctx->reply('Please type *yes* or *no*.', ['parse_mode' => 'Markdown']);
    }
}

Bot::run();
