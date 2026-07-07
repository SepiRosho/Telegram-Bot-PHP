<?php

/**
 * Inline keyboard example — sending buttons and handling callback queries.
 *
 * Demonstrates:
 *  - Building inline keyboards with Keyboard::inline() / Keyboard::button()
 *  - Wildcard callback patterns (order_*)
 *  - Answering callback queries with a toast notification
 *  - Editing or deleting the message after a button press via Context shorthands
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\Keyboard;

Bot::init('YOUR_BOT_TOKEN');

Bot::onCommand('order', function (Context $ctx) {
    $ctx->reply('🛒 Choose an item:', [
        'reply_markup' => Keyboard::inline([
            [
                Keyboard::button('🍕 Pizza — $9.99',  'order_pizza'),
                Keyboard::button('🍔 Burger — $7.99', 'order_burger'),
            ],
            [
                Keyboard::button('❌ Cancel', 'order_cancel'),
            ],
        ]),
    ]);
});

Bot::onCallbackQuery('order_pizza', function (Context $ctx) {
    $ctx->answerCallback('Great choice! 🍕');
    $ctx->editReply('✅ You ordered a Pizza! We\'ll get right on it.');
});

Bot::onCallbackQuery('order_burger', function (Context $ctx) {
    $ctx->answerCallback('Excellent! 🍔');
    $ctx->editReply('✅ You ordered a Burger! Coming right up.');
});

Bot::onCallbackQuery('order_cancel', function (Context $ctx) {
    $ctx->answerCallback('Order cancelled.');
    $ctx->deleteCurrentMessage();
});

Bot::run();
