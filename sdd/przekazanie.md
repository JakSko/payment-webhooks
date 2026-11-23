# Przekazanie: Payment Webhook Ingestion & Notification Pipeline

## Co zbudowaliśmy i dlaczego
- `public/index.php`, `src/Http/WebhookController.php`: endpoint `POST /api/v1/webhooks/payment`. Sprawdza metodę, rozmiar, typ treści, podpis, JSON i pola, potem zapisuje. Nie wywołuje API zewnętrznych.
- `src/Security/SignatureVerifier.php`: HMAC-SHA256 z `"<t>.<body>"`, `hash_equals`, tolerancja 300 s.
- `src/Webhook/PayloadValidator.php`, `PaymentEvent.php`: walidacja z listą ścieżek pól, bez mapowania danych osobowych.
- `src/Webhook/EventStore.php`: `ON CONFLICT DO NOTHING` + outbox w jednej transakcji (idempotentność i odporność na wyścig).
- `src/Notification/*`, `bin/worker.php`: worker z dzierżawą (`SKIP LOCKED`), backoffem z jitterem, DLQ (`status = dead`), `bin/requeue.php`.
- `mock/notifier.php`: atrapa zewnętrznego API (tryby up, down, reject, slow; deduplikacja po `Idempotency-Key`).
- Historia: `git log` (każdy krok to osobny commit z numerem kryterium `A<n>`).

## Decyzje projektowe
- Kolejka w PostgreSQL (Transactional Outbox) zamiast brokera. Alternatywa: RabbitMQ lub SQS. Powód: atomowy zapis ze zdarzeniem, brak dodatkowej infrastruktury przy obecnej skali (`ARCHITECTURE.md` 7).
- Czysty PHP + Composer zamiast frameworka. Powód: mało kodu do audytu, PHPUnit jako jedyna zależność dev.
- Uszkodzona treść przy poprawnym podpisie dostaje 4xx, a nie kwarantannę. Powód: nie przechowujemy danych osobowych z uszkodzonych payloadów (`ARCHITECTURE.md` 4.4).
- Ten sam `event_id` z inną treścią dostaje 200 i log `error`, a nie 409. Powód: odpowiedź inna niż 2xx powoduje, że operator ponawia w nieskończoność.
- Wysyłka działa "co najmniej raz" z `Idempotency-Key`, bo "dokładnie raz" wymaga współpracy odbiorcy.

## Jak uruchomić i przetestować
- `docker compose up` (porty: `APP_PORT`, `MOCK_PORT`), curl z podpisem: `README.md` sekcja 2.
- Testy: `docker compose run --rm --no-deps app vendor/bin/phpunit` (40 testów).
- Scenariusze ręczne: `README.md` sekcja 3. Dowody z sesji są w `sdd/dziennik.md`.

## Ryzyka i ograniczenia
- Brak automatycznego testu integracyjnego (e2e). Wyścig, retry, DLQ i dwa workery sprawdziliśmy ręcznie.
- Out-of-order (tabela `payments` ze statusem monotonicznym) jest tylko opisane w `ARCHITECTURE.md` 4.2.
- Schemat podpisu jest generyczny (wzorowany na Stripe). Nie ma też kontroli `livemode`.
- Domyślny harmonogram ponowień (6 prób, około 1 min) jest ustawiony pod demo.
- Schemat bazy ładuje się tylko przy pustym wolumenie (`docker-entrypoint-initdb.d`). Kolejne zmiany schematu wymagają narzędzia do migracji.

## Rekomendacje do CI/CD i wdrożenia
- Bramki w CI blokujące merge: PHPUnit, `php -l`, PHPStan, `composer audit`, skan sekretów i danych osobowych w diffie.
- Test e2e w CI na `docker compose`: wyścig 20 żądań, 401, 400 i 422, mock `down` aż do `dead`, dwa workery.
- Środowisko podglądowe dla PR z atrapą usługi powiadomień. Przy pierwszym wdrożeniu produkcyjnym zgoda człowieka, bo chodzi o płatności.
- Monitoring i alerty: liczba zadań w `dead`, wiek najstarszego `pending`, logi `error` (w tym "different payload"), odsetek 4xx na endpoincie.
- Produkcyjny build bez dev (`composer install --no-dev`), sekrety z menedżera sekretów, TLS i rate limiting na reverse proxy.

## Następne kroki (priorytet)
1. Format podpisu wybranego operatora (Stripe lub PayU) i kontrola `livemode`.
2. Skrypt e2e w CI.
3. Dłuższy horyzont ponowień na produkcję, alert na `dead` i prosty podgląd DLQ.
4. Tabela `payments` (out-of-order w kodzie), jeśli klient potrzebuje stanu płatności.
5. Retencja `webhook_events` i `notification_outbox` (do ustalenia z klientem).
