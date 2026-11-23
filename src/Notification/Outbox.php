<?php

declare(strict_types=1);

namespace App\Notification;

use PDO;

/**
 * Worker-side access to notification_outbox (the queue and the dead letter storage).
 *
 * claimDue() takes jobs with FOR UPDATE SKIP LOCKED and a lease, so several workers never take
 * the same job, and a job of a crashed worker becomes due again when its lease expires.
 * The attempts value returned by claimDue() is a fencing token: a worker whose lease expired
 * and was taken over cannot overwrite the newer owner's result.
 */
final class Outbox
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, event_id: string, payload: string, attempts: int}> */
    public function claimDue(int $limit, int $leaseSeconds): array
    {
        $claim = $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'processing',
                    locked_until = now() + make_interval(secs => CAST(:lease AS integer)),
                    attempts = attempts + 1
              WHERE id IN (
                    SELECT id FROM notification_outbox
                     WHERE (status = 'pending' AND next_attempt_at <= now())
                        OR (status = 'processing' AND locked_until < now())
                     ORDER BY next_attempt_at
                     LIMIT :limit
                     FOR UPDATE SKIP LOCKED)
              RETURNING id, event_id, payload::text AS payload, attempts"
        );
        $claim->execute(['lease' => $leaseSeconds, 'limit' => $limit]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'event_id' => (string) $row['event_id'],
            'payload' => (string) $row['payload'],
            'attempts' => (int) $row['attempts'],
        ], $claim->fetchAll());
    }

    /**
     * A job whose lease expired after its last allowed attempt was most likely killing the worker
     * (fatal error, OOM). Without this it would be re-claimed forever; it goes to dead instead.
     * @return list<string> event ids moved to dead
     */
    public function reapExhausted(int $maxAttempts): array
    {
        $reap = $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'dead', locked_until = NULL,
                    last_error = 'lease expired after the last attempt (worker crashed?)'
              WHERE status = 'processing' AND locked_until < now() AND attempts >= :max_attempts
              RETURNING event_id"
        );
        $reap->execute(['max_attempts' => $maxAttempts]);

        return array_map('strval', $reap->fetchAll(PDO::FETCH_COLUMN));
    }

    public function markSent(int $id, int $attempts): void
    {
        $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'sent', sent_at = now(), locked_until = NULL, last_error = NULL
              WHERE id = :id AND status = 'processing' AND attempts = :attempts"
        )->execute(['id' => $id, 'attempts' => $attempts]);
    }

    public function scheduleRetry(int $id, int $attempts, int $delaySeconds, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'pending', locked_until = NULL, last_error = :error,
                    next_attempt_at = now() + make_interval(secs => CAST(:delay AS integer))
              WHERE id = :id AND status = 'processing' AND attempts = :attempts"
        )->execute(['id' => $id, 'attempts' => $attempts, 'delay' => $delaySeconds, 'error' => $error]);
    }

    public function markDead(int $id, int $attempts, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'dead', locked_until = NULL, last_error = :error
              WHERE id = :id AND status = 'processing' AND attempts = :attempts"
        )->execute(['id' => $id, 'attempts' => $attempts, 'error' => $error]);
    }

    /** Moves a dead job back to the queue with a fresh attempt budget. Returns false if no dead job exists. */
    public function requeue(string $eventId): bool
    {
        $requeue = $this->pdo->prepare(
            "UPDATE notification_outbox
                SET status = 'pending', attempts = 0, next_attempt_at = now(), last_error = NULL
              WHERE event_id = :event_id AND status = 'dead'"
        );
        $requeue->execute(['event_id' => $eventId]);

        return $requeue->rowCount() === 1;
    }
}
