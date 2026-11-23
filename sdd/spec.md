# Spec: Payment Webhook Ingestion & Notification Pipeline (część kodowa)

Status: ZAAKCEPTOWANA przez Jakuba (2026-11-23), z założeniami do Q1-Q3. Źródło wymagań: `sdd/zadanie.md` (W1-W12). Mail do klienta jest gotowy (`EMAIL_TO_CLIENT.md`), więc ten dokument dotyczy architektury, kodu, audytu, dockera i udostępnienia.

## Cel
Operator płatności wysyła webhook o udanej płatności. System przyjmuje go bezpiecznie i dokładnie raz, a zespół sprzedaży dostaje powiadomienie także wtedy, gdy usługa powiadomień chwilowo nie działa. Całość uruchamia się jedną komendą `docker compose up`.

## Kontekst
- Projekt od zera. Klient nie przekazał kodu, więc nie ma istniejących klas do ponownego użycia. Składamy rozwiązanie z mechanizmów platformy (sekcja "Elementy").
- Stack: PHP 8.3, czysty PHP + Composer (autoload PSR-4, PHPUnit tylko w dev), PostgreSQL 16, Apache (`php:8.3-apache`), Docker Compose.
- Uruchomienie: `docker compose up`. Testy: `docker compose run --rm app vendor/bin/phpunit` oraz `tests/e2e.sh`.
- Na hoście nie ma PHP, więc wszystko działa w kontenerach. Na pobranie obrazów i pakietów przy budowaniu Jakub już się zgodził.

## Wymagania
- R1 Endpoint `POST /api/v1/webhooks/payment` przyjmuje payload z treści zadania i odpowiada szybko, bez wysyłania powiadomienia w trakcie żądania (W7).
- R2 Endpoint przyjmuje tylko żądania z poprawnym podpisem HMAC i świeżym znacznikiem czasu (W8).
- R3 To samo `event_id` jest zapisane i powiadomione najwyżej raz, także przy równoczesnych żądaniach (W2).
- R4 Uszkodzone lub niekompletne żądania dostają czytelną odpowiedź 4xx. Log zawiera identyfikator żądania i powód, bez danych osobowych (W6).
- R5 Asynchroniczny worker wysyła powiadomienie do zewnętrznej usługi (mock) (W7).
- R6 Przy błędach przejściowych (5xx, timeout, 429) worker ponawia z wykładniczym odstępem. Po wyczerpaniu prób zadanie trafia do Dead Letter Storage. Błąd trwały (inne 4xx) trafia tam od razu (W3, W4).
- R7 Kolejność zdarzeń nie psuje stanu płatności (W5). Opis w architekturze obowiązkowo, w kodzie, jeśli zostanie czas.
- R8 `docker compose up` stawia db, app (PHP + www), worker i mock (W10).
- R9 `ARCHITECTURE.md` opisuje 4 scenariusze poza happy path. `AUDIT.md` opisuje audyt kodu. `README.md` prowadzi krok po kroku do testowego `curl` (W11).
- R10 Kod jest minimalny: bez zbędnych klas, abstrakcji i plików (W9).

## Ograniczenia
- Bez frameworka i bez zależności produkcyjnych. Jedyna zależność dev to PHPUnit.
- Bez prawdziwych usług zewnętrznych: operatora płatności i usługi powiadomień zastępują lokalny skrypt i mock.
- Tylko dane syntetyczne (`example.com`). Sekret webhooka nie trafia do repo jako prawdziwy: w compose jest jawnie lokalna wartość deweloperska.
- Czas: ok. 60 min na kod i sprawdzenie. Krok opcjonalny (R7 w kodzie) wypada pierwszy.

## Uzgodnienie
- **Zakres:** endpoint, worker, mock, baza, docker compose, dokumenty architektury, audytu i README. **Powód:** to są deliverables z treści zadania.
- **Efekt:** reviewer klonuje repo, robi `docker compose up`, wysyła podpisany `curl` i widzi powiadomienie w mocku. Duplikat, zły podpis, uszkodzony payload i awaria mocka zachowują się zgodnie z opisem. **Powód:** "gotowy do przetestowania po jednej komendzie".
- **Granice:** bez brokera, bez wielu kanałów, bez AI i bez wdrożenia produkcyjnego. **Powód:** decyzja z maila do klienta (etapy 2-4 po potwierdzeniu potrzeby).
- **Dowód:** testy jednostkowe, skrypt e2e z wyścigiem 20 równoległych żądań, zapytania SQL o stan tabel i log mocka.

