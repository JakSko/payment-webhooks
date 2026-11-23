<?php

declare(strict_types=1);

namespace App\Webhook;

/**
 * The validated part of a webhook that the system needs.
 * Personal data from the payload (email, names, company) is deliberately not mapped.
 */
final class PaymentEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $occurredAt,
        public readonly bool $livemode,
        public readonly string $transactionId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly string $customerId,
        public readonly string $orderId,
        public readonly string $storeId,
        public readonly string $channel,
    ) {
    }
}
