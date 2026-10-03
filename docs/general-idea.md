# Tarcza Polska
## „Siła jest w nas”

### 1. Cel projektu

**Tarcza Polska** to platforma odporności cywilnej wykorzystująca crowdsourcing, geolokalizację i AI do szybkiego wykrywania, weryfikowania i określania zasięgu zagrożeń oraz problemów infrastrukturalnych w sytuacjach kryzysowych.

Głównym problemem, który rozwiązujemy, nie jest brak informacji.

Problemem jest to, że podczas kryzysu informacja jest:

- rozproszona pomiędzy tysiącami ludzi,
- niepełna,
- szybko się dezaktualizuje,
- trudna do zweryfikowania,
- często dostępna tylko lokalnie,
- trudna do przekształcenia w konkretną decyzję.

Tarcza Polska zamienia społeczeństwo w **rozproszoną sieć sensorów**.

Pojedynczy użytkownik może wiedzieć niewiele. Tysiące użytkowników, których obserwacje są korelowane, weryfikowane i analizowane razem, mogą stworzyć bardzo dokładny obraz sytuacji.

---

# 2. Główna idea

Podstawowy cykl działania systemu:

**DETECT → VERIFY → MAP → INFORM**

### DETECT

Użytkownicy zgłaszają problemy:

- brak prądu,
- brak wody,
- brak paliwa,
- nieprzejezdną drogę,
- problem ze schronem,
- zagrożenie lub skutki incydentu,
- inne zakłócenie istotne dla ludności cywilnej.

### VERIFY

System nie traktuje pojedynczego zgłoszenia jako faktu.

Łączy:

- inne zgłoszenia użytkowników,
- odpowiedzi użytkowników znajdujących się w okolicy,
- zdjęcia,
- dane z publicznych źródeł,
- komunikaty oficjalne,
- research wykonywany przez AI.

Na tej podstawie wyznaczany jest poziom wiarygodności zdarzenia.

### MAP

System określa nie tylko lokalizację problemu, ale również jego **rzeczywisty zasięg geograficzny**.

Jeżeli kilku użytkowników zgłasza brak prądu, system może zacząć aktywnie pytać:

> Czy obecnie masz dostęp do energii elektrycznej?

Najpierw osoby w bezpośrednim otoczeniu problemu, następnie użytkowników znajdujących się coraz dalej.

Na podstawie odpowiedzi pozytywnych i negatywnych system stopniowo wyznacza granicę obszaru awarii.

### INFORM

Osoby znajdujące się na obszarze zagrożenia otrzymują konkretną, użyteczną informację.

Przykład:

> Potwierdzono rozległą awarię energetyczną w Twoim obszarze.
>
> Problem zgłasza 86% odpowiadających użytkowników.
>
> Najbliższy działający punkt pomocy znajduje się 1,2 km od Ciebie.

---

# 3. Kluczowa innowacja

Tarcza Polska nie jest zwykłą mapą crowdsourcingową.

Najważniejszym elementem projektu jest:

## Active Crowd Verification

System **sam aktywnie zbiera brakujące informacje**.

Jeżeli pojawi się potencjalny problem:

1. system wykrywa skupisko podobnych zgłoszeń,
2. tworzy hipotezę o występowaniu zdarzenia,
3. pyta użytkowników znajdujących się w okolicy,
4. analizuje odpowiedzi,
5. rozszerza lub zawęża badany obszar,
6. wybiera kolejnych użytkowników znajdujących się przede wszystkim przy niepewnej granicy problemu,
7. tworzy dynamiczną strefę zdarzenia.

Dzięki temu użytkownicy nie muszą sami wiedzieć, że powinni coś zgłosić.

System może ich o to zapytać.

---

# 4. Dwie warstwy produktu

## Tarcza Citizen

Aplikacja przeznaczona dla ludności cywilnej.

Pozwala:

- zobaczyć sytuację w swojej okolicy,
- zgłosić problem,
- potwierdzić lub zaprzeczyć zgłoszeniu,
- otrzymywać lokalne alerty,
- znaleźć najbliższy schron,
- sprawdzić jego aktualny status,
- przekazać informacje pomagające innym.

Użytkownik otrzymuje przede wszystkim **actionable information**, a nie wszystkie surowe dane.

---

## Tarcza Command

Panel przeznaczony docelowo dla uprawnionych centrów zarządzania kryzysowego, administracji i służb publicznych.

Operator widzi:

- napływające zgłoszenia,
- skupiska zdarzeń,
- źródła informacji,
- poziom confidence,
- zmiany zasięgu incydentu,
- bardziej szczegółowe dane źródłowe,
- zdjęcia i materiały przesłane do weryfikacji,
- statystyki odpowiedzi społeczności.

