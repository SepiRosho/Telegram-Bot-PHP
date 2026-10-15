<?php

namespace Devflow\TelegramBot\Types;

/**
 * The service message Telegram sends after a payment goes through — the one
 * event a shop or Stars bot must act on (grant the item) rather than merely
 * answer. Route it with onSuccessfulPayment().
 */
class SuccessfulPayment
{
    public function __construct(
        public readonly string $currency,
        public readonly int $totalAmount,
        public readonly string $invoicePayload,
        public readonly string $telegramPaymentChargeId,
        public readonly string $providerPaymentChargeId,
        public readonly ?string $shippingOptionId = null,
        public readonly ?array $orderInfo = null,
        public readonly ?int $subscriptionExpirationDate = null,
        public readonly bool $isRecurring = false,
        public readonly bool $isFirstRecurring = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'],
            totalAmount: $data['total_amount'],
            invoicePayload: $data['invoice_payload'],
            telegramPaymentChargeId: $data['telegram_payment_charge_id'],
            providerPaymentChargeId: $data['provider_payment_charge_id'] ?? '',
            shippingOptionId: $data['shipping_option_id'] ?? null,
            orderInfo: $data['order_info'] ?? null,
            subscriptionExpirationDate: $data['subscription_expiration_date'] ?? null,
            isRecurring: (bool) ($data['is_recurring'] ?? false),
            isFirstRecurring: (bool) ($data['is_first_recurring'] ?? false),
        );
    }

    /** Telegram Stars payments use the pseudo-currency "XTR" and have no provider. */
    public function isStars(): bool
    {
        return $this->currency === 'XTR';
    }
}
