<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

/**
 * HMAC-SHA256 webhook signature, header format: "t=<unix>,v1=<hex>".
 * The signed message is "<t>.<raw body>", so the timestamp cannot be swapped (replay protection).
 * Modelled on Stripe; replace this class once the real provider is chosen.
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Webhook secret must not be empty');
        }
    }

    /** @throws InvalidSignature */
    public function verify(string $header, string $body, int $now): void
    {
        if ($header === '') {
            throw new InvalidSignature('missing');
        }

        if (preg_match('/^t=(\d{1,12}),v1=([a-f0-9]{64})$/', $header, $m) !== 1) {
            throw new InvalidSignature('malformed');
        }

        $timestamp = (int) $m[1];
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            throw new InvalidSignature('expired');
        }

        if (!hash_equals($this->hmac($body, $timestamp), $m[2])) {
            throw new InvalidSignature('mismatch');
        }
    }

    /** Builds a header value; used by tests. */
    public function sign(string $body, int $timestamp): string
    {
        return sprintf('t=%d,v1=%s', $timestamp, $this->hmac($body, $timestamp));
    }

    private function hmac(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $this->secret);
    }
}