Operator może również kierować komunikaty do określonych obszarów.

---

# 5. Zasada bezpieczeństwa danych

Publiczna aplikacja i Command Center **nie powinny posiadać tego samego poziomu szczegółowości informacji**.

Przykład:

### Command Center

System może wiedzieć, że kilka raportów pochodzi z konkretnych lokalizacji.

### Citizen

Użytkownik widzi:

> Potwierdzone zagrożenie w tym obszarze.

Nie widzi dokładnych współrzędnych źródeł ani informacji mogących stanowić zagrożenie operacyjne.

Szczególnie wrażliwe informacje powinny być:

- agregowane przestrzennie,
- udostępniane zgodnie z rolami,
- chronione przez RBAC,
- rejestrowane w audit logu,
- przechowywane tylko przez wymagany okres.

---

# 6. Główne typy danych

### Report

Pojedyncze zgłoszenie użytkownika.

Przykładowe pola:

- typ,
- lokalizacja,
- timestamp,
- opis,
- zdjęcie,
- użytkownik / anonimowy identyfikator,
- confidence źródła.

### Incident

Zdarzenie stworzone na podstawie jednego lub wielu raportów.

Przykład:

> Power outage — Poznań / Jeżyce

Incident posiada:

- typ,
- status,
- confidence,
- obszar,
- powiązane raporty,
- źródła zewnętrzne,
- czas rozpoczęcia,
- czas ostatniego potwierdzenia.

### Verification Request

Pytanie skierowane do użytkownika.

Przykład:

> Czy w tej chwili masz dostęp do prądu?

### Verification Response

Odpowiedź:

- TAK,
- NIE,
- NIE WIEM.

### Shelter

- lokalizacja,
- pojemność,
- status,
- dostępność,
- ostatnie potwierdzenie,
- liczba raportów użytkowników.

### Alert

Komunikat przypisany do określonego obszaru.

---

# 7. Confidence Score

Każde zdarzenie powinno posiadać poziom wiarygodności.

Przykład:

**UNVERIFIED**

Pojedynczy raport.

**LIKELY**

Wiele niezależnych raportów z tego samego obszaru.

**HIGH CONFIDENCE**

Crowdsourcing potwierdzony przez większą liczbę użytkowników.

**CONFIRMED**

Potwierdzenie społeczności + wiarygodne źródło zewnętrzne lub oficjalne.

Confidence nie powinien być prostą liczbą generowaną przez LLM.

Powinien wynikać z możliwie deterministycznego modelu uwzględniającego między innymi:

- liczbę niezależnych raportów,
- ich rozkład geograficzny,
- świeżość informacji,
- liczbę potwierdzeń,
- liczbę zaprzeczeń,
- jakość źródeł zewnętrznych,
- wiarygodność reporterów.

AI dostarcza dodatkowe dowody, ale nie powinno być jedynym arbitrem prawdziwości informacji.

---

# 8. Rola AI

AI pełni funkcję **research & correlation engine**, a nie centralnego decydenta.

AI może:

- grupować podobne raporty,
- wykrywać, że różne opisy dotyczą tego samego zdarzenia,
- analizować komunikaty internetowe,
- wyszukiwać potwierdzenia w źródłach publicznych,
- streszczać informacje dla operatora,
- klasyfikować zdjęcia,
- wykrywać sprzeczne informacje,
- proponować pytania weryfikacyjne.

Przykład:

Crowdsourcing:

> 23 raporty braku prądu.

AI:

> Lokalny operator energetyczny opublikował 4 minuty temu informację o awarii obejmującej ten obszar.

System:

> Confidence zwiększony do CONFIRMED.

---

# 9. Funkcjonalności — MVP

MVP musi pokazywać przede wszystkim **unikalny mechanizm Tarczy**, a nie wszystkie możliwe zastosowania.

## MOBILE

### Mapa

Użytkownik widzi:

- swoją lokalizację,
- aktywne incidenty,
- obszary problemów,
- schrony.

### Raportowanie

Możliwość zgłoszenia:

- brak prądu,
- brak wody,
- brak paliwa,
- nieprzejezdna droga,
- problem ze schronem,
- inne zagrożenie.

Flow:

`typ → lokalizacja → opcjonalny opis → wyślij`

### Active Verification

Push/in-app prompt:

> W Twojej okolicy zgłoszono brak prądu.
>
> Czy u Ciebie również występuje ten problem?

Odpowiedzi:

`TAK / NIE / NIE WIEM`

