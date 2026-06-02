<?php

/**
 * Inline keyboard example — sending buttons and handling callback queries.
 *
 * Demonstrates:
 *  - Sending inline keyboard markup
 *  - Wildcard callback patterns (confirm_*)
 *  - Answering callback queries with a toast notification
 *  - Editing the message after a button press
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;

Bot::init('YOUR_BOT_TOKEN');

Bot::onCommand('order', function (Context $ctx) {
    $ctx->reply(
        text: '🛒 Choose an item:',
        options: [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '🍕 Pizza — $9.99',  'callback_data' => 'order_pizza'],
                        ['text' => '🍔 Burger — $7.99', 'callback_data' => 'order_burger'],
                    ],
                    [
                        ['text' => '❌ Cancel', 'callback_data' => 'order_cancel'],
                    ],
                ],
            ],
        ],
    );
});

// Wildcard: matches order_pizza, order_burger, order_*, etc.
Bot::onCallbackQuery('order_pizza', function (Context $ctx) {
    $ctx->answerCallback('Great choice! 🍕');

    Bot::editMessageText(
        chatId: $ctx->chatId(),
        messageId: $ctx->callbackQuery()->message->messageId,
        text: '✅ You ordered a Pizza! We\'ll get right on it.',
    );
});

Bot::onCallbackQuery('order_burger', function (Context $ctx) {
    $ctx->answerCallback('Excellent! 🍔');

    Bot::editMessageText(
        chatId: $ctx->chatId(),
        messageId: $ctx->callbackQuery()->message->messageId,
        text: '✅ You ordered a Burger! Coming right up.',
    );
});

Bot::onCallbackQuery('order_cancel', function (Context $ctx) {
    $ctx->answerCallback('Order cancelled.', showAlert: false);

    Bot::deleteMessage(
        chatId: $ctx->chatId(),
        messageId: $ctx->callbackQuery()->message->messageId,
    );
});

Bot::run();
