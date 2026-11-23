<?php

declare(strict_types=1);

namespace App\Webhook;

use PDO;
use Throwable;

/**
 * Idempotent event persistence with a transactional outbox.
 *
 * Race safety comes from the database, not from a check-then-insert in PHP:
 * with two concurrent inserts of the same event_id, PostgreSQL makes the second one
 * wait for the first transaction; after its commit, ON CONFLICT DO NOTHING returns no row.
 * The outbox row is written only by the transaction that actually inserted the event.
 */
final class EventStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed>|null $notification outbox payload, null when the event needs no notification */
    public function record(PaymentEvent $event, string $payloadHash, ?array $notification): RecordResult
    {
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO webhook_events (event_id, event_type, transaction_id, order_id, amount_minor, currency,
                     payment_method, store_id, channel, customer_id, payload_hash, occurred_at)
                 VALUES (:event_id, :event_type, :transaction_id, :order_id, :amount_minor, :currency,
                     :payment_method, :store_id, :channel, :customer_id, :payload_hash, to_timestamp(:occurred_at))
                 ON CONFLICT (event_id) DO NOTHING
                 RETURNING event_id'
            );
            $insert->execute([
                'event_id' => $event->eventId,
                'event_type' => $event->eventType,
                'transaction_id' => $event->transactionId,
                'order_id' => $event->orderId,
                'amount_minor' => $event->amountMinor,
                'currency' => $event->currency,
                'payment_method' => $event->paymentMethod,
                'store_id' => $event->storeId,
                'channel' => $event->channel,
                'customer_id' => $event->customerId,
                'payload_hash' => $payloadHash,
                'occurred_at' => $event->occurredAt,
            ]);

            if ($insert->fetchColumn() === false) {
                $this->pdo->rollBack();
                return $this->duplicateOf($event->eventId, $payloadHash);
            }

            if ($notification !== null) {
                $this->pdo->prepare(
                    'INSERT INTO notification_outbox (event_id, payload) VALUES (:event_id, CAST(:payload AS jsonb))'
                )->execute([
                    'event_id' => $event->eventId,
                    'payload' => json_encode($notification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $this->pdo->commit();
            return RecordResult::Recorded;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function duplicateOf(string $eventId, string $payloadHash): RecordResult
    {
        $select = $this->pdo->prepare('SELECT payload_hash FROM webhook_events WHERE event_id = :event_id');
        $select->execute(['event_id' => $eventId]);

        return hash_equals((string) $select->fetchColumn(), $payloadHash)
            ? RecordResult::Duplicate
            : RecordResult::ConflictingDuplicate;
    }
}