## Elementy, z których składamy
Klient nie przekazał kodu, więc używamy sprawdzonych mechanizmów platformy, zamiast pisać własne:
- Unikalny klucz PostgreSQL + `INSERT ... ON CONFLICT DO NOTHING`: idempotentność i odporność na wyścig bez własnych blokad.
- Transakcja PostgreSQL: zdarzenie i zadanie powiadomienia zapisujemy atomowo (transactional outbox).
- `SELECT ... FOR UPDATE SKIP LOCKED`: wielu workerów nie weźmie tego samego zadania. Kolejka w bazie zamiast brokera.
- `hash_hmac` + `hash_equals` (PHP): podpis odporny na ataki czasowe.
- PDO z prepared statements: zero SQL sklejanego z danymi.
- Rozszerzenie `curl` (PHP): klient HTTP z timeoutem, bez Guzzle.
- `docker-entrypoint-initdb.d` w obrazie Postgresa: schemat bazy bez własnego narzędzia do migracji.
- Healthcheck i `depends_on: condition: service_healthy` w Compose: kolejność startu bez skryptów czekających.

## Podejście
- **Rozwiązanie:** endpoint weryfikuje podpis, waliduje payload i w jednej transakcji zapisuje zdarzenie oraz zadanie w tabeli outbox. Odpowiada 202 (nowe) albo 200 (duplikat). Worker w pętli pobiera zadania z dzierżawą, wysyła je do mocka z kluczem idempotentności i ustawia status: wysłane, ponów później albo dead.
- **Wzorce projektowe:**
  - Transactional Outbox: zapis zdarzenia i zadania jest atomowy, więc nie da się zapisać płatności i zgubić powiadomienia. Zastępuje brokera przy obecnej skali.
  - Repository (`EventStore`, `OutboxRepository` lub jedna klasa, decyzja w kodzie): cały SQL w jednym miejscu.
  - Adapter (`NotifierClient` + `HttpNotifierClient`): zewnętrzna usługa za interfejsem. W testach zastępuje ją atrapa, a kolejny kanał (etap 2) to kolejna implementacja.
  - DI przez konstruktor, składanie obiektów w `public/index.php` i `bin/worker.php` bez kontenera DI.
- **Semantyka dostarczenia:** przyjęcie zdarzenia jest dokładnie raz (unikalny `event_id`). Wysyłka powiadomienia jest co najmniej raz: worker może paść po wysłaniu, a przed zapisem statusu. Dlatego wysyłamy nagłówek `Idempotency-Key: <event_id>`, a mock po nim deduplikuje.
- **Podpis:** nagłówek `X-Signature: t=<unix>,v1=<hex>`, HMAC-SHA256 z `"<t>.<surowe body>"`, tolerancja 300 s przeciw powtórzeniu. Schemat wzorowany na Stripe, bo docelowy operator nie jest jeszcze znany (pytanie 5 w mailu).
- **Odpowiedzi endpointu:** 202 nowe zdarzenie, 200 duplikat, 400 niepoprawny JSON, 401 podpis, 405 metoda, 413 za duże body (powyżej 64 KB), 415 zły Content-Type, 422 brak lub zły typ pól (lista ścieżek pól, bez wartości), 500 błąd wewnętrzny (tylko `request_id`). Nieznany typ zdarzenia zapisujemy bez powiadomienia i odpowiadamy 202, żeby operator nie ponawiał go w kółko.
- **Retry:** opóźnienie `min(2^(n-1) * 2 s, 300 s)` plus jitter do 20%. Maksymalnie 6 prób, potem status `dead`. Ponawiamy przy 5xx, 408, 429, timeoucie i błędzie połączenia. Inne 4xx trafiają od razu do `dead`. Komenda `bin/requeue.php <event_id>` przywraca zadanie z DLQ.
- **Dane wrażliwe:** w bazie zapisujemy tylko pola potrzebne do powiadomienia i stanu płatności: id transakcji, zamówienia i klienta, kwotę, walutę, metodę płatności, sklep i kanał, oraz hash payloadu. E-maila, imienia, nazwiska i firmy nie zapisujemy. Logi są w formacie JSON i zawierają tylko identyfikatory. Powiadomienie nie zawiera danych osobowych. Sekret jest w zmiennej środowiskowej, a jego brak zatrzymuje start aplikacji.
- **Kwoty:** liczba całkowita w groszach plus waluta, nigdy float.

