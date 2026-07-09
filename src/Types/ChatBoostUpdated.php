<?php

namespace Devflow\TelegramBot\Types;

class ChatBoostUpdated
{
    public function __construct(
        public readonly Chat $chat,
        public readonly array $boost,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            boost: $data['boost'],
        );
    }
}
