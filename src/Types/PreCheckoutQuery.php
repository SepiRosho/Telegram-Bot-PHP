<?php

namespace Devflow\TelegramBot\Types;

class PreCheckoutQuery
{
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly string $currency,
        public readonly int $totalAmount,
        public readonly string $invoicePayload,
        public readonly ?string $shippingOptionId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            currency: $data['currency'],
            totalAmount: $data['total_amount'],
            invoicePayload: $data['invoice_payload'],
            shippingOptionId: $data['shipping_option_id'] ?? null,
        );
    }
}
