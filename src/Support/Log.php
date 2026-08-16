<?php

namespace Devflow\TelegramBot\Support;

class Log
{
    private static string $path = '';
    private static ?int $adminChatId = null;

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/\\');
    }

    public static function setAdminChatId(int $id): void
    {
        self::$adminChatId = $id > 0 ? $id : null;
    }

    /**
     * Write a log entry to a daily file.
     * Pass $_depth=2 when calling through a one-level wrapper (global saveLog(), Bot::saveLog())
     * so the reported file:line points to the actual call site instead of the wrapper.
     */
    public static function save(mixed $data, string $level = 'INFO', int $_depth = 1): void
    {
        if (self::$path === '') {
            return;
        }

        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $_depth);
        $caller = $trace[$_depth - 1] ?? $trace[0] ?? [];
        $file   = basename($caller['file'] ?? 'unknown');
        $line   = $caller['line'] ?? 0;

        $message   = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = date('Y-m-d H:i:s');
        $entry     = "[{$timestamp}] [{$level}] {$message} | {$file}:{$line}" . PHP_EOL;

        $logFile = self::$path . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send a message directly to the configured admin chat ID via Telegram.
     * Falls back to save() if Bot is not initialized or no admin chat ID is set.
     */
    public static function send(mixed $data): void
    {
        if (self::$adminChatId === null) {
            self::save($data, 'BOT_LOG');
            return;
        }

        try {
            $message = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            \Devflow\TelegramBot\Bot::sendMessage(self::$adminChatId, $message);
        } catch (\Throwable) {
            self::save($data, 'BOT_LOG');
        }
    }
}
