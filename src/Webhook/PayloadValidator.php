<?php

declare(strict_types=1);

namespace App\Webhook;

/**
 * Validates the decoded webhook body and maps it to a PaymentEvent.
 * Collects all errors at once; messages name the rule, never echo the value.
 */
final class PayloadValidator
{
    public const ID_PATTERN = '/^[A-Za-z0-9_.:-]{1,128}$/';
    private const EVENT_TYPE_PATTERN = '/^[a-z_]+(\.[a-z_]+)+$/';
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';
    private const MAX_TEXT_LENGTH = 255;

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<mixed> $payload
     * @throws InvalidPayload
     */
    public function validate(array $payload): PaymentEvent
    {
        $this->errors = [];

        $event = new PaymentEvent(
            eventId: $this->matching($payload, 'event_id', self::ID_PATTERN),
            eventType: $this->matching($payload, 'event', self::EVENT_TYPE_PATTERN),
            occurredAt: $this->positiveInt($payload, 'timestamp'),
            livemode: $this->bool($payload, 'livemode'),
            transactionId: $this->matching($payload, 'data.transaction.id', self::ID_PATTERN),
            amountMinor: $this->nonNegativeInt($payload, 'data.transaction.amount.value'),
            currency: $this->matching($payload, 'data.transaction.amount.currency', self::CURRENCY_PATTERN),
            paymentMethod: $this->text($payload, 'data.transaction.payment_method.type'),
            transactionStatus: $this->text($payload, 'data.transaction.status'),
            customerId: $this->matching($payload, 'data.customer.id', self::ID_PATTERN),
            orderId: $this->matching($payload, 'data.metadata.order_id', self::ID_PATTERN),
            storeId: $this->matching($payload, 'data.metadata.store_id', self::ID_PATTERN),
            channel: $this->text($payload, 'data.metadata.channel'),
        );

        if ($this->errors !== []) {
            throw new InvalidPayload($this->errors);
        }

        return $event;
    }

    /** @param array<mixed> $payload */
    private function value(array $payload, string $path): mixed
    {
        $current = $payload;
        foreach (explode('.', $path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /** @param array<mixed> $payload */
    private function text(array $payload, string $path): string
    {
        $value = $this->value($payload, $path);
        if (!is_string($value) || $value === '' || strlen($value) > self::MAX_TEXT_LENGTH) {
            $this->errors[$path] = sprintf('must be a non-empty string up to %d characters', self::MAX_TEXT_LENGTH);
            return '';
        }

        return $value;
    }

    /** @param array<mixed> $payload */
    private function matching(array $payload, string $path, string $pattern): string
    {
        $value = $this->value($payload, $path);
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            $this->errors[$path] = 'must be a string matching ' . $pattern;
            return '';
        }

        return $value;
    }

    /** @param array<mixed> $payload */
    private function nonNegativeInt(array $payload, string $path): int
    {
        $value = $this->value($payload, $path);
        if (!is_int($value) || $value < 0) {
            $this->errors[$path] = 'must be a non-negative integer (minor units)';
            return 0;
        }

        return $value;
    }

    /** @param array<mixed> $payload */
    private function positiveInt(array $payload, string $path): int
    {
        $value = $this->value($payload, $path);
        if (!is_int($value) || $value <= 0) {
            $this->errors[$path] = 'must be a positive integer (unix time)';
            return 0;
        }

        return $value;
    }

    /** @param array<mixed> $payload */
    private function bool(array $payload, string $path): bool
    {
        $value = $this->value($payload, $path);
        if (!is_bool($value)) {
            $this->errors[$path] = 'must be a boolean';
            return false;
        }

        return $value;
    }
}
