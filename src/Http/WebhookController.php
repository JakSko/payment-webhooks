<?php

declare(strict_types=1);

namespace App\Http;

use App\JsonLogger;
use App\Security\InvalidSignature;
use App\Security\SignatureVerifier;
use App\Webhook\EventStore;
use App\Webhook\InvalidPayload;
use App\Webhook\PayloadValidator;
use App\Webhook\PaymentEvent;
use App\Webhook\RecordResult;
use JsonException;

/**
 * POST /api/v1/webhooks/payment. Only verifies, validates and records;
 * the notification is sent later by the worker, so this endpoint never waits on external APIs.
 */
final class WebhookController
{
    public const MAX_BODY_BYTES = 65536;

    /** Event types that notify the sales team; other valid events are stored only. */
    private const NOTIFIABLE_EVENTS = ['charge.successful'];

    public function __construct(
        private readonly SignatureVerifier $signatureVerifier,
        private readonly PayloadValidator $validator,
        private readonly EventStore $store,
        private readonly JsonLogger $logger,
    ) {
    }

    /** @return array{int, array<string, mixed>} HTTP status and JSON body */
    public function handle(
        string $method,
        string $contentType,
        string $signatureHeader,
        string $body,
        string $requestId,
        int $now,
    ): array {
        $log = ['request_id' => $requestId];

        if ($method !== 'POST') {
            return [405, ['error' => 'method_not_allowed']];
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'payload_too_large']);
            return [413, ['error' => 'payload_too_large']];
        }
        if (!str_starts_with(strtolower(trim($contentType)), 'application/json')) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'unsupported_media_type']);
            return [415, ['error' => 'unsupported_media_type']];
        }

        // Signature before parsing: unauthenticated input never reaches the JSON parser or the database.
        try {
            $this->signatureVerifier->verify($signatureHeader, $body, $now);
        } catch (InvalidSignature $e) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'invalid_signature', 'detail' => $e->reason]);
            return [401, ['error' => 'invalid_signature']];
        }

        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'malformed_json']);
            return [400, ['error' => 'malformed_json']];
        }
        if (!is_array($payload)) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'malformed_json']);
            return [400, ['error' => 'malformed_json']];
        }

        $rawEventId = $payload['event_id'] ?? null;
        if (is_string($rawEventId) && preg_match(PayloadValidator::ID_PATTERN, $rawEventId) === 1) {
            $log['event_id'] = $rawEventId;
        }

        try {
            $event = $this->validator->validate($payload);
        } catch (InvalidPayload $e) {
            $this->logger->warning('webhook rejected', $log + ['reason' => 'validation_failed', 'errors' => array_keys($e->errors)]);
            return [422, ['error' => 'validation_failed', 'details' => $e->errors]];
        }

        $notify = in_array($event->eventType, self::NOTIFIABLE_EVENTS, true);
        $result = $this->store->record($event, hash('sha256', $body), $notify ? $this->notificationFor($event) : null);

        $log += ['event_type' => $event->eventType, 'order_id' => $event->orderId];
        if ($result === RecordResult::Recorded) {
            $this->logger->info('webhook accepted', $log + ['notification_queued' => $notify]);
            return [202, ['status' => 'accepted']];
        }

        if ($result === RecordResult::ConflictingDuplicate) {
            $this->logger->warning('duplicate event_id with different payload', $log);
        } else {
            $this->logger->info('duplicate webhook ignored', $log);
        }
        return [200, ['status' => 'duplicate']];
    }

    /** @return array<string, mixed> Notification body for the sales team: no personal data. */
    private function notificationFor(PaymentEvent $event): array
    {
        return [
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'order_id' => $event->orderId,
            'transaction_id' => $event->transactionId,
            'amount' => ['value_minor' => $event->amountMinor, 'currency' => $event->currency],
            'payment_method' => $event->paymentMethod,
            'store_id' => $event->storeId,
            'channel' => $event->channel,
            'livemode' => $event->livemode,
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z', $event->occurredAt),
        ];
    }
}
