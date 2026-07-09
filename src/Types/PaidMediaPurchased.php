<?php

namespace Devflow\TelegramBot\Types;

class PaidMediaPurchased
{
    public function __construct(
        public readonly User $from,
        public readonly string $paidMediaPayload,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            from: User::fromArray($data['from']),
            paidMediaPayload: $data['paid_media_payload'],
        );
    }
}
