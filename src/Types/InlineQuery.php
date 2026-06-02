<?php

namespace Devflow\TelegramBot\Types;

class InlineQuery
{
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly string $query,
        public readonly string $offset,
        public readonly ?string $chatType = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            query: $data['query'],
            offset: $data['offset'],
            chatType: $data['chat_type'] ?? null,
        );
    }
}
