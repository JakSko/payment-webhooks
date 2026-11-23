<?php

declare(strict_types=1);

namespace App\Webhook;

enum RecordResult
{
    case Recorded;
    case Duplicate;
    /** Same event_id, different body: possible provider bug or abuse, logged as a warning. */
    case ConflictingDuplicate;
}
