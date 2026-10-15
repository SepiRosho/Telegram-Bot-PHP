<?php

namespace Devflow\TelegramBot\Support;

/**
 * Parses the `allowed_updates` setting shared by setWebhook (webhook:set /
 * telegram:set-webhook) and getUpdates (polling).
 *
 * Telegram's default subscription leaves out chat_member, message_reaction
 * and message_reaction_count, so registering onChatMember()/onMessageReaction()
 * handlers does nothing until those types are asked for by name. And omitting
 * the parameter does not reset it — Telegram keeps whatever was set last — so
 * "not specified" and "back to default" are different requests.
 */
final class AllowedUpdates
{
    /** Every update type this library can route. */
    public const ALL = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'business_connection',
        'business_message',
        'edited_business_message',
        'deleted_business_messages',
        'guest_message',
        'message_reaction',
        'message_reaction_count',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'purchased_paid_media',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
        'chat_boost',
        'removed_chat_boost',
        'managed_bot',
    ];

    /** Not delivered unless requested by name (Telegram's documented default). */
    public const OFF_BY_DEFAULT = ['chat_member', 'message_reaction', 'message_reaction_count'];

    /** Route types (see Router) that are all just a `message` update. */
    private const MESSAGE_ROUTES = [
        'command', 'unknown_command', 'text', 'message', 'photo', 'document', 'video', 'voice', 'audio',
        'video_note', 'sticker', 'animation', 'location', 'contact', 'venue', 'new_chat_members',
        'left_chat_member', 'successful_payment', 'web_app_data', 'step',
    ];

    /**
     * The update types Telegram has to deliver for these route types to ever fire.
     * `update` (the catch-all) asks for nothing in particular, so it adds nothing.
     *
     * @param  string[] $routeTypes
     * @return string[]
     */
    public static function requiredBy(array $routeTypes): array
    {
        $needed = [];
        foreach (array_unique($routeTypes) as $type) {
            if (in_array($type, self::MESSAGE_ROUTES, true)) {
                $needed[] = 'message';
            } elseif (in_array($type, self::ALL, true)) {
                $needed[] = $type;
            }
        }

        return array_values(array_unique($needed));
    }

    /**
     * What a stored allowed_updates list means in practice: [] is Telegram's
     * default set, everything except OFF_BY_DEFAULT.
     *
     * @param  string[]|null $allowed
     * @return string[]
     */
    public static function effective(?array $allowed): array
    {
        return $allowed === null || $allowed === []
            ? array_values(array_diff(self::ALL, self::OFF_BY_DEFAULT))
            : $allowed;
    }

    /**
     * @param string|array|null $spec
     *   null / '' / []   → null: leave it out, Telegram keeps its previous setting
     *   'all'            → every type in ALL (including chat_member and the reaction types)
     *   'default'        → []: Telegram's default set (everything except those three)
     *   'a,b' / ['a','b'] → exactly those types ('all' may be mixed in)
     *
     * @return string[]|null
     *
     * @throws \InvalidArgumentException on an unknown update type
     */
    public static function parse(string|array|null $spec): ?array
    {
        if (is_string($spec)) {
            $spec = explode(',', $spec);
        }

        $names = array_values(array_filter(
            array_map(fn($n) => strtolower(trim((string) $n)), $spec ?? []),
            fn($n) => $n !== '',
        ));

        if ($names === []) {
            return null;
        }

        if ($names === ['default']) {
            return [];
        }

        $types = [];
        foreach ($names as $name) {
            if ($name === 'all') {
                array_push($types, ...self::ALL);
                continue;
            }
            if (!in_array($name, self::ALL, true)) {
                throw new \InvalidArgumentException(
                    "Unknown update type \"{$name}\". Valid types: " . implode(', ', self::ALL)
                    . ' (or "all" / "default").'
                );
            }
            $types[] = $name;
        }

        return array_values(array_unique($types));
    }
}
