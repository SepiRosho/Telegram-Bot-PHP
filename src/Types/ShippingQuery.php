<?php

namespace Devflow\TelegramBot\Types;

class ShippingQuery
{
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly string $invoicePayload,
        public readonly array $shippingAddress,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            invoicePayload: $data['invoice_payload'],
            shippingAddress: $data['shipping_address'],
        );
    }
}
