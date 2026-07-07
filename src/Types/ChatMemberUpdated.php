<?php

namespace Devflow\TelegramBot\Types;

class ChatMemberUpdated
{
    public function __construct(
        public readonly Chat $chat,
        public readonly User $from,
        public readonly int $date,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            from: User::fromArray($data['from']),
            date: $data['date'],
            oldStatus: $data['old_chat_member']['status'] ?? 'unknown',
            newStatus: $data['new_chat_member']['status'] ?? 'unknown',
        );
    }

    public function userJoined(): bool
    {
        return in_array($this->newStatus, ['member', 'administrator', 'creator'], true)
            && in_array($this->oldStatus, ['left', 'kicked'], true);
    }

    public function userLeft(): bool
    {
        return in_array($this->newStatus, ['left', 'kicked'], true)
            && in_array($this->oldStatus, ['member', 'administrator', 'creator', 'restricted'], true);
    }
}
