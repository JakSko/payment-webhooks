<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/** The reason is for internal logs only; the HTTP response stays a generic 401. */
final class InvalidSignature extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Invalid webhook signature: ' . $reason);
    }
}
