<?php

namespace Devflow\TelegramBot\Types;

class MessageReactionUpdated
{
    public function __construct(
        public readonly Chat $chat,
        public readonly int $messageId,
        public readonly int $date,
        public readonly ?User $user = null,
        public readonly ?Chat $actorChat = null,
        public readonly array $oldReaction = [],
        public readonly array $newReaction = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            messageId: $data['message_id'],
            date: $data['date'],
            user: isset($data['user']) ? User::fromArray($data['user']) : null,
            actorChat: isset($data['actor_chat']) ? Chat::fromArray($data['actor_chat']) : null,
            oldReaction: $data['old_reaction'] ?? [],
            newReaction: $data['new_reaction'] ?? [],
        );
    }
}
