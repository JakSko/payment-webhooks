<?php

declare(strict_types=1);

namespace App\Webhook;

use RuntimeException;

final class InvalidPayload extends RuntimeException
{
    /** @param array<string, string> $errors field path => rule, never the submitted value */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid webhook payload');
    }
}
