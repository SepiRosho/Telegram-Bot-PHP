<?php

namespace Devflow\TelegramBot\Support;

use Devflow\TelegramBot\Context;

/**
 * @deprecated Use Support\Lang / Context::t() instead — one class per string
 * does not scale to a real app's string count. Kept for backward compatibility.
 */
abstract class BotText
{
    abstract protected static function translations(): array;

    public static function get(array $vars = [], string $lang = 'en'): string
    {
        $map = static::translations();
        $text = $map[$lang] ?? $map['en'] ?? '';

        foreach ($vars as $key => $val) {
            $text = str_replace('{' . $key . '}', (string) $val, $text);
        }

        return $text;
    }

    public static function forContext(Context $ctx, array $vars = []): string
    {
        $lang = $ctx->from()?->languageCode
            ?? $ctx->user()?->language_code
            ?? 'en';

        return static::get($vars, $lang);
    }
}
