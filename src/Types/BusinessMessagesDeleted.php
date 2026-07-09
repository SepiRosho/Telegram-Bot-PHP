<?php

namespace Devflow\TelegramBot\Types;

class BusinessMessagesDeleted
{
    public function __construct(
        public readonly string $businessConnectionId,
        public readonly Chat $chat,
        public readonly array $messageIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            businessConnectionId: $data['business_connection_id'],
            chat: Chat::fromArray($data['chat']),
            messageIds: $data['message_ids'],
        );
    }
}
