<?php

namespace Devflow\TelegramBot\Types;

class ChatJoinRequest
{
    public function __construct(
        public readonly Chat $chat,
        public readonly User $from,
        public readonly int $date,
        public readonly ?string $bio = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            from: User::fromArray($data['from']),
            date: $data['date'],
            bio: $data['bio'] ?? null,
        );
    }
}
