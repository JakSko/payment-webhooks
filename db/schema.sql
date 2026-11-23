-- Runs once, on the first start of an empty database volume (docker-entrypoint-initdb.d).
-- Personal data from the payload (email, names, company) is intentionally not stored.

CREATE TABLE webhook_events (
    event_id        text PRIMARY KEY,               -- idempotency key from the provider
    event_type      text        NOT NULL,
    transaction_id  text        NOT NULL,
    order_id        text        NOT NULL,
    amount_minor    bigint      NOT NULL CHECK (amount_minor >= 0),  -- grosze, never float
    currency        char(3)     NOT NULL,
    payment_method  text        NOT NULL,
    store_id        text        NOT NULL,
    channel         text        NOT NULL,
    customer_id     text        NOT NULL,
    payload_hash    char(64)    NOT NULL,           -- sha256 of the raw body, detects same id with different content
    occurred_at     timestamptz NOT NULL,           -- provider timestamp
    received_at     timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX webhook_events_transaction_id_idx ON webhook_events (transaction_id);

-- Transactional outbox: written in the same transaction as the event, consumed by the worker.
-- status 'dead' is the Dead Letter Storage.
CREATE TABLE notification_outbox (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    event_id        text        NOT NULL UNIQUE REFERENCES webhook_events (event_id),
    payload         jsonb       NOT NULL,           -- notification body, no personal data
    status          text        NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending', 'processing', 'sent', 'dead')),
    attempts        integer     NOT NULL DEFAULT 0,
    next_attempt_at timestamptz NOT NULL DEFAULT now(),
    locked_until    timestamptz,
    last_error      text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    sent_at         timestamptz
);

CREATE INDEX notification_outbox_due_idx ON notification_outbox (status, next_attempt_at);
