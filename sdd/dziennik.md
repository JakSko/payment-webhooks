# Dziennik

## 10:02 - Etap 0: Przyjęcie materiałów - przegląd
- Zrobione: przegląd folderu roboczego. Nie ma materiałów od klienta: wymagania przyszły jako tekst (Google Docs wklejony przez Jakuba w czacie). Projekt powstaje od zera w `C:\strix-weryfikacja\payment-webhooks`.
- Decyzja: skan Defenderem i `inspect-materials.sh` pominięte, bo nie ma plików do sprawdzenia. W treści zadania nie ma prób sterowania agentem.
- Dowód: listing folderu roboczego (tylko nasze `CLAUDE.md` i `.claude/`), `docker version` 29.2.1. Lokalne obrazy: `php:8.3-cli`, `composer:2`, `postgres:16`, `postgres:17`.
- AI: Claude przejrzał folder i środowisko. Jakub w tym czasie czytał kontekst i zadanie.

## 10:02 - Etap 1: Zrozumienie - zapis wymagań
- Zrobione: wymagania zapisane wiernie w `sdd/zadanie.md` razem z wytycznymi W1-W12, wyprowadzonymi wprost z treści. Subagent `requirements-analyst` (sama treść zadania, bez kodu) przygotował pytania, założenia, ryzyka i warianty, zapisane w `sdd/pytania.md`.
- Decyzja Jakuba: najpierw wspólne rozumienie wymagań, bez pośpiechu ze spec i kodem. Stack wstępnie: czysty PHP + Composer, PostgreSQL + Apache, pobieranie z sieci przy budowaniu obrazu dozwolone. Szczegóły omówimy później. Out-of-order trafi do kodu, jeśli zostanie czas.
- Dowód: `sdd/zadanie.md`, `sdd/pytania.md`.
- AI: Claude przygotował szkic techniczny (plan mode), ale za szybko przeszedł do rozwiązania. Jakub zatrzymał go na etapie rozumienia wymagań. Szkic czeka na rozmowę i nie jest realizowany.

## 10:12 - Etap 1: Zrozumienie - porównanie z rozumieniem Jakuba
- Zrobione: Jakub opisał zadanie w dwóch etapach (1: mail do klienta w tonie biznesowo-konsultingowym; 2: kod PHP wg wytycznych, 3 poziomy jakości, bezpieczeństwo, docker, udostępnienie) i wskazał 6 tematów do doprecyzowania.
- Porównanie: broker i idempotentność były w założeniach. Kanały: brakowało pytania o credentiale i dostępy. Raporty AI: brakowało pytania "jakie raporty" i naszej propozycji. "Na dzisiaj": nie było dopytane (demo czy produkcja). Nieprzerywalność: tylko w kodzie, bez infrastruktury (środowisko zapasowe, monitoring, dyżur). Subagent dodał: operator i podpis, dane osobowe w powiadomieniu, typy zdarzeń, wolumen, przejęcie projektu.
- Decyzja: mail łączy tematy Jakuba z pytaniami subagenta.
- AI: Jakub wychwycił 3 tematy, których Claude nie założył (kanały i dostępy, raporty AI, termin "na dzisiaj"), oraz infrastrukturalną stronę ciągłości działania.

## 10:14 - Etap 2: Mail do klienta - szkic
- Zrobione: szkic `EMAIL_TO_CLIENT.md`: przejęcie projektu, etapy 1-4 z warunkami, 7 grup pytań, propozycje raportów AI (liczby bez AI, model do opisu i interpretacji, zgoda i umowa powierzenia przed danymi), prośba o dostępy bezpiecznym kanałem, następny krok.
- Dowód: `EMAIL_TO_CLIENT.md`, czeka na przegląd Jakuba przed commitem.

## 10:15 - Etap 2: Mail do klienta - kontrola wobec wymagań
- Zrobione: Jakub kazał sprawdzić szkic wobec 3 wymagań z treści (sytuacja biznesowa, nierealne oczekiwania, przejęcie projektu). Luki: brak stawki biznesowej, nierealność wizji była tylko pośrednia, przy przejęciu brakowało czasu na wdrożenie się i rozmów z zaangażowanymi osobami, a "następny krok" zakładał budowę przed poznaniem istniejącego kodu.
- Poprawki: nowe sekcje "Jak rozumiem sytuację" (koszt zgubionego, zdublowanego i fałszywego powiadomienia), "Co jest realne na start" (wprost: całość na dziś jest nierealna, z powodami) oraz "Przejęcie projektu" (1-2 dni na wdrożenie się, rozmowy z poprzednim wykonawcą, właścicielem projektu i sprzedażą, dostępy bezpiecznym kanałem). Następne kroki: najpierw poznanie projektu, rdzeń buduję na tym, co już istnieje.
- AI: pierwsza wersja Claude'a nie sprawdziła się sama wobec wymagań z treści zadania. Jakub to wyłapał.

