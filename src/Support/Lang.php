<?php

namespace Devflow\TelegramBot\Support;

/**
 * Key-based i18n: per-locale PHP array files (dot-notation keys), loaded
 * from a configured directory (see `lang_path` in Bot::init's config).
 *
 * lang/en.php:
 *   return ['welcome' => 'Hello, {name}!', 'menu' => ['account' => 'My Account']];
 *
 * Usage: $ctx->t('welcome', ['name' => 'Ali']) or $ctx->t('menu.account')
 */
class Lang
{
    private static ?string $path = null;
    private static string $defaultLocale = 'en';
    private static array $cache = [];

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/\\');
        self::$cache = [];
    }

    public static function setDefaultLocale(string $locale): void
    {
        self::$defaultLocale = $locale;
    }

    public static function defaultLocale(): string
    {
        return self::$defaultLocale;
    }

    /**
     * Resolve a translation key for a locale, falling back to the default
     * locale and then the key itself, interpolating `{placeholder}` vars.
     */
    public static function get(string $locale, string $key, array $vars = []): string
    {
        $text = self::lookup($locale, $key)
            ?? self::lookup(self::$defaultLocale, $key)
            ?? $key;

        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    public static function has(string $locale, string $key): bool
    {
        return self::lookup($locale, $key) !== null;
    }

    /**
     * Reverse lookup — find the translation key whose value matches $text in
     * any of the given locales. Useful for matching reply-keyboard button
     * presses back to a key regardless of which locale rendered them.
     */
    public static function findKey(string $text, array $locales): ?string
    {
        foreach ($locales as $locale) {
            $key = array_search($text, self::load($locale), true);
            if ($key !== false) {
                return $key;
            }
        }

        return null;
    }

    private static function lookup(string $locale, string $key): ?string
    {
        return self::load($locale)[$key] ?? null;
    }

    private static function load(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        if (self::$path === null) {
            return self::$cache[$locale] = [];
        }

        $file = self::$path . DIRECTORY_SEPARATOR . $locale . '.php';
        if (!is_file($file)) {
            return self::$cache[$locale] = [];
        }

        $data = require $file;

        return self::$cache[$locale] = self::flatten(is_array($data) ? $data : []);
    }

    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += self::flatten($value, $fullKey);
            } else {
                $flat[$fullKey] = (string) $value;
            }
        }

        return $flat;
    }
}
