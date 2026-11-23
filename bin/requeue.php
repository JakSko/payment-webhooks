<?php

declare(strict_types=1);

// Moves a notification from the dead letter storage back to the queue.
// Usage: docker compose exec worker php bin/requeue.php <event_id>

use App\Config;
use App\Notification\Outbox;
use App\Webhook\PayloadValidator;

require __DIR__ . '/../vendor/autoload.php';

$eventId = $argv[1] ?? '';
if (preg_match(PayloadValidator::ID_PATTERN, $eventId) !== 1) {
    fwrite(STDERR, "Usage: php bin/requeue.php <event_id>\n");
    exit(2);
}

$outbox = new Outbox(Config::fromEnv()->createPdo());
if (!$outbox->requeue($eventId)) {
    fwrite(STDERR, "No dead notification for {$eventId}\n");
    exit(1);
}

echo "Requeued {$eventId}\n";
