<?php

namespace Devflow\TelegramBot\Types;

class CallbackQuery
{
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly ?Message $message,
        public readonly ?string $data,
        public readonly ?string $inlineMessageId = null,
        public readonly ?string $chatInstance = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            data: $data['data'] ?? null,
            inlineMessageId: $data['inline_message_id'] ?? null,
            chatInstance: $data['chat_instance'] ?? null,
        );
    }
}
