<?php

namespace Devflow\TelegramBot\Support;

use Devflow\TelegramBot\Context;

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
