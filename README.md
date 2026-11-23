# Payment Webhook Ingestion & Notification Pipeline

System przyjmuje webhooki płatności (`POST /api/v1/webhooks/payment`), zapisuje każde zdarzenie dokładnie raz i asynchronicznie wysyła powiadomienie do zespołu sprzedaży (mock zewnętrznego API). Stack: PHP 8.3 (czysty PHP + Composer), Apache, PostgreSQL 16, worker, Docker Compose.

Dokumenty: [ARCHITECTURE.md](ARCHITECTURE.md) (4 scenariusze poza happy path), [EMAIL_TO_CLIENT.md](EMAIL_TO_CLIENT.md), `sdd/` (spec, plan, dziennik pracy).

## 1. Uruchomienie

Wymagany jest tylko Docker.

```bash
docker compose up --build
```

Komenda uruchamia `db` (PostgreSQL ze schematem), `app` (PHP + Apache, port 8080), `worker` i `notifier-mock` (port 8081). Jeśli porty są zajęte: `APP_PORT=8090 MOCK_PORT=8091 docker compose up --build`.

```bash
curl http://localhost:8080/health      # {"status":"ok"}
```

## 2. Testowe zapytanie curl (podpisane)

Endpoint wymaga nagłówka `X-Signature: t=<unix>,v1=<HMAC-SHA256 z "<t>.<body>">`. Sekret lokalny jest w `docker-compose.yml`. W bashu (Linux, macOS, Git Bash):

```bash
SECRET=local-dev-only-webhook-secret
BODY=$(cat examples/charge-successful.json)
TS=$(date +%s)
SIG=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -i -X POST http://localhost:8080/api/v1/webhooks/payment \
  -H 'Content-Type: application/json' \
  -H "X-Signature: t=$TS,v1=$SIG" \
  --data-binary "$BODY"
```

Oczekiwany wynik: `202 {"status":"accepted"}`. Ponowne wysłanie daje `200 {"status":"duplicate"}`.

Powiadomienie odebrane przez mock:

```bash
curl http://localhost:8081/notifications
```

## 3. Scenariusze do sprawdzenia

| Scenariusz | Jak sprawdzić | Wynik |
|---|---|---|
| Duplikat | wyślij ten sam curl drugi raz | 200 `duplicate`, brak drugiego powiadomienia |
| Wyścig | 20 równoległych identycznych żądań (np. `xargs -P 20`) | 1×202 + 19×200, 1 wiersz w bazie |
| Zły / brak / stary podpis | zmień `SIG` albo `TS` (powyżej 300 s) | 401 |
| Uszkodzony JSON / brak pól | wyślij `{"event":` albo kwotę jako tekst | 400 / 422 z listą pól |
| Awaria API | `curl -X POST localhost:8081/mode -d '{"mode":"down"}'`, wyślij nowy event | ponowienia z backoffem, potem `dead` |
| Przywrócenie z DLQ | tryb `up`, potem `docker compose exec worker php bin/requeue.php <event_id>` | `sent` |
| Błąd trwały | tryb `reject` (400) | `dead` od razu, kolejka działa dalej |

Stan kolejki:

```bash
docker compose exec db psql -U app -d payments -c "select event_id, status, attempts, last_error from notification_outbox"
```

Szybsze ponowienia do demo: `RETRY_MAX_ATTEMPTS=4 RETRY_BASE_SECONDS=1 docker compose up -d worker`.

## 4. Testy

```bash
docker compose run --rm --no-deps app vendor/bin/phpunit
```

40 testów jednostkowych: podpis, walidacja, polityka ponowień, logowanie bez danych z wyjątków.

## 5. Audyt kodu

1. **Race condition (2 identyczne webhooki w tej samej milisekundzie).** O unikalności decyduje baza, a nie kod (`INSERT ... ON CONFLICT (event_id) DO NOTHING RETURNING`, `src/Webhook/EventStore.php`). Zadanie powiadomienia powstaje tylko w transakcji, która wstawiła zdarzenie, a `notification_outbox.event_id` ma drugi `UNIQUE`. Workery pobierają zadania z `FOR UPDATE SKIP LOCKED` i dzierżawą, a licznik prób chroni przed nadpisaniem wyniku (`src/Notification/Outbox.php`). Sprawdzone: 20 równoległych żądań daje 1 zdarzenie, 1 zadanie i 1 powiadomienie.
2. **Zabezpieczenie endpointu.** Podpis HMAC-SHA256 z `hash_equals`, tolerancja znacznika czasu 300 s (ochrona przed replay). Podpis sprawdzany jest przed parsowaniem JSON. Limit 64 KB, wymagany `Content-Type: application/json`. Pusty sekret zatrzymuje start (`src/Security/SignatureVerifier.php`, `src/Config.php`). SQL tylko przez PDO z parametrami. E-mail, imię, nazwisko i firma nie trafiają do bazy, logów ani powiadomień; zapisujemy tylko identyfikatory (zamówienie, transakcja, klient).
3. **Sprzątanie.** Bez frameworka i bez zależności produkcyjnych (PHPUnit tylko w dev). Jeden interfejs, tylko dla systemu zewnętrznego (`NotifierClient`, Adapter). Kolejką jest tabela w PostgreSQL zamiast brokera. Składanie zależności odbywa się w `public/index.php` i `bin/worker.php`, bez kontenera DI. Każdy plik w `src/` jest używany.

## 6. Ograniczenia (świadome)

- Brak automatycznego skryptu e2e. Scenariusze z tabeli w sekcji 3 sprawdziliśmy ręcznie (zapis w `sdd/dziennik.md`).
- Out-of-order (`charge.successful` przed intencją) jest opisany w `ARCHITECTURE.md` 4.2. Tabela `payments` ze statusem monotonicznym nie jest zaimplementowana.
- Format podpisu konkretnego operatora (Stripe / PayU), kontrola `livemode`, TLS, rate limiting i monitoring czekają na wdrożenie.
