<?php

namespace Devflow\TelegramBot\Testing;

use Devflow\TelegramBot\Types\Update;

/**
 * Builds synthetic Update objects for tests, so handlers/routes/flows can be
 * exercised without a real Telegram payload. Use raw() as an escape hatch
 * for anything not covered by the named builders.
 */
class UpdateFactory
{
    private static int $nextUpdateId = 1;
    private static int $nextMessageId = 1;

    public static function reset(): void
    {
        self::$nextUpdateId = 1;
        self::$nextMessageId = 1;
    }

    public static function text(string $text, array $overrides = []): Update
    {
        return self::raw(self::mergeOverrides([
            'update_id' => self::$nextUpdateId++,
            'message'   => [
                'message_id' => self::$nextMessageId++,
                'date'       => time(),
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
                'text'       => $text,
            ],
        ], $overrides));
    }

    /** @param int|null $userId Overrides the sender's id (message.from.id). */
    public static function command(string $command, array $args = [], array $overrides = [], ?int $userId = null): Update
    {
        $name = ltrim($command, '/');
        $text = '/' . $name . ($args === [] ? '' : ' ' . implode(' ', $args));

        $merged = self::mergeOverrides([
            'message' => [
                'entities' => [['type' => 'bot_command', 'offset' => 0, 'length' => strlen($name) + 1]],
            ],
        ], $overrides);

        if ($userId !== null) {
            $merged = self::mergeOverrides($merged, ['message' => ['from' => ['id' => $userId]]]);
        }

        return self::text($text, $merged);
    }

    public static function callback(string $data, array $overrides = []): Update
    {
        return self::raw(self::mergeOverrides([
            'update_id'      => self::$nextUpdateId++,
            'callback_query' => [
                'id'      => 'cb' . self::$nextUpdateId,
                'from'    => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
                'data'    => $data,
                'message' => [
                    'message_id' => self::$nextMessageId++,
                    'date'       => time(),
                    'chat'       => ['id' => 100, 'type' => 'private'],
                    'from'       => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
            ],
        ], $overrides));
    }

    /** Alias of callback() — matches the naming AGENTS.md documents. */
    public static function callbackQuery(string $data, array $overrides = []): Update
    {
        return self::callback($data, $overrides);
    }

    public static function photo(array $overrides = []): Update
    {
        return self::media(['photo' => [['file_id' => 'photo1', 'width' => 100, 'height' => 100]]], $overrides);
    }

    public static function voice(array $overrides = []): Update
    {
        return self::media(['voice' => ['file_id' => 'voice1', 'duration' => 3]], $overrides);
    }

    public static function document(array $overrides = []): Update
    {
        return self::media(['document' => ['file_id' => 'doc1', 'file_name' => 'file.txt']], $overrides);
    }

    private static function media(array $mediaField, array $overrides): Update
    {
        return self::raw(self::mergeOverrides([
            'update_id' => self::$nextUpdateId++,
            'message'   => array_merge([
                'message_id' => self::$nextMessageId++,
                'date'       => time(),
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
            ], $mediaField),
        ], $overrides));
    }

    /** Escape hatch: build an Update from a raw update array. */
    public static function raw(array $data): Update
    {
        return Update::fromArray($data);
    }

    /**
     * Like array_replace_recursive(), except a list value (entities, photo, ...)
     * in $overrides replaces the base list wholesale instead of merging by
     * index — array_replace_recursive can't remove or fully swap a list, since
     * it only ever overwrites the indices present in the override.
     */
    private static function mergeOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $base[$key] = $value;
            } elseif (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($base[$key])) {
                $base[$key] = self::mergeOverrides($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