## Kryteria akceptacji
- A1 `docker compose up` stawia db, app, worker i notifier-mock. Dowód: `curl -s localhost:8080/health` zwraca `{"status":"ok"}` (200), a `docker compose ps` pokazuje 4 działające usługi.
- A2 Poprawny podpisany webhook z payloadem z zadania daje 202, a mock otrzymuje dokładnie 1 powiadomienie z `order_id` `ORD-2024-8839` i kwotą 29990 PLN. Dowód: `bin/send-test-webhook.php`, `GET localhost:8081/notifications`.
- A3 20 równoległych identycznych żądań (to samo `event_id`) daje 1 odpowiedź 202 i 19 odpowiedzi 200 `duplicate`, 1 wiersz w `webhook_events`, 1 w `notification_outbox` i 1 powiadomienie w mocku. Dowód: `tests/e2e.sh` (sekcja race) i `SELECT count(*)`.
- A4 Brak nagłówka, zły podpis albo `t` starszy niż 300 s daje 401, a w bazie nie pojawia się żaden wiersz. Dowód: testy jednostkowe `SignatureVerifierTest`, e2e.
- A5 Niepoprawny JSON daje 400, brak wymaganych pól albo zły typ (np. kwota jako string) daje 422 z listą ścieżek pól. Log zawiera `request_id` i powód, bez danych osobowych i bez treści żądania. Dowód: `PayloadValidatorTest`, e2e, `docker compose logs app`.
- A6 Gdy mock jest w trybie `down` (503), `attempts` rośnie, `next_attempt_at` się oddala, a po 6 próbach status zmienia się na `dead`. Po przełączeniu na `up` i `bin/requeue.php` status zmienia się na `sent`. Dowód: `RetryPolicyTest` (opóźnienia i klasyfikacja), e2e z krótkimi opóźnieniami w trybie testowym.
- A7 Odpowiedź 4xx z mocka (tryb `reject`) od razu daje status `dead`, a kolejne zadania są dalej wysyłane. Dowód: `RetryPolicyTest`, e2e.
- A8 Dwa workery nie wysyłają tego samego zadania, a padnięty worker oddaje zadanie po wygaśnięciu dzierżawy. Dowód: zapytanie z `FOR UPDATE SKIP LOCKED` w diffie oraz e2e z `docker compose up --scale worker=2` i liczbą powiadomień równą liczbie zdarzeń.
- A9 (opcjonalne) Gdy `charge.successful` przychodzi przed `payment_intent.created`, status płatności zostaje `COMPLETED` i nie cofa się. Dowód: e2e i `SELECT status FROM payments`.
- A10 W bazie i logach nie ma e-maila, imienia ani nazwiska z payloadu. Dowód: `scan-diff.sh` oraz `docker compose logs | grep -c example.com` = 0 i `SELECT` kolumn tabel.
- A11 `ARCHITECTURE.md` opisuje 4 scenariusze: problem, rozwiązanie, plik i dowód. `AUDIT.md` opisuje, co sprawdzono (wyścig, zabezpieczenie endpointu, sprzątanie), co poprawiono i co zostaje poza zakresem. Dowód: same pliki i raport `code-reviewer`.
- A12 `README.md` prowadzi od klonowania przez `docker compose up` do testowego `curl` z podpisem, w obu wariantach: skrypt PHP w kontenerze oraz bash + openssl. Dowód: przejście README od zera na czystym `docker compose down -v`.

## Poza zakresem
- Message Broker, kolejne kanały, raporty AI (etapy 2-4 z maila).
- Prawdziwy format podpisu Stripe lub PayU, bo operator jest nieznany. Weryfikator to jedna klasa do podmiany.
- Kontrola `livemode` (odrzucanie zdarzeń testowych na produkcji). Trafia do przekazania jako pierwsza rzecz po wyborze operatora.
- Wdrożenie produkcyjne, TLS, rate limiting, monitoring i alerty (rekomendacje w `sdd/przekazanie.md`).
- Szyfrowanie danych w spoczynku i retencja (do ustalenia z klientem).

## Pytania otwarte
- Q1 Co zwracać przy poprawnym podpisie, ale uszkodzonej treści: 4xx (operator ponawia, a my widzimy błąd w jego panelu) czy 2xx z zapisem do kwarantanny (operator przestaje ponawiać)? (Założenie: 4xx, bo to błąd po stronie nadawcy, do wyjaśnienia z operatorem. W architekturze opisujemy kompromis.)
- Q2 Sposób udostępnienia: publiczne repo na GitHubie (push za Twoją zgodą) czy ZIP mailem? (Decyzja na końcu.)
- Q3 Czy uruchamiamy e2e z dwoma workerami (A8), czy wystarczy argument z kodu? (Założenie: e2e, jeśli zostanie czas.)
