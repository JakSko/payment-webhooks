# Doprecyzowanie: Payment Webhook Ingestion & Notification Pipeline

Wynik subagenta `requirements-analyst` (tylko z treści zadania, bez kodu), 2026-11-23. Materiał do porównania z rozumieniem Jakuba, a nie decyzja.

## 1. Jak rozumiem prośbę
Klient chce, żeby każda płatność zatwierdzona u dostawcy (Stripe/PayU) automatycznie powiadamiała zespół sprzedaży. System ma przyjąć zdarzenie od dostawcy, zapisać je dokładnie raz i wysłać powiadomienie, także wtedy, gdy zewnętrzna usługa chwilowo nie działa. Wizja klienta (broker wiadomości, 5 kanałów, raporty AI) wykracza poza to, co da się bezpiecznie uruchomić "na dzisiaj". Najpierw niezawodny rdzeń, resztę dokładamy etapami.

## 2. Pytania do klienta (od najważniejszych)
1. Który dostawca jest pierwszy (Stripe czy PayU) i jak podpisuje webhooki? Od tego zależy weryfikacja podpisu, która chroni przed fałszywymi "płatnościami".
2. Jaki jeden kanał powiadomień jest potrzebny dziś (e-mail, Slack, SMS, inny) i kto jest odbiorcą? Wtedy "5 kanałów" to jeden adapter plus wzorzec do dokładania kolejnych.
3. Co ma zawierać powiadomienie: czy wystarczy numer zamówienia i kwota, czy potrzebne są dane klienta? Mniej danych osobowych w wiadomościach to mniejsze ryzyko RODO.
4. Jakie zdarzenia poza `charge.successful` mają działać (zwroty, nieudane płatności, chargeback)? To zmienia model danych i maszynę stanów płatności.
5. Jaki jest oczekiwany wolumen i czas reakcji (zdarzenia na minutę, jak szybko ma przyjść powiadomienie)? Przy kilku zdarzeniach na minutę wystarczy baza jako kolejka, bez brokera.

## 3. Założenia (gdy brak odpowiedzi)
- Dostawca generyczny: podpis HMAC-SHA256 w nagłówku, wspólny sekret z konfiguracji (poza repo).
- Jeden kanał: HTTP do mockowanego serwisu powiadomień.
- Kolejka w bazie (tabela zadań), bez brokera. Worker w osobnym kontenerze.
- Obsługujemy tylko `charge.successful`. Inne typy zapisujemy i pomijamy, z wpisem w logu.
- Idempotentność: unikalny klucz na `event_id`; duplikat dostaje 200 i nie jest przetwarzany ponownie.
- Zdarzenie, które przyjdzie przed utrwaleniem intencji płatności, zapisujemy jako oczekujące i ponawiamy przetwarzanie.
- Ponowienia: exponential backoff z jitterem, po N próbach zadanie trafia do DLQ (tabela). Błędny payload odrzucamy (4xx) i logujemy.
- Powiadomienie zawiera `order_id`, `store_id`, kwotę i walutę, bez e-maila i nazwiska.

## 4. Ryzyka
- Pieniądze: kwota 29990 to grosze (minor units). Pomyłka w jednostkach albo użycie float da złe kwoty. Trzymamy integer i walutę.
- Bezpieczeństwo: bez weryfikacji podpisu i ochrony przed powtórzeniem (sprawdzanie `timestamp`) każdy może "zaksięgować" płatność. Sekret nie trafia do repo ani do logów.
- Dane osobowe: payload zawiera e-mail, imię, nazwisko i firmę. Mogą wyciec przez logi, DLQ i powiadomienia (sekcja 7).
- Spójność: wyścig przy tym samym `event_id` rozwiązuje tylko unikalny indeks w bazie, a nie sprawdzenie "czy istnieje" w kodzie. Worker musi blokować zadanie, żeby dwa nie wysłały tego samego powiadomienia. Gwarancja to "co najmniej raz", więc odbiorca dostaje klucz idempotentności.
- Integracje i przejęcie: nie znamy realnego API powiadomień ani dostawcy, a projekt przejmujemy po kimś. Mock może różnić się od produkcji. Na całość jest ok. 90 min, więc testy pokryją tylko część.

## 5. Warianty
| Wariant | Co dostaje klient | Koszt | Główne ryzyko |
|---|---|---|---|
| A (zalecany, na dziś) | Endpoint z podpisem, idempotentność, kolejka w bazie, worker, backoff i DLQ, 1 kanał (mock), docker compose, README z curl | ok. 8-10 h | Brak skalowania powyżej niskiego wolumenu |
| B | A + interfejs kanałów i drugi adapter, podstawowy podgląd DLQ | ok. 16-20 h | Więcej kodu do utrzymania bez potwierdzonej potrzeby |
| C (wizja) | Broker (RabbitMQ/SQS), 5 kanałów, raporty AI | ok. 60+ h | Złożoność operacyjna, koszt, dane osobowe w AI |

Na dziś wystarcza wariant A. Broker ma sens dopiero wtedy, gdy pomiary pokażą, że baza nie nadąża.

## 6. MVP na dziś a później
- Dziś: wariant A. Tylko ten zakres da się zweryfikować w jednej sesji (testy wyścigu, duplikatów i awarii API).
- Później, jeśli klient potwierdzi potrzebę: kolejne kanały (po jednym, na gotowym interfejsie), broker przy wzroście wolumenu, raporty AI na zanonimizowanych danych (po decyzji prawnej).

## 7. Dane osobowe w logach i powiadomieniach
- Logi: tylko `event_id`, `order_id`, status i kod błędu. Nigdy pełny payload, e-mail, imię, nazwisko ani firma.
- Malformed payload: logujemy powód i identyfikator, bez treści żądania. Jeśli surowy payload jest potrzebny do diagnozy, trafia do DLQ z ograniczonym dostępem i czasem przechowywania.
- Powiadomienia: bez e-maila i nazwiska klienta, wystarczy `order_id`. Jeśli sprzedaż potrzebuje danych klienta, sprawdza je w systemie zamówień.
- Baza: e-mail i dane billingowe zapisujemy tylko wtedy, gdy są potrzebne. Domyślnie nie zapisujemy.
- Testy i README: tylko dane syntetyczne, zero prawdziwych adresów.
