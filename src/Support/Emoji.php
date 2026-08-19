<?php

namespace Devflow\TelegramBot\Support;

/**
 * Named premium (custom) emoji, so a handler can write `:fire:` instead of
 * the raw markup Telegram requires to show a premium emoji with a fallback
 * glyph for clients that can't render it:
 *
 *   HTML:       <tg-emoji emoji-id="5368324170671202286">🔥</tg-emoji>
 *   MarkdownV2: ![🔥](tg://emoji?id=5368324170671202286)
 *
 * Register once (e.g. from app/Emojis.php in bootstrap/app.php):
 *   Emoji::register('fire', '5368324170671202286', '🔥');
 *
 * Then anywhere you build message text:
 *   $ctx->reply(Emoji::text('Nice :fire: work!'), ['parse_mode' => 'HTML']);
 *
 * Premium emoji only render under 'HTML' or 'MarkdownV2' parse_mode — legacy
 * 'Markdown' has no equivalent syntax.
 */
class Emoji
{
    /** @var array<string, array{id: string, fallback: string}> */
    private static array $registry = [];

    public static function register(string $name, string $id, string $fallback): void
    {
        self::$registry[$name] = ['id' => $id, 'fallback' => $fallback];
    }

    /**
     * Bulk-register from an array, e.g. the return value of app/Emojis.php:
     *   ['fire' => ['id' => '5368324170671202286', 'fallback' => '🔥']]
     * or the shorter positional form: ['fire' => ['5368324170671202286', '🔥']]
     */
    public static function registerMany(array $emojis): void
    {
        foreach ($emojis as $name => $definition) {
            self::register(
                $name,
                (string) ($definition['id'] ?? $definition[0] ?? ''),
                (string) ($definition['fallback'] ?? $definition[1] ?? ''),
            );
        }
    }

    public static function has(string $name): bool
    {
        return isset(self::$registry[$name]);
    }

    /** Markup for one named emoji. Unregistered names are returned as the literal ":name:" shortcode. */
    public static function get(string $name, string $parseMode = 'HTML'): string
    {
        if (!isset(self::$registry[$name])) {
            return ":{$name}:";
        }

        return self::markup(self::$registry[$name]['id'], self::$registry[$name]['fallback'], $parseMode);
    }

    /**
     * Expands every `:name:` shortcode for a registered emoji in $text.
     * Shortcodes for names that aren't registered are left untouched, since
     * a colon-wrapped word in ordinary text (times, ratios, ...) is not
     * necessarily meant as one — so this never warns or throws on a miss.
     */
    public static function text(string $text, string $parseMode = 'HTML'): string
    {
        return (string) preg_replace_callback(
            '/:([a-zA-Z0-9_]+):/',
            fn(array $m) => self::has($m[1]) ? self::get($m[1], $parseMode) : $m[0],
            $text,
        );
    }

    private static function markup(string $id, string $fallback, string $parseMode): string
    {
        return match (strtolower($parseMode)) {
            'html' => '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>',
            'markdownv2' => '![' . $fallback . '](tg://emoji?id=' . $id . ')',
            default => throw new \InvalidArgumentException(
                "Premium emoji require parse_mode 'HTML' or 'MarkdownV2' (got '{$parseMode}')."
                . " Legacy 'Markdown' has no custom-emoji syntax."
            ),
        };
    }

    /** Test-only: clears every registered emoji. */
    public static function reset(): void
    {
        self::$registry = [];
    }
}