### Schrony

Mapa schronów:

- otwarty,
- zamknięty,
- brak danych.

Użytkownik może potwierdzić status.

### Alerty

Użytkownik widzi komunikaty dotyczące obszaru, w którym się znajduje.

---

## BACKEND

### Reports API

Przyjmowanie i zapisywanie zgłoszeń.

### Incident Engine

Łączenie raportów dotyczących:

- podobnego typu,
- podobnego czasu,
- podobnej lokalizacji.

### Geographic clustering

Tworzenie obszaru występowania problemu.

W MVP może to być uproszczone do:

- grid/geohash/H3,
- grupowania komórek,
- confidence dla każdej komórki.

### Active Verification Engine

System wybiera użytkowników znajdujących się:

- wewnątrz potencjalnego obszaru,
- na jego granicy,
- tuż poza nim.

Następnie wysyła verification request.

### Confidence Engine

Aktualizacja confidence na podstawie raportów i odpowiedzi.

### Basic AI Research

Dla MVP wystarczy:

`incident → AI research → znalezione źródła → summary`

### Command Center

Minimalny panel:

- mapa,
- lista incidentów,
- szczegóły incidentu,
- powiązane zgłoszenia,
- confidence,
- możliwość wysłania alertu.

---

# 10. Ważne do dodania po MVP

## MOBILE

### Zdjęcia

Możliwość przesłania zdjęcia jako materiału weryfikacyjnego.

### Offline / degraded mode

Cache:

- schronów,
- podstawowych procedur,
- ostatnich alertów,
- ostatniego stanu mapy.

### Dostępność schronów

Nie tylko:

`otwarty / zamknięty`

ale także:

`dużo miejsc / mało miejsc / pełny`

### Historia incydentu

Pokazanie:

- kiedy problem wykryto,
- kiedy został potwierdzony,
- jak zmienia się jego zasięg.

### Powiadomienia push

Automatyczne alerty geograficzne.

---

## BACKEND

### Analiza zdjęć

- usuwanie EXIF,
- klasyfikacja materiału,
- moderacja,
- powiązanie zdjęcia z incidentem.

### External Sources Engine

Adaptery do:

- oficjalnych komunikatów,
- stron operatorów infrastruktury,
- lokalnych mediów,
- RSS/API.

### User reputation

Historia wiarygodności użytkownika wpływa na wagę raportu.

### Advanced geographic estimation

Dynamiczne wyznaczanie granicy problemu zamiast prostego promienia.

### Role Based Access Control

Role np.:

- citizen,
- analyst,
- emergency operator,
- administrator.

### Audit log

Rejestrowanie dostępu do wrażliwych informacji.

---

# 11. Nice to have

### Integracja z mObywatelem

Potwierdzenie, że reporter jest realną, unikalną osobą.

Pomaga ograniczyć:

- boty,
- masowe fałszywe konta,
- Sybil attacks.

Docelowo możliwa integracja Tarczy jako usługi publicznej.

### Zaawansowane źródła danych

Integracje z:

- operatorami energetycznymi,
- wodociągami,
- zarządcami dróg,
- systemami miejskimi,
- centrami zarządzania kryzysowego.

### Prediction / anomaly detection

System wykrywa anomalie zanim powstanie ręcznie utworzony incident.

Przykład:

Normalnie:

`2 raporty / godz.`

Nagle:

`47 raportów / 10 min`

System:

> Potential infrastructure disruption detected.

### Inteligentne routowanie

Wyznaczanie trasy:

- do schronu,
- do punktu pomocy,
- poza obszarem zakłóceń.

### Multilingual support

Automatyczne tłumaczenie alertów.

Przydatne np. dla:

- turystów,
- uchodźców,
- obcokrajowców.

---

# 12. MVP — czego NIE robimy

Żeby projekt pozostał wykonalny podczas hackathonu, MVP nie wymaga:

- pełnej integracji mObywatela,
- produkcyjnej integracji z państwowymi systemami,
- rzeczywistych tajnych danych,
- rozbudowanego systemu reputacji,
- perfekcyjnego algorytmu wyznaczania granic,
- dużej liczby rzeczywistych źródeł informacji,
- produkcyjnego bezpieczeństwa klasy systemu państwowego.

Najważniejsze jest pokazanie, że **mechanizm działa end-to-end**.

---

# 13. Docelowe demo

Najlepszy scenariusz prezentacyjny:

### 1.

Kilku użytkowników zgłasza:

> BRAK PRĄDU

### 2.

Backend wykrywa cluster.

Command Center:

> POSSIBLE POWER OUTAGE  
> Confidence: 42%

