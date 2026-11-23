<?php

declare(strict_types=1);

use App\Config;
use App\JsonLogger;
use App\Notification\HttpNotifierClient;
use App\Notification\NotificationDispatcher;
use App\Notification\Outbox;
use App\Notification\RetryPolicy;

require __DIR__ . '/../vendor/autoload.php';

$logger = new JsonLogger();

try {
    $config = Config::fromEnv();
    $dispatcher = new NotificationDispatcher(
        new Outbox($config->createPdo()),
        new HttpNotifierClient($config->notifierUrl),
        new RetryPolicy($config->retryMaxAttempts, $config->retryBaseSeconds, $config->retryCapSeconds),
        $logger,
    );
    $logger->info('worker started', ['retry_max_attempts' => $config->retryMaxAttempts]);

    while (true) {
        if ($dispatcher->dispatchBatch() === 0) {
            usleep(500_000);
        }
    }
} catch (Throwable $e) {
    // Crash-only: on a database or config error the process exits and Docker restarts it.
    // Jobs in progress are not lost: their lease expires and another run picks them up.
    $logger->error('worker stopped', ['exception' => $e::class, 'error' => $e->getMessage()]);
    exit(1);
}
