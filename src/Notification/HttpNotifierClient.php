<?php

declare(strict_types=1);

namespace App\Notification;

/** Sends the notification as JSON over HTTP. Delivery is at-least-once, so the receiver dedupes by Idempotency-Key. */
final class HttpNotifierClient implements NotifierClient
{
    public function __construct(
        private readonly string $url,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function send(string $idempotencyKey, string $jsonBody): SendResult
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Idempotency-Key: ' . $idempotencyKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $response = curl_exec($curl);
        $result = $response === false
            ? new SendResult(null, curl_error($curl))
            : new SendResult(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        curl_close($curl);

        return $result;
    }
}
