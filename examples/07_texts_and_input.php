<?php

/**
 * Localized text classes and input helpers.
 *
 * Demonstrates:
 *  - BotText: per-message translation classes with {variable} placeholders
 *  - BotText::forContext() for automatic language detection
 *  - Input: validation and sanitization helpers
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\BotText;
use Devflow\TelegramBot\Support\Input;

Bot::init('YOUR_BOT_TOKEN', ['database' => true]);

// ---- Text classes -----------------------------------------------------------
// Each class extends BotText and returns one translation per language.
// Variables like {name} are substituted at call time.

class WelcomeText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => "Hello, {name}!\n\nWelcome to the bot. Use /help to see what I can do.",
            'fa' => "سلام، {name}!\n\nبه ربات خوش آمدید. برای راهنمایی /help را ارسال کنید.",
            'de' => "Hallo, {name}!\n\nWillkommen beim Bot. Tippe /help für Hilfe.",
            'ru' => "Привет, {name}!\n\nДобро пожаловать. Используй /help для помощи.",
        ];
    }
}

class AgeErrorText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => 'Age must be between {min} and {max}. Try again:',
            'fa' => 'سن باید بین {min} و {max} باشد. دوباره وارد کنید:',
        ];
    }
}

class RegistrationDoneText extends BotText
{
    protected static function translations(): array
    {
        return [
            'en' => "Done!\nName: {name}\nAge: {age}\nEmail: {email}",
            'fa' => "کامل شد!\nنام: {name}\nسن: {age}\nایمیل: {email}",
        ];
    }
}

// ---- Handlers ---------------------------------------------------------------

Bot::onCommand('start', function (Context $ctx) {
    $name = $ctx->from()?->firstName ?? 'there';

    // forContext() reads the user's language from Telegram, falls back to 'en'
    $ctx->reply(WelcomeText::forContext($ctx, ['name' => $name]));
});

// A multi-step registration flow that validates each input

Bot::onCommand('register', function (Context $ctx) {
    $ctx->setStep('reg.name');
    $ctx->reply('What is your full name?');
});

Bot::onStep('reg.name', function (Context $ctx) {
    $name = Input::clean($ctx->text());   // trims + strips tags
    $name = Input::truncate($name, 100);  // cap at 100 chars

    if (!Input::minLength($name, 2)) {
        $ctx->reply('Name is too short. Please enter at least 2 characters:');
        return;
    }

    $ctx->setTemp('name', $name);
    $ctx->setStep('reg.age');
    $ctx->reply('How old are you?');
});

Bot::onStep('reg.age', function (Context $ctx) {
    if (!Input::isInt($ctx->text())) {
        $ctx->reply(AgeErrorText::forContext($ctx, ['min' => 13, 'max' => 120]));
        return;
    }

    $age = Input::toInt($ctx->text());

    if (!Input::between($age, 13, 120)) {
        $ctx->reply(AgeErrorText::forContext($ctx, ['min' => 13, 'max' => 120]));
        return;
    }

    $ctx->setTemp('age', $age);
    $ctx->setStep('reg.email');
    $ctx->reply('What is your email address?');
});

Bot::onStep('reg.email', function (Context $ctx) {
    if (!Input::isEmail($ctx->text())) {
        $ctx->reply('That does not look like a valid email. Try again:');
        return;
    }

    $ctx->setTemp('email', Input::clean($ctx->text()));
    $ctx->clearFlow();

    $ctx->reply(RegistrationDoneText::forContext($ctx, [
        'name'  => $ctx->temp('name'),
        'age'   => $ctx->temp('age'),
        'email' => $ctx->temp('email'),
    ]));
});

// Manual language selection example:
Bot::onCommand('hello_fa', function (Context $ctx) {
    $name = $ctx->from()?->firstName ?? 'دوست';
    $ctx->reply(WelcomeText::get(['name' => $name], 'fa'));
});

Bot::run();
