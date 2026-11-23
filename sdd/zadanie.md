# Zadanie: Payment Webhook Ingestion & Notification Pipeline

Źródło prawdy dla tego projektu. Treść przekazana przez Jakuba w czacie (kopia z Google Docs), 2026-11-23. Część 1 to wierny zapis wymagań. Część 2 to wytyczne wyprowadzone wprost z treści, bez własnych rozwiązań. Rozwiązania powstaną dopiero w spec, po uzgodnieniu rozumienia z Jakubem.

## Część 1. Wymagania (wierny zapis)

### Kontekst biznesowy
Budujemy mikro-system dla platformy e-commerce. Odbiera on webhooki o zdarzeniach płatności od dostawcy (np. Stripe/PayU), przetwarza je i wysyła asynchroniczne powiadomienie do zespołu sprzedaży.

Klient przesłał mocno skomplikowane wizje (chce m.in. kolejkę Message Broker, powiadomienia na 5 kanałów i raporty AI), a system ma:
- działać na dzisiaj,
- być odporny na duplikaty zdarzeń (idempotentność),
- być nieprzerywalny w przypadku chwilowych awarii zewnętrznych API.

### 1. Komunikacja z klientem
Napisz wiadomość e-mail do klienta i zapisz jej treść w pliku `EMAIL_TO_CLIENT.md`. Weź pod uwagę sytuację biznesową, nierealne oczekiwania klienta oraz fakt, że przejmujesz ten projekt. Napisz maila, którego wysłałbyś do niego przed rozpoczęciem prac.

### 2. Architektura
Przeanalizuj scenariusze odbiegające od ścieżki podstawowej (happy path) i zabezpiecz je w koncepcji architektonicznej. Uwzględnij m.in.:
- **Race Condition / Concurrent Requests**: wpadnięcie dwóch identycznych webhooków (`event_id`) w dokładnie tej samej milisekundzie.
- **Out-of-order Delivery**: zdarzenie `charge.successful` trafia na serwer szybciej niż utrwalenie samej intencji płatności w bazie danych.
- **External API Failures & Backoff**: chwilowy brak dostępności zewnętrznego serwisu powiadomień (HTTP 5xx / Timeout) i strategia ponawiania prób (Exponential Backoff + Dead Letter Queue/Storage).
- **Poison Pills / Malformed Payloads**: obsługa uszkodzonych lub niekompletnych żądań HTTP przy jednoczesnym utrzymaniu czytelnego logowania błędów.

### 3. Vibecoding
Stwórz działający system w PHP (dowolny framework, np. Laravel, Symfony, Slim, lub czysty PHP z Composerem) przy użyciu narzędzi AI (Cursor, Claude, Copilot):
- **Endpoint API**: `POST /api/v1/webhooks/payment` odbierający produkcyjny payload od bramki płatniczej (poniżej).
- **Dispatcher powiadomień**: asynchroniczny proces/worker, który bierze zdarzenie i wysyła powiadomienie do zewnętrznego serwisu (mock zewnętrznego API).

Payload (dane przykładowe z treści zadania):
```json
{
  "event": "charge.successful",
  "event_id": "evt_9f8d7c6b5a4321",
  "timestamp": 1718029200,
  "livemode": true,
  "data": {
    "transaction": {
      "id": "txn_8a7b6c5d4e3f",
      "provider_reference": "PAYU-9876543210",
      "amount": { "value": 29990, "currency": "PLN" },
      "payment_method": { "type": "blik", "issuer": "mBank" },
      "status": "COMPLETED"
    },
    "customer": {
      "id": "cus_11223344",
      "email": "jan.kowalski@example.com",
      "billing_address": {
        "first_name": "Jan",
        "last_name": "Kowalski",
        "company": "Kowalski Dev Ltd",
        "country": "PL"
      }
    },
    "metadata": {
      "order_id": "ORD-2024-8839",
      "store_id": "store_main_pl",
      "channel": "mobile_app"
    }
  }
}
```

### 4. Audyt kodu
- Zweryfikuj wygenerowany kod pod kątem wyścigów (race condition: co jeśli 2 takie same webhooki wpadną w tej samej milisekundzie?).
- Zabezpiecz endpoint (np. weryfikacja nagłówka / token / sygnatura).
- Oczyść kod z jakichkolwiek zbędnych klas, przerośniętych abstrakcji czy śmieciowych plików wygenerowanych przez AI.

### 5. Wdrożenie i konteneryzacja
- Plik `docker-compose.yml`, który odpala całe środowisko (PHP / serwer www / baza danych lub storage / worker).
- System musi wystartować i być gotowy do przetestowania po jednej komendzie: `docker compose up`.

### 6. Udostępnienie dla zespołu Strix
- Opcja A: spakowany kod aplikacji mailem do rekrutera (adres w oryginalnym dokumencie).
- Opcja B: publiczne repozytorium (GitHub / GitLab) albo gotowe obrazy w rejestrze (np. Docker Hub / GitHub Packages).
- Repozytorium musi zawierać `README.md` z instrukcją krok po kroku: pobranie, uruchomienie, testowe zapytanie `curl`.

## Część 2. Wytyczne projektowe (wprost z treści)

| # | Wytyczna | Skąd w treści |
|---|---|---|
| W1 | Zakres "na dzisiaj" ma pierwszeństwo przed wizją klienta (broker, 5 kanałów, raporty AI) | kontekst biznesowy |
| W2 | To samo zdarzenie przetworzone najwyżej raz, także przy równoczesnych żądaniach | idempotentność, race condition, audyt |
| W3 | Chwilowa awaria zewnętrznego API nie gubi powiadomień i nie zatrzymuje przyjmowania webhooków | nieprzerywalność, backoff |
| W4 | Ponawianie z rosnącym odstępem; po wyczerpaniu prób zdarzenie trafia do DLQ/storage, a nie znika | External API Failures |
| W5 | Kolejność dostarczenia zdarzeń nie jest gwarantowana i nie może psuć stanu | Out-of-order Delivery |
| W6 | Uszkodzone lub niekompletne żądania są obsłużone i czytelnie zalogowane | Poison Pills |
| W7 | Powiadomienie wysyła asynchroniczny worker, nie endpoint | Dispatcher |
| W8 | Endpoint przyjmuje tylko zweryfikowane żądania (nagłówek / token / sygnatura) | audyt |
| W9 | Kod bez zbędnych klas, przerośniętych abstrakcji i śmieci po AI | audyt |
| W10 | Całość startuje jedną komendą `docker compose up` (PHP, www, baza/storage, worker) | wdrożenie |
| W11 | README pozwala obcej osobie pobrać, uruchomić i wysłać testowy `curl` | udostępnienie |
| W12 | Mail do klienta przed pracami: przejęcie projektu, realne oczekiwania | komunikacja |

Uwaga o danych: payload zawiera dane osobowe (e-mail, imię, nazwisko, firma). W treści zadania są przykładowe (`example.com`). W projekcie używamy tylko danych syntetycznych.
