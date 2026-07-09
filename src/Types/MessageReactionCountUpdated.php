<?php

namespace Devflow\TelegramBot\Types;

class MessageReactionCountUpdated
{
    public function __construct(
        public readonly Chat $chat,
        public readonly int $messageId,
        public readonly int $date,
        public readonly array $reactions,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            messageId: $data['message_id'],
            date: $data['date'],
            reactions: $data['reactions'] ?? [],
        );
    }
}
