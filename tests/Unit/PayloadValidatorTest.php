<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Webhook\InvalidPayload;
use App\Webhook\PayloadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayloadValidatorTest extends TestCase
{
    /** Payload from the task description (synthetic data). */
    private static function validPayload(): array
    {
        return [
            'event' => 'charge.successful',
            'event_id' => 'evt_9f8d7c6b5a4321',
            'timestamp' => 1718029200,
            'livemode' => true,
            'data' => [
                'transaction' => [
                    'id' => 'txn_8a7b6c5d4e3f',
                    'provider_reference' => 'PAYU-9876543210',
                    'amount' => ['value' => 29990, 'currency' => 'PLN'],
                    'payment_method' => ['type' => 'blik', 'issuer' => 'mBank'],
                    'status' => 'COMPLETED',
                ],
                'customer' => [
                    'id' => 'cus_11223344',
                    'email' => 'jan.kowalski@example.com',
                    'billing_address' => [
                        'first_name' => 'Jan',
                        'last_name' => 'Kowalski',
                        'company' => 'Kowalski Dev Ltd',
                        'country' => 'PL',
                    ],
                ],
                'metadata' => [
                    'order_id' => 'ORD-2024-8839',
                    'store_id' => 'store_main_pl',
                    'channel' => 'mobile_app',
                ],
            ],
        ];
    }

    public function testMapsValidPayloadWithoutPersonalData(): void
    {
        $event = (new PayloadValidator())->validate(self::validPayload());

        $this->assertSame('evt_9f8d7c6b5a4321', $event->eventId);
        $this->assertSame('charge.successful', $event->eventType);
        $this->assertSame(1718029200, $event->occurredAt);
        $this->assertTrue($event->livemode);
        $this->assertSame('txn_8a7b6c5d4e3f', $event->transactionId);
        $this->assertSame(29990, $event->amountMinor);
        $this->assertSame('PLN', $event->currency);
        $this->assertSame('blik', $event->paymentMethod);
        $this->assertSame('cus_11223344', $event->customerId);
        $this->assertSame('ORD-2024-8839', $event->orderId);
        $this->assertSame('store_main_pl', $event->storeId);
        $this->assertSame('mobile_app', $event->channel);

        $mapped = var_export($event, true);
        foreach (['jan.kowalski@example.com', 'Jan', 'Kowalski'] as $personal) {
            $this->assertStringNotContainsString($personal, $mapped);
        }
    }

    /** @return iterable<string, array{callable(array): array, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'missing event_id' => [static function (array $p): array {
            unset($p['event_id']);
            return $p;
        }, 'event_id'];
        yield 'event_id with illegal characters' => [static function (array $p): array {
            $p['event_id'] = 'evt 1; DROP TABLE';
            return $p;
        }, 'event_id'];
        yield 'amount as string' => [static function (array $p): array {
            $p['data']['transaction']['amount']['value'] = '29990';
            return $p;
        }, 'data.transaction.amount.value'];
        yield 'amount as float' => [static function (array $p): array {
            $p['data']['transaction']['amount']['value'] = 299.90;
            return $p;
        }, 'data.transaction.amount.value'];
        yield 'negative amount' => [static function (array $p): array {
            $p['data']['transaction']['amount']['value'] = -1;
            return $p;
        }, 'data.transaction.amount.value'];
        yield 'lowercase currency' => [static function (array $p): array {
            $p['data']['transaction']['amount']['currency'] = 'pln';
            return $p;
        }, 'data.transaction.amount.currency'];
        yield 'timestamp as string' => [static function (array $p): array {
            $p['timestamp'] = '1718029200';
            return $p;
        }, 'timestamp'];
        yield 'livemode missing' => [static function (array $p): array {
            unset($p['livemode']);
            return $p;
        }, 'livemode'];
        yield 'metadata missing' => [static function (array $p): array {
            unset($p['data']['metadata']);
            return $p;
        }, 'data.metadata.order_id'];
        yield 'data is a list' => [static function (array $p): array {
            $p['data'] = [1, 2, 3];
            return $p;
        }, 'data.transaction.id'];
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayload(callable $mutate, string $expectedPath): void
    {
        try {
            (new PayloadValidator())->validate($mutate(self::validPayload()));
            $this->fail('Expected InvalidPayload');
        } catch (InvalidPayload $e) {
            $this->assertContains($expectedPath, array_keys($e->errors));
        }
    }

    public function testDoesNotRequireFieldsTheSystemDoesNotUse(): void
    {
        $payload = self::validPayload();
        unset($payload['data']['transaction']['status'], $payload['data']['transaction']['provider_reference']);

        $event = (new PayloadValidator())->validate($payload);

        $this->assertSame('evt_9f8d7c6b5a4321', $event->eventId);
    }

    public function testErrorMessagesDoNotEchoValues(): void
    {
        $payload = self::validPayload();
        $payload['data']['transaction']['amount']['value'] = 'jan.kowalski@example.com';

        try {
            (new PayloadValidator())->validate($payload);
            $this->fail('Expected InvalidPayload');
        } catch (InvalidPayload $e) {
            $this->assertStringNotContainsString('example.com', json_encode($e->errors));
        }
    }

    public function testReportsAllErrorsAtOnce(): void
    {
        try {
            (new PayloadValidator())->validate([]);
            $this->fail('Expected InvalidPayload');
        } catch (InvalidPayload $e) {
            $this->assertGreaterThanOrEqual(10, count($e->errors));
        }
    }
}