## 10:23 - Etap 2: Mail do klienta - poprawki Jakuba
- Zrobione: w następnych krokach dodane wcześniejsze spotkanie (30 min, przed rozpoczęciem prac) na omówienie treści maila. Spotkanie z demo rdzenia stało się drugim spotkaniem. Podpis: imię i nazwisko Jakuba, stanowisko Senior AI Engineer.
- Dowód: `git diff EMAIL_TO_CLIENT.md` (plik jeszcze nieśledzony, do przeglądu przed commitem).

## 10:26 - Etap 2: Spec części kodowej - szkic
- Zrobione: `sdd/spec.md` z wymaganiami R1-R10 (powiązanymi z W1-W12), uzgodnieniem z powodami, elementami platformy, z których składamy rozwiązanie, wzorcami (Transactional Outbox, Repository, Adapter) i kryteriami A1-A12 z dowodami.
- Decyzja: czysty PHP + Composer, PostgreSQL jako magazyn i kolejka, Apache. Bez frameworka i brokera. Powód: najmniej kodu do audytu (W9), a kolejka w bazie wystarcza przy obecnej skali.
- Dowód: `sdd/spec.md`, czeka na akceptację Jakuba. Otwarte pytania Q1-Q3.

## 10:28 - Etap 2: Spec - akceptacja
- Zrobione: Jakub zaakceptował `sdd/spec.md` razem z założeniami do Q1-Q3 (Q1: 4xx przy uszkodzonej treści, kompromis opisany w architekturze; Q2: decyzja na końcu; Q3: e2e z dwoma workerami, jeśli zostanie czas).
- Dowód: status w nagłówku `sdd/spec.md`.

## 10:29 - Etap 3: Plan
- Zrobione: `sdd/plan.md` z 10 krokami, estymacją ok. 100 min i dwoma ryzykami (czas, budowanie obrazu i CRLF).
- Decyzja: kolejność jak w treści zadania: najpierw koncepcja architektury, potem kod. Krok 7 jest pierwszy do wycięcia.

## 10:31 - Etap 3: Krok 1 - ARCHITECTURE.md
- Zrobione: komponenty z diagramem (mermaid), happy path, model danych, 4 scenariusze (problem, rozwiązanie, odrzucone alternatywy, kod i dowód), bezpieczeństwo, tabela gwarancji dostarczenia, warunki wejścia brokera, ograniczenia.
- Decyzja: domyślny retry (6 prób, cap 300 s, ok. 1 min do dead) jest pod demo. Na produkcję rekomendacja: dłuższy horyzont i alert na dead. Parametry w zmiennych środowiskowych.
- Dowód: `ARCHITECTURE.md`, czeka na przegląd Jakuba przed commitem (A11 część).

## 10:39 - Etap 3: Krok 2 - szkielet docker
- Zrobione: `.gitattributes` (LF), `.gitignore`, `.dockerignore`, `Dockerfile` (php:8.3-apache + pdo_pgsql + composer), `docker/vhost.conf` (FallbackResource do front controllera), `docker-compose.yml` (db + app, healthchecki), `composer.json` + `composer.lock` (PHPUnit 11.5.58), `db/schema.sql`, `src/Config.php` (fail fast przy braku zmiennej), `src/JsonLogger.php`, `public/index.php` z `/health`.
- Decyzja: port aplikacji przez `${APP_PORT:-8080}`. Porty 8080 i 8081 zajmuje inny projekt Jakuba (kitchen-erp-bridge), którego nie zatrzymujemy. Lokalnie uruchamiamy na 8090, a recenzenci dostają domyślne 8080.
- Decyzja: lock wygenerowany w `composer:2` z `--ignore-platform-req=ext-pdo_pgsql` (obraz composera nie ma tego rozszerzenia, obraz aplikacji ma).
- Dowód: `curl localhost:8090/health` daje 200 `{"status":"ok"}` i `X-Request-Id`; nieznana ścieżka daje 404 JSON; `docker compose ps` pokazuje app i db jako healthy; `\dt` pokazuje 2 tabele; pusty `WEBHOOK_SECRET` daje RuntimeException; `php -l` czysto; `scan-diff.sh 79e5bc0` czysto. Composer: brak znanych podatności.
- AI: Claude przy pierwszym podejściu nie sprawdził zajętych portów. Błąd z dockera wyłapał problem, przyczynę sprawdzono przez `docker ps` przed zmianą.