### 3.

System automatycznie wysyła pytanie do użytkowników w pobliżu:

> Czy masz obecnie prąd?

### 4.

Większość odpowiada:

> NIE

Confidence:

`42% → 76%`

### 5.

System pyta użytkowników znajdujących się dalej.

Część odpowiada:

> TAK

### 6.

System automatycznie wyznacza granicę awarii.

### 7.

AI znajduje komunikat operatora energetycznego.

Confidence:

`76% → 96%`

Status:

> CONFIRMED

### 8.

Operator wysyła komunikat do wyznaczonego obszaru.

### 9.

Użytkownik znajdujący się na tym obszarze otrzymuje:

> Potwierdzono awarię energetyczną w Twojej okolicy.

To demo pokazuje cały sens Tarczy w kilka minut.

---

# 14. Roadmapa hackathonowa

## FAZA 1 — Fundament

### Mobile

- projekt podstawowego UI,
- mapa,
- lokalizacja użytkownika,
- ekran zgłoszenia,
- podstawowe kategorie incidentów.

### Backend

- model danych,
- Reports API,
- Incident API,
- baza geograficzna,
- podstawowy clustering.

**Cel fazy:** użytkownik zgłasza problem i widzi go w systemie.

---

## FAZA 2 — Core Tarczy

### Mobile

- verification prompt,
- TAK / NIE / NIE WIEM,
- wyświetlanie incident areas,
- confidence/status incidentu.

### Backend

- Verification Requests,
- Verification Responses,
- agregacja odpowiedzi,
- Confidence Engine,
- aktualizacja geograficznego zasięgu incidentu.

**Cel fazy:** system sam aktywnie ustala, gdzie występuje problem.

To jest najważniejsza faza całego projektu.

---

## FAZA 3 — Command Center

### Mobile

- alerty,
- mapa schronów,
- status schronu.

### Backend / CMS

- mapa incidentów,
- szczegóły incidentu,
- wszystkie źródła,
- liczba raportów,
- liczba odpowiedzi,
- confidence,
- wysłanie komunikatu dla obszaru.

**Cel fazy:** pokazać połączenie społeczeństwo → system → operator → społeczeństwo.

---

## FAZA 4 — AI

### Mobile

Brak większych zmian.

### Backend

- research dla incidentu,
- agregacja znalezionych źródeł,
- AI summary,
- zmiana confidence po zewnętrznym potwierdzeniu.

**Cel fazy:** pokazać wieloźródłową weryfikację.

---

## FAZA 5 — Polish & Demo

### Mobile

- poprawa UX,
- animacje / loading,
- kolory statusów,
- ekran alertu,
- dopracowanie mapy.

### Backend

- przygotowanie seed data,
- stabilizacja,
- logowanie,
- przygotowanie demo scenario.

### Command Center

- dopracowanie dashboardu,
- czytelna wizualizacja zasięgu zdarzenia,
- timeline incidentu.

**Cel:** całość ma wyglądać jak jeden spójny produkt.

---

# 15. Priorytety implementacji

Jeżeli zaczyna brakować czasu:

**PRIORYTET 1**

`Report → Incident → Verification → Dynamic Area`

Bez tego tracimy clue projektu.

**PRIORYTET 2**

`Command Center → Alert`

Pokazuje realną wartość operacyjną.

**PRIORYTET 3**

`AI Research`

Wzmacnia wiarygodność danych.

**PRIORYTET 4**

`Shelters`

Bardzo przydatne użytkownikowi i efektowne wizualnie.

**PRIORYTET 5**

`Photos / offline / reputation / mObywatel`

Jeżeli zostanie czas.

---

# 16. Najważniejsze zdanie dla zespołu

Przy każdej nowej funkcji powinniśmy zadać sobie pytanie:

> **Czy ta funkcja pomaga szybciej wykryć problem, lepiej go zweryfikować, określić jego zasięg albo skuteczniej poinformować zagrożonych ludzi?**

Jeżeli odpowiedź brzmi „nie”, prawdopodobnie nie jest potrzebna w MVP.

---

# 17. Product statement

**Tarcza Polska — siła jest w nas.**

Tarcza Polska przekształca tysiące pojedynczych obserwacji obywateli w jeden wspólny, zweryfikowany obraz sytuacji.

System wykorzystuje crowdsourcing, aktywną weryfikację, dane geograficzne i AI, aby wykrywać zagrożenia, określać ich rzeczywisty zasięg i dostarczać właściwą informację właściwym osobom we właściwym miejscu.

**Jedna osoba widzi fragment sytuacji. Razem widzimy całość.**
