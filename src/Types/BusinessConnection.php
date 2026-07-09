<?php

namespace Devflow\TelegramBot\Types;

class BusinessConnection
{
    public function __construct(
        public readonly string $id,
        public readonly User $user,
        public readonly int $userChatId,
        public readonly int $date,
        public readonly bool $isEnabled,
        public readonly ?array $rights = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            user: User::fromArray($data['user']),
            userChatId: $data['user_chat_id'],
            date: $data['date'],
            isEnabled: $data['is_enabled'] ?? false,
            rights: $data['rights'] ?? null,
        );
    }
}