## 10:45 - Etap 3: Krok 3 - podpis i walidacja (A4, A5)
- Zrobione: najpierw testy (`SignatureVerifierTest`, `PayloadValidatorTest`, `phpunit.xml.dist`). Wynik czerwony: 17 błędów, `Class "App\Security\SignatureVerifier" not found`. Potem kod: `SignatureVerifier` + `InvalidSignature` (HMAC-SHA256 z "t.body", `hash_equals`, tolerancja 300 s w obie strony, powód tylko do logu) oraz `PayloadValidator` + `PaymentEvent` + `InvalidPayload` (wszystkie błędy naraz, ścieżka pola i reguła, bez wartości; dane osobowe nie są mapowane).
- Decyzja: podpis parsowany ścisłym regexem (jeden v1, 64 znaki hex), bo każde odstępstwo to "malformed". Identyfikatory ograniczone wzorcem `[A-Za-z0-9_.:-]{1,128}`, a kwota przyjmowana tylko jako int (string i float odrzucane).
- Dowód: PHPUnit 25 testów, 41 asercji, zielono (PHP 8.3.37); `php -l` czysto.
- scan-diff: jedno zgłoszenie, `tests/Unit/SignatureVerifierTest.php:15` (`SECRET = 'test-secret'`). Akceptujemy: to stała testowa, nie prawdziwy sekret, i nie jest używana przez aplikację. Nazwy nie zmieniamy pod skaner.

## 10:49 - Etap 3: Krok 4 - zapis idempotentny i endpoint (A2, A3, A4, A5, A10)
- Zrobione: `EventStore` (INSERT ... ON CONFLICT DO NOTHING RETURNING + outbox w tej samej transakcji, wykrywanie duplikatu z inną treścią przez payload_hash), `RecordResult` (enum), `WebhookController` (kolejność bramek 405, 413, 415, 401, 400, 422, potem zapis; podpis przed parsowaniem JSON; powiadomienie tylko dla charge.successful, bez danych osobowych), wpięcie w `public/index.php` (body czytane maksymalnie do limitu + 1 bajt), `examples/charge-successful.json` (payload z zadania).
- Dowód (na żywo, port 8090): happy path 202; ponownie 200 duplicate; 20 równoległych identycznych żądań: 1×202 + 19×200, w bazie events=1, outbox=1; zły, brakujący i przeterminowany podpis 401; uszkodzony JSON 400; kwota jako string 422 ze ścieżką pola; ten sam event_id z inną kwotą 200 + warning w logu; text/plain 415; GET 405 + Allow: POST. Dane osobowe: 0 trafień w logach app i 0 w tabelach. PHPUnit 25/25, `php -l` czysto.
- scan-diff: jedno zgłoszenie, `src/Http/WebhookController.php:45` (`$log = ['request_id' => ...]`). Fałszywy alarm: to kontekst logu z samymi identyfikatorami, co potwierdza wynik 0 trafień danych osobowych w logach.
- Testy integracyjne endpointu na razie są ręczne. Skryptem powtarzalnym staną się w kroku 6 (`tests/e2e.sh`).

## 10:57 - Etap 3: Krok 5 - worker, ponowienia, DLQ, mock (A6, A7, A8)
- Zrobione: najpierw `RetryPolicyTest` (czerwony: `Class "App\Notification\SendResult" not found`), potem `SendResult`, `RetryPolicy` (5xx/408/429/transport = ponów, inne 4xx = dead, backoff min(base*2^(n-1), cap) + jitter do 20%), `NotifierClient` (interfejs, Adapter) + `HttpNotifierClient` (curl, timeout 5 s, Idempotency-Key), `Outbox` (claim z FOR UPDATE SKIP LOCKED + dzierżawa 60 s, attempts jako fencing token przy zapisie wyniku, requeue), `NotificationDispatcher` (błąd jednego zadania nie zatrzymuje pętli), `bin/worker.php` (crash-only + restart: unless-stopped), `bin/requeue.php`, `mock/notifier.php` (tryby up/down/reject/slow, dedup po Idempotency-Key), w compose usługi worker i notifier-mock (MOCK_PORT, parametry retry z env). `Config`: NOTIFIER_URL wymagane, parametry retry z domyślnymi wartościami i walidacją.
- Dowód: PHPUnit 38 testów, 59 asercji. Na żywo: worker po starcie dostarczył 2 zaległe zadania (attempt 1). Mock down, worker z RETRY_MAX_ATTEMPTS=4 i RETRY_BASE_SECONDS=1: ponowienia po 1, 2 i 4 s, potem dead (attempts=4, HTTP 503); mock up + requeue daje sent. Mock reject: dead po 1 próbie (HTTP 400), a następne zadanie przechodzi jako sent. Dane osobowe w logach workera i mocka: 0. `php -l` czysto.
- scan-diff: jedno zgłoszenie, `mock/notifier.php:75` (`error_log` odebranego powiadomienia). Akceptujemy: to celowy log atrapy z samym event_id, widoczny w demo, a nie pozostałość debugowania.
- Do e2e (krok 6): dwa workery (A8) i tryb slow (timeout).

## 10:58 - Etap 5: Domknięcie (2 min do końca)
- Decyzja Jakuba: zostają tylko rzeczy krytyczne dla organizatora. README z instrukcją, curlem i sekcją audytu (race, zabezpieczenie endpointu, sprzątanie) oraz ZIP do opcji A.
- Uproszczone: e2e.sh, out-of-order w kodzie, code-reviewer, AUDIT.md (audyt w README), przekazanie i podsumowanie. Wszystko to jest wpisane jako ograniczenia w README.
