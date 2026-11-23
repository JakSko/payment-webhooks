**Temat:** Przejęcie projektu powiadomień o płatnościach: pierwsze kroki, realny zakres na start i pytania

Dzień dobry,

od dziś przejmuję projekt systemu, który odbiera zdarzenia płatności od operatora (Stripe/PayU) i powiadamia zespół sprzedaży. Dziękuję za przesłaną wizję. Dobrze pokazuje, dokąd chcecie Państwo dojść. Zanim zacznę prace, chcę przedstawić, jak rozumiem sytuację, co realnie możemy uruchomić najpierw i czego potrzebuję od Państwa, żeby sprawnie wejść w projekt.

## Jak rozumiem sytuację

Ten system dotyczy pieniędzy i obsługi klientów. Sprzedaż ma się dowiedzieć o każdej opłaconej transakcji szybko i dokładnie raz. Każdy błąd ma konkretny koszt:
- zgubione powiadomienie to opłacone zamówienie, którym nikt się nie zajął,
- zdublowane powiadomienie to podwójna obsługa i chaos w zespole,
- fałszywe powiadomienie to ryzyko wydania towaru bez zapłaty.

Dlatego na start najważniejsza jest niezawodność, a nie liczba funkcji.

## Co jest realne na start, a co wymaga etapów

Wizja z brokerem wiadomości, powiadomieniami na 5 kanałów i raportami AI jest dobrym kierunkiem. Uruchomienie jej w całości "na dzisiaj" nie jest jednak realne, jeśli ma działać bezpiecznie na prawdziwych płatnościach. Każdy kanał wymaga dostępów i testów z daną usługą. Broker to osobna infrastruktura do utrzymania. Raporty AI potrzebują danych z kilku tygodni działania i decyzji, jakie pytania mają rozstrzygać. Robienie wszystkiego naraz zwiększa ryzyko, że żaden element nie będzie działał niezawodnie.

Proponuję zacząć od rdzenia, na którym zbudujemy resztę:
- odbiór powiadomień o płatnościach z weryfikacją, że naprawdę wysłał je operator płatności,
- brak duplikatów: ta sama płatność nigdy nie wygeneruje dwóch powiadomień, nawet jeśli operator wyśle zdarzenie kilka razy,
- brak utraconych powiadomień: gdy zewnętrzna usługa chwilowo nie działa, system ponawia wysyłkę z rosnącym odstępem, a zdarzenia, których nie udało się dostarczyć, odkłada do ręcznej obsługi zamiast je gubić,
- jeden kanał powiadomień dla zespołu sprzedaży, zbudowany tak, żeby kolejne kanały dało się dodać bez przebudowy.

| Etap | Zakres | Kiedy ma sens |
|---|---|---|
| 1 | Rdzeń opisany wyżej | teraz |
| 2 | Kolejne kanały powiadomień, po jednym | gdy znamy kanały i mamy do nich dostępy |
| 3 | Message Broker (kolejka zewnętrzna) | gdy liczba zdarzeń przekroczy możliwości prostszej kolejki w bazie danych. Wcześniej broker to dodatkowy koszt i element do utrzymania, który nie daje Państwu nic więcej |
| 4 | Raporty AI | gdy zbierzemy dane z kilku tygodni działania i ustalimy, jakie decyzje mają wspierać |

## Przejęcie projektu: czego potrzebuję na start

Zanim zaproponuję ostateczny plan i terminy, muszę poznać to, co już powstało. Nie chcę budować od nowa czegoś, co działa, ani powielać błędów, które ktoś już rozpoznał. Proszę o:
- **czas na wdrożenie się:** 1-2 dni robocze na przegląd kodu, dokumentacji i środowisk,
- **rozmowy z osobami zaangażowanymi w projekt:** poprzednim wykonawcą lub programistą, osobą odpowiedzialną za projekt po Państwa stronie i przedstawicielem zespołu sprzedaży, który będzie korzystał z powiadomień,
- **dostęp do materiałów:** repozytorium, dokumentacja, ustalenia z poprzednim zespołem, lista znanych problemów,
- **dostęp do środowisk i panelu operatora płatności:** proszę nie przesyłać haseł ani kluczy mailem. Ustalimy bezpieczny sposób przekazania, np. menedżer haseł,
- **kontekst zmiany wykonawcy:** co działało, co nie i dlaczego. To pozwoli mi uniknąć tych samych problemów.

