<?php

namespace Devflow\TelegramBot\Support;

class Input
{
    public static function isInt(string $value): bool
    {
        return (bool) preg_match('/^-?\d+$/', trim($value));
    }

    public static function isFloat(string $value): bool
    {
        return is_numeric(trim($value));
    }

    public static function isEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isUrl(string $value): bool
    {
        $url = trim($value);
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function isPhone(string $value): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-]{7,15}$/', trim($value));
    }

    public static function toInt(string $value, ?int $default = null): ?int
    {
        $trimmed = trim($value);
        if (!self::isInt($trimmed)) {
            return $default;
        }
        return (int) $trimmed;
    }

    public static function toFloat(string $value, ?float $default = null): ?float
    {
        $trimmed = trim($value);
        if (!self::isFloat($trimmed)) {
            return $default;
        }
        return (float) $trimmed;
    }

    public static function clean(string $value): string
    {
        return strip_tags(trim($value));
    }

    public static function truncate(string $value, int $length, string $suffix = '…'): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . $suffix;
    }

    public static function between(int|float $value, int|float $min, int|float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }
}
