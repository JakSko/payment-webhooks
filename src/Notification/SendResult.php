<?php

declare(strict_types=1);

namespace App\Notification;

/** Outcome of one delivery attempt: an HTTP status, or a transport error (timeout, connection refused). */
final class SendResult
{
    public function __construct(
        public readonly ?int $statusCode,
        public readonly ?string $transportError = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->statusCode !== null && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** Short, data-free description stored in last_error. */
    public function describe(): string
    {
        return $this->statusCode !== null
            ? 'HTTP ' . $this->statusCode
            : 'transport error: ' . substr((string) $this->transportError, 0, 200);
    }
}
