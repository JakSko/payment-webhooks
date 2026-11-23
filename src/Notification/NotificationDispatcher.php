<?php

declare(strict_types=1);

namespace App\Notification;

use App\JsonLogger;
use Throwable;

/** One worker iteration: claim due jobs, send each, record sent / retry later / dead. */
final class NotificationDispatcher
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly NotifierClient $client,
        private readonly RetryPolicy $retryPolicy,
        private readonly JsonLogger $logger,
        private readonly int $batchSize = 10,
        private readonly int $leaseSeconds = 60,
    ) {
    }

    /** @return int number of jobs handled */
    public function dispatchBatch(): int
    {
        $jobs = $this->outbox->claimDue($this->batchSize, $this->leaseSeconds);

        foreach ($jobs as $job) {
            $log = ['event_id' => $job['event_id'], 'attempt' => $job['attempts']];

            // A failure of one job must not stop the loop (poison pill isolation).
            try {
                $result = $this->client->send($job['event_id'], $job['payload']);
            } catch (Throwable $e) {
                $result = new SendResult(null, $e::class);
            }

            if ($result->isSuccess()) {
                $this->outbox->markSent($job['id'], $job['attempts']);
                $this->logger->info('notification sent', $log);
                continue;
            }

            if ($this->retryPolicy->shouldRetry($result, $job['attempts'])) {
                $delay = $this->retryPolicy->delaySeconds($job['attempts']);
                $this->outbox->scheduleRetry($job['id'], $job['attempts'], $delay, $result->describe());
                $this->logger->warning('notification failed, retry scheduled', $log + ['error' => $result->describe(), 'retry_in_seconds' => $delay]);
                continue;
            }

            $this->outbox->markDead($job['id'], $job['attempts'], $result->describe());
            $this->logger->error('notification moved to dead letter storage', $log + ['error' => $result->describe()]);
        }

        return count($jobs);
    }
}
