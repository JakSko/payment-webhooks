# Plan
Estymacja: ok. 100 min (etap 3: 80 min, etap 4: 15 min, etap 5: 5 min). Start etapu 3: 10:28.
Główne ryzyka:
1. Czas. Krok 7 (out-of-order w kodzie) wypada pierwszy, a e2e z dwoma workerami drugie.
2. Budowanie obrazu (sieć, apt, Composer) i końce linii na Windows (CRLF w skryptach dla kontenera). Ograniczamy to przez `.gitattributes` w kroku 2 i przez zbudowanie obrazu wcześnie.

Każdy krok: najpierw test lub powtarzalny dowód, potem minimalna zmiana, przegląd diffu przez Jakuba, bramki (`php -l`, PHPUnit, `scan-diff.sh f638990`), commit z `A<n>`.

| # | Krok | Dowodzi | Dowód | Est. (min) | Status |
|---|---|---|---|---|---|
| 1 | `ARCHITECTURE.md`: diagram, 4 scenariusze (problem, rozwiązanie, plik, dowód), semantyka dostarczenia, kompromis Q1, dlaczego bez brokera | A11 (część) | przegląd Jakuba | 10 | |
| 2 | Szkielet: `.gitattributes`, `.gitignore`, `Dockerfile`, `docker-compose.yml`, `composer.json`, `db/schema.sql`, `public/index.php` z `/health` | A1 | `docker compose up -d --build`, `curl localhost:8080/health` daje 200, `docker compose ps` | 10 | |
| 3 | `SignatureVerifier` + `PayloadValidator` z testami jednostkowymi (najpierw testy) | A4, A5 | PHPUnit: najpierw czerwony, potem zielony | 10 | |
| 4 | `EventStore` + `WebhookController`: idempotentny zapis + outbox w jednej transakcji, kody odpowiedzi, log JSON bez danych osobowych | A2, A3, A10 | curl: 202, potem 200 duplicate; SELECT count | 10 | |
| 5 | `RetryPolicy` (najpierw testy) + `HttpNotifierClient` + `NotificationDispatcher` + `bin/worker.php` + `bin/requeue.php` + `mock/notifier.php` | A6, A7, A8 | PHPUnit, powiadomienie w `GET :8081/notifications` | 15 | |
| 6 | `bin/send-test-webhook.php` + `tests/e2e.sh`: happy path, wyścig 20 żądań, 401, 400/422, `down` -> dead -> requeue -> sent, `reject` -> dead | A2-A8, A10 | wynik e2e na ekranie | 10 | |
| 7 | (opcjonalnie) tabela `payments` z monotonicznym statusem | A9 | e2e out-of-order | 10 | |
| 8 | `README.md` krok po kroku + curl (PHP w kontenerze oraz bash + openssl) | A12 | przejście od `docker compose down -v` | 5 | |
| 9 | Etap 4: bramki, `code-reviewer`, poprawki, `AUDIT.md` | A11 | raport recenzenta, zielone bramki | 15 | |
| 10 | Etap 5: `sdd/przekazanie.md`, `sdd/podsumowanie.md`, udostępnienie (Q2) | - | pliki, decyzja Jakuba | 5 | |
