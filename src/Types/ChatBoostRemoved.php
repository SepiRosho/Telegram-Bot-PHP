<?php

namespace Devflow\TelegramBot\Types;

class ChatBoostRemoved
{
    public function __construct(
        public readonly Chat $chat,
        public readonly string $boostId,
        public readonly int $removeDate,
        public readonly array $source,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            boostId: $data['boost_id'],
            removeDate: $data['remove_date'],
            source: $data['source'],
        );
    }
}
