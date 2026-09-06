<?php

// Lang auto-fallback, reusable keyboards, model generator, premium emoji.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::fixable(
        check: fn(string $root) => is_dir($root . '/app/Keyboards'),
        okMessage: 'app/Keyboards/ exists',
        problemMessage: 'app/Keyboards/ is missing (target directory for `make:keyboard`)',
        fix: function (string $root): void {
            mkdir($root . '/app/Keyboards', 0755, true);
            file_put_contents($root . '/app/Keyboards/.gitkeep', '');
        },
    ),

    UpgradeStep::fixable(
        check: fn(string $root) => file_exists($root . '/app/Emojis.php'),
        okMessage: 'app/Emojis.php exists',
        problemMessage: 'app/Emojis.php is missing (registry consumed by Support\\Emoji)',
        fix: fn(string $root) => file_put_contents($root . '/app/Emojis.php', <<<'PHP'
        <?php

        // Premium (custom) emoji, registered by name so you can write ":fire:"
        // in message text instead of the raw <tg-emoji>/tg://emoji markup Telegram
        // needs to show one with a fallback glyph for clients that can't render it.
        // Grab the emoji-id by forwarding a message containing it to @userinfobot,
        // or from getCustomEmojiStickers in the Bot API.
        //
        // Loaded once in bootstrap/app.php via Emoji::registerMany(). See docs/16-premium-emoji.md.
        //
        // return [
        //     'fire' => ['id' => '5368324170671202286', 'fallback' => '🔥'],
        // ];

        return [];
        PHP),
    ),

    UpgradeStep::envKey('LANG_AUTO_FALLBACK', 'LANG_AUTO_FALLBACK=false'),

    UpgradeStep::bootstrapMarker(
        marker: 'lang_auto_fallback',
        label: 'lang_auto_fallback',
        snippet: "'lang_auto_fallback' => filter_var(env('LANG_AUTO_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),",
    ),

    UpgradeStep::bootstrapMarker(
        marker: 'Emoji::registerMany',
        label: 'Emoji::registerMany',
        snippet: "\\Devflow\\TelegramBot\\Support\\Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');",
    ),
];