## Pytania, które pozwolą dobrze zaplanować prace

**1. Termin "na dzisiaj"**
Co dokładnie oznacza dla Państwa "działający dziś"? Czy chodzi o wersję do pokazania i przetestowania, czy o wdrożenie produkcyjne obsługujące prawdziwe płatności? Na wersję testową rdzenia mogę się zobowiązać w krótkim terminie. Wdrożenie produkcyjne wymaga gotowego środowiska, dostępów, osoby po Państwa stronie, która zatwierdzi zmianę, i testu z operatorem płatności. Bez tego nie chcę obiecywać terminu, którego nie da się bezpiecznie dotrzymać.

**2. Kanały powiadomień**
Które 5 kanałów mają Państwo na myśli (np. e-mail, Slack, Microsoft Teams, SMS, CRM) i który jest najważniejszy na start? Kto odbiera powiadomienia? Czy mają Państwo konta i dane dostępowe do tych usług?

**3. Raporty AI**
Jakie pytania mają rozstrzygać raporty i kto będzie je czytał? Przykłady, od których możemy zacząć:
- dzienne lub tygodniowe podsumowanie sprzedaży: przychód według sklepu, kanału i metody płatności, z krótkim komentarzem, co się zmieniło względem poprzedniego okresu,
- sygnały do reakcji: nagły spadek udanych płatności w danej metodzie lub kanale, nietypowa liczba zwrotów,
- odpowiedzi na pytania zadawane zwykłym językiem, np. "jak wypadł BLIK w aplikacji mobilnej w tym tygodniu?".

Same liczby policzymy dokładnie i tanio bez AI. Model językowy proponuję tam, gdzie daje wartość: w opisie, interpretacji i odpowiedziach na pytania. Przed przekazaniem danych do dostawcy AI potrzebujemy Państwa zgody i umowy powierzenia danych. Do tego czasu pracujemy na danych zanonimizowanych.

**4. Ciągłość działania**
Po stronie aplikacji zabezpieczymy ponawianie wysyłki i przechowywanie zdarzeń, których nie udało się dostarczyć. Chcę też poznać infrastrukturę: gdzie system będzie działał, czy jest środowisko zapasowe lub testowe, czy jest monitoring i kto reaguje na alerty. Od tego zależy, jak długą przerwę system przetrwa bez utraty danych.

**5. Operator płatności i zdarzenia**
Który operator jest pierwszy, Stripe czy PayU? Czy oprócz udanych płatności mamy obsługiwać płatności nieudane, zwroty i chargebacki? Ile zdarzeń dziennie i w szczycie się Państwo spodziewają? Od tego zależy weryfikacja podpisu, model danych i decyzja o brokerze.

**6. Dane osobowe w powiadomieniach**
Czy sprzedaży wystarczy numer zamówienia, kwota i metoda płatności? Bez e-maila i nazwiska klienta ograniczamy ryzyko związane z RODO, a pełne dane zespół znajdzie w systemie zamówień.

## Następne kroki

1. Krótkie spotkanie (30 minut) jeszcze przed rozpoczęciem prac, najlepiej w ciągu 1-2 dni roboczych. Omówimy treść tego maila: zakres na start, odpowiedzi na pytania i sposób przekazania dostępów. Część pytań szybciej wyjaśnimy w rozmowie niż mailowo.
2. Po otrzymaniu dostępów przeglądam projekt i rozmawiam z osobami, które w nim uczestniczyły (1-2 dni robocze).
3. Równolegle przygotowuję wersję testową rdzenia na danych testowych, z zastępnikiem zewnętrznej usługi powiadomień. Wykorzystam to, co już istnieje w projekcie, zamiast zaczynać od zera.
4. Drugie spotkanie: pokazuję działający rdzeń i przedstawiam plan kolejnych etapów z estymacją.

Wszystkie założenia, które przyjmę po drodze, opiszę, żeby łatwo je było potwierdzić albo zmienić. Proszę o propozycję terminu pierwszego spotkania. Dostosuję się do Państwa kalendarza.

Z poważaniem,

**Jakub Skowroński**\
Senior AI Engineer
