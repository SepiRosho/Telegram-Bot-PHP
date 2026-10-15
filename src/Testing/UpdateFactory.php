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

    public static function video(array $overrides = []): Update
    {
        return self::media(['video' => ['file_id' => 'video1', 'duration' => 5, 'width' => 640, 'height' => 360]], $overrides);
    }

    public static function audio(array $overrides = []): Update
    {
        return self::media(['audio' => ['file_id' => 'audio1', 'duration' => 30]], $overrides);
    }

    public static function videoNote(array $overrides = []): Update
    {
        return self::media(['video_note' => ['file_id' => 'vnote1', 'length' => 240, 'duration' => 3]], $overrides);
    }

    public static function sticker(array $overrides = []): Update
    {
        return self::media(['sticker' => ['file_id' => 'sticker1', 'type' => 'regular', 'width' => 512, 'height' => 512]], $overrides);
    }

    public static function animation(array $overrides = []): Update
    {
        return self::media(['animation' => ['file_id' => 'gif1', 'width' => 320, 'height' => 240, 'duration' => 2]], $overrides);
    }

    public static function location(float $latitude = 52.52, float $longitude = 13.405, array $overrides = []): Update
    {
        return self::media(['location' => ['latitude' => $latitude, 'longitude' => $longitude]], $overrides);
    }

    public static function contact(string $phoneNumber = '+10000000000', array $overrides = []): Update
    {
        return self::media(['contact' => ['phone_number' => $phoneNumber, 'first_name' => 'Contact']], $overrides);
    }

    /** A Telegram-Stars (or provider) payment confirmation — what onSuccessfulPayment() receives. */
    public static function successfulPayment(string $payload = 'order-1', int $amount = 100, string $currency = 'XTR', array $overrides = []): Update
    {
        return self::media(['successful_payment' => [
            'currency'                   => $currency,
            'total_amount'               => $amount,
            'invoice_payload'            => $payload,
            'telegram_payment_charge_id' => 'charge-1',
            'provider_payment_charge_id' => $currency === 'XTR' ? '' : 'provider-1',
        ]], $overrides);
    }

    /** @param array[] $users Users who joined, each ['id' => ..., 'first_name' => ...]; defaults to one. */
    public static function newChatMembers(array $users = [], array $overrides = []): Update
    {
        $users = array_map(
            fn(array $u) => array_merge(['is_bot' => false, 'first_name' => 'Joiner'], $u),
            $users ?: [['id' => 300]],
        );

        return self::groupMedia(['new_chat_members' => $users], $overrides);
    }

    public static function leftChatMember(int $userId = 300, array $overrides = []): Update
    {
        return self::groupMedia(['left_chat_member' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Leaver']], $overrides);
    }

    public static function inlineQuery(string $query = '', array $overrides = []): Update
    {
        return self::raw(self::mergeOverrides([
            'update_id'    => self::$nextUpdateId++,
            'inline_query' => [
                'id'     => 'iq' . self::$nextUpdateId,
                'from'   => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
                'query'  => $query,
                'offset' => '',
            ],
        ], $overrides));
    }

    /**
     * A my_chat_member / chat_member update. $type is the update key
     * ('my_chat_member' or 'chat_member'); statuses are Telegram's
     * ('member', 'administrator', 'left', 'kicked', ...).
     */
    public static function chatMember(string $newStatus = 'member', string $oldStatus = 'left', string $type = 'chat_member', array $overrides = []): Update
    {
        $member = fn(string $status) => ['status' => $status, 'user' => ['id' => 300, 'is_bot' => false, 'first_name' => 'Member']];

        return self::raw(self::mergeOverrides([
            'update_id' => self::$nextUpdateId++,
            $type        => [
                'chat'            => ['id' => -100, 'type' => 'supergroup', 'title' => 'Group'],
                'from'            => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
                'date'            => time(),
                'old_chat_member' => $member($oldStatus),
                'new_chat_member' => $member($newStatus),
            ],
        ], $overrides));
    }

    public static function myChatMember(string $newStatus = 'member', string $oldStatus = 'left', array $overrides = []): Update
    {
        return self::chatMember($newStatus, $oldStatus, 'my_chat_member', $overrides);
    }

    public static function messageReaction(string $emoji = "\u{1F44D}", array $overrides = []): Update
    {
        return self::raw(self::mergeOverrides([
            'update_id'        => self::$nextUpdateId++,
            'message_reaction' => [
                'chat'         => ['id' => 100, 'type' => 'private'],
                'message_id'   => 1,
                'user'         => ['id' => 200, 'is_bot' => false, 'first_name' => 'Test'],
                'date'         => time(),
                'old_reaction' => [],
                'new_reaction' => [['type' => 'emoji', 'emoji' => $emoji]],
            ],
        ], $overrides));
    }

    /** Like media(), but in a supergroup — service messages never occur in private chats. */
    private static function groupMedia(array $mediaField, array $overrides): Update
    {
        return self::media($mediaField, self::mergeOverrides([
            'message' => ['chat' => ['id' => -100, 'type' => 'supergroup', 'title' => 'Group']],
        ], $overrides));
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
