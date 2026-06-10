<?php

/**
 * Example 08: Keyboard helpers
 *
 * Demonstrates Keyboard::inline(), Keyboard::reply(), Keyboard::button(), and Keyboard::remove().
 * The Keyboard class returns PHP arrays — Guzzle JSON-encodes them automatically.
 * Do NOT pass json_encode() output as reply_markup; that double-encodes the value.
 */

require __DIR__ . '/../vendor/autoload.php';

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\Keyboard;

Bot::init($_ENV['BOT_TOKEN'] ?? 'YOUR_TOKEN');

// ─── Reply keyboard (persistent buttons at the bottom of the screen) ──────────
Bot::onCommand('start', function (Context $ctx) {
    $ctx->reply('👋 Welcome! Use the buttons below.', [
        'reply_markup' => Keyboard::reply([
            ['📋 My Account', '🔔 Notifications'],
            ['ℹ️ About',       '❓ Help'],
        ]),
    ]);
});

// One-time reply keyboard (disappears after one tap)
Bot::onCommand('ask', function (Context $ctx) {
    $ctx->reply('Are you sure?', [
        'reply_markup' => Keyboard::reply(
            [['✅ Yes', '❌ No']],
            resize: true,
            oneTime: true
        ),
    ]);
});

// ─── Remove reply keyboard ─────────────────────────────────────────────────────
Bot::onCommand('close', function (Context $ctx) {
    $ctx->reply('Keyboard removed.', [
        'reply_markup' => Keyboard::remove(),
    ]);
});

// ─── Inline keyboard (buttons attached to a message) ─────────────────────────
Bot::onCommand('menu', function (Context $ctx) {
    $ctx->reply('Choose an option:', [
        'reply_markup' => Keyboard::inline([
            // Two buttons side-by-side in one row
            [Keyboard::button('📊 Stats', 'menu_stats'), Keyboard::button('👥 Users', 'menu_users')],
            // One full-width button
            [Keyboard::button('⚙️ Settings', 'menu_settings')],
            // URL button
            [Keyboard::url('📖 Documentation', 'https://github.com/devflow/telegram-bot')],
        ]),
    ]);
});

// ─── Handling inline button callbacks ─────────────────────────────────────────
Bot::onCallbackQuery('menu_stats', function (Context $ctx) {
    $ctx->answerCallback('Loading stats…');
    $ctx->reply('📊 Stats: everything is running fine.');
});

Bot::onCallbackQuery('menu_users', function (Context $ctx) {
    $ctx->answerCallback();
    $ctx->reply('👥 There are no users yet.');
});

// Wildcard: catch all menu_* callbacks
Bot::onCallbackQuery('menu_*', function (Context $ctx) {
    $ctx->answerCallback('This section is under construction.', showAlert: true);
});

Bot::run();
