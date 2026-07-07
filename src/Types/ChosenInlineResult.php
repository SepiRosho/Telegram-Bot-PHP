<?php

namespace Devflow\TelegramBot\Types;

class ChosenInlineResult
{
    public function __construct(
        public readonly string $resultId,
        public readonly User $from,
        public readonly string $query,
        public readonly ?string $inlineMessageId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            resultId: $data['result_id'],
            from: User::fromArray($data['from']),
            query: $data['query'],
            inlineMessageId: $data['inline_message_id'] ?? null,
        );
    }
}
