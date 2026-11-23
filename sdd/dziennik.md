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
