<?php

/**
 * Basic bot example — /start command, text echo, fallback handler.
 *
 * Setup:
 *   1. composer require devflow/telegram-bot
 *   2. Set your webhook: Bot::init($token)->api()->setWebhook('https://yourdomain.com/webhook.php');
 *   3. Point your web server at this file.
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

Bot::init(token: 'YOUR_BOT_TOKEN');

// Register bot commands shown in Telegram's menu
Bot::setMyCommands([
    ['command' => 'start', 'description' => 'Start the bot'],
    ['command' => 'help',  'description' => 'Show help'],
]);

Bot::onCommand('start', function (Context $ctx) {
    $name = $ctx->from()?->firstName ?? 'there';
    $ctx->reply("Hello, {$name}! 👋\n\nSend me any message and I'll echo it back.");
});

Bot::onCommand('help', function (Context $ctx) {
    $ctx->reply(
        text: "Available commands:\n/start — Welcome message\n/help — This message",
        options: ['parse_mode' => 'HTML'],
    );
});

Bot::onText(function (Context $ctx) {
    $ctx->reply("You said: " . $ctx->text());
});

Bot::onUpdate(function (Context $ctx) {
    // Catch-all for updates without a specific handler (stickers, audio, etc.)
    $ctx->reply('I only understand text messages for now!');
});

Bot::run();
