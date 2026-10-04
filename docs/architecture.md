# Tarcza Polska - architektura backendu

Dokument opisuje architekturę i stack techniczny backendu Tarczy Polskiej (HackYeah, task „Defence”).
Opis produktu i priorytety są w [general-idea.md](general-idea.md), kontrakt API w [api.md](api.md),
decyzje projektowe w [adr/](adr/), uruchomienie w [getting-started.md](getting-started.md).

## 1. Rekomendacja w jednym zdaniu

Modularny monolit w Symfony 7.4 LTS na PHP 8.4, uruchamiany na FrankenPHP, z PostgreSQL 16 + PostGIS + H3
jako jedyną bazą, Redisem pod kolejki, Mercure do czasu rzeczywistego, Firebase Cloud Messaging do pushy
i modelem językowym z web search (OpenAI lub Claude) jako silnikiem researchu. Całość w jednym `docker compose`,
na jednym VPS w UE.

Mikroserwisy, Kafka czy Kubernetes nie wnoszą tu wartości. Monolit z wyraźnie oddzielonymi modułami
i asynchronicznymi handlerami daje te same granice co osobne usługi, a pozwala dowieźć działający
mechanizm end-to-end i uruchomić go jedną komendą.

## 2. Stack technologiczny

| Warstwa | Wybór | Dlaczego |
|---|---|---|
| Runtime | PHP 8.4, Symfony 7.4 LTS, FrankenPHP | HTTPS, worker mode i wbudowany hub Mercure w jednym kontenerze |
| Baza | PostgreSQL 16 + PostGIS 3.5 + h3-pg | zapytania przestrzenne, poligony, komórki H3 liczone w SQL |
| ORM | Doctrine ORM 3 + własne typy `geo_point` / `geo_geometry` | geometria PostGIS mapowana przez GeoJSON, bez zewnętrznej biblioteki |
| Kolejki | Symfony Messenger, transport Redis | asynchroniczny clustering, fale weryfikacji, research AI, pushe |
| Zadania cykliczne | Symfony Scheduler (`scheduler_tarcza`) | weryfikacja co 20 s, symulowany tłum co 10 s, kanały RSS co 5 min, przypomnienia o lokalizacji co 15 min |
| Realtime | Mercure (SSE) | live mapa i lista incydentów w Command Center bez pollingu |
| Push | Firebase Cloud Messaging (`kreait/firebase-php`) | prompty weryfikacyjne i alerty na Flutterze, iOS i Android |
| Auth | LexikJWTAuthenticationBundle | anonimowa tożsamość urządzenia (Citizen) i konta operatorów (Command) |
| Dokumentacja API | NelmioApiDocBundle (OpenAPI 3) pod `/api/doc` | zespół Fluttera generuje klienta Dart z jednego pliku |
| AI | OpenAI Responses API (`openai-php/client`, domyślnie `gpt-5`) lub Claude API (`anthropic-ai/sdk`, `claude-opus-5`), wybór przez `RESEARCH_PROVIDER` | research incydentu w jednym wywołaniu z wbudowanym web search, bez własnych scraperów |
| Command Center | Twig + Tailwind (CDN, tokeny design systemu Tarcza) + MapLibre GL JS + Mercure | zespół backendowy robi panel bez osobnego frontu; ten sam język wizualny co aplikacja mobilna (ADR 0006) |
| Mapy | OpenFreeMap (kafelki wektorowe, bez klucza); `flutter_map` po stronie mobile | darmowe i wystarczające na demo |
| Jakość | PHPStan poziom 8, php-cs-fixer, PHPUnit 13, Foundry | `make qa` = pełna bramka lokalna, to samo w GitHub Actions |
| Hosting | Hetzner (DE/FI) lub inny VPS w UE, `compose.prod.yaml`, Caddy auto-TLS | jedna maszyna, jeden plik, deploy w 2 minuty |


## 3. Architektura wewnętrzna: modularny monolit

Jedna aplikacja, katalog `src/` podzielony na moduły domenowe. Każdy moduł ma własne encje, serwisy,
kontrolery i handlery Messengera. Moduły komunikują się przez zdarzenia domenowe (Messenger),
a nie przez bezpośrednie sięganie do cudzych encji poza odczytem.

```
src/
├── Shared/          Point, BoundingBox, H3 (wrapper h3-pg), typy Doctrine, JSON-owe błędy API, Scheduler
├── Identity/        Device (anonimowe urządzenie), Operator (konto Command), JWT
├── Reporting/       Report, ReportType, POST /api/v1/reports            -> emituje ReportCreated
├── Incident/        Incident, IncidentCell, klastrowanie, AreaCalculator -> emituje IncidentUpdated
├── Verification/    VerificationWave, VerificationRequest, silnik fal, odpowiedzi TAK/NIE/NIE WIEM
├── Confidence/      deterministyczny ConfidenceCalculator               -> emituje IncidentConfidenceUpdated
├── Intelligence/    ExternalSource, ResearcherInterface (OpenAiResearcher / ClaudeResearcher), External Sources Engine (RSS)
├── Alerting/        Alert przypisany do poligonu, dostarczanie pushem
├── Shelter/         Shelter (import z rejestru dane.gov.pl), potwierdzenia statusu i zapełnienia
├── Fuel/            FuelStation (import z OpenStreetMap), dostępność per rodzaj paliwa
├── Guidance/        procedury (listy kontrolne) i paczka offline
├── Notification/    PushSenderInterface: FCM albo logger (gdy brak credentiali)
├── Command/         API operatora, panel Twig, publikacja do Mercure, audit-logowane widoki
├── Audit/           AuditLogEntry, AuditLogger
└── Simulation/      tarcza:seed, tarcza:simulate:outage, tarcza:simulate:confirm, symulowany tłum
```

Kontroler robi jedno: waliduje DTO (`#[MapRequestPayload]`), wywołuje serwis, zwraca JSON.
Cała logika siedzi w serwisach i handlerach, więc da się ją uruchomić z komendy symulatora
i przetestować bez HTTP.

### 3.1 Przepływ zdarzeń (scenariusz demo)

```
POST /api/v1/reports
  └─ ReportCreated ──────────────► Incident\ReportCreatedHandler (clustering: typ + czas + promień)
                                     └─ IncidentUpdated(reason)
                                          ├─► Confidence\IncidentUpdatedHandler  (przelicz score/level)
                                          │     ├─ IncidentConfidenceUpdated ──► Command\…Handler ──► Mercure
                                          │     └─ (level >= LIKELY && !researched) ResearchIncident
                                          │           └─► Intelligence\ResearchIncidentHandler (LLM + web search)
                                          │                 └─ ExternalSource(y) ──► IncidentUpdated('research_completed')
                                          └─► Verification\IncidentUpdatedHandler (>= 2 raporty)
                                                └─ ScheduleVerificationWave ──► VerificationScheduler.planNextWave()
                                                      ├─ wave ring 0: center + 6 sąsiadów, push do urządzeń w komórkach
                                                      └─ VerificationTick (co 20 s): zamknij wygasłe fale, planuj następny ring

POST /api/v1/verifications/{id}/response
  └─ VerificationResponder: normalizuj odpowiedź, zaktualizuj komórkę, przelicz obszar
       └─ IncidentUpdated('verification_response') ──► (jak wyżej)

POST /api/command/alerts  (lub formularz w panelu)
  └─ AlertPublished ──► AlertPublishedHandler: urządzenia wewnątrz poligonu ──► push
```

Wszystkie wiadomości z przestrzeni `App\*\Message\*` są routowane na transport `async` (Redis).
Nieudane trafiają na transport `failed` (Doctrine) i można je obejrzeć przez `messenger:failed:show`.

### 3.2 Geografia i Dynamic Area

Źródłem prawdy dla geometrii jest PostGIS (SRID 4326). Komórki liczymy funkcjami h3-pg na
rozdzielczości 9 (~174 m krawędzi). Każda komórka incydentu (`IncidentCell`) ma stan:

```
evidence = reports + yes - no
evidence > 0            -> positive  (problem występuje)
evidence <= 0 && no > 0 -> negative  (problem nie występuje)
w pozostałych wypadkach -> unknown   (pytamy)
```

* `yes` i `no` są już znormalizowane do „problem występuje tutaj?”. Pytanie „Czy masz prąd?” z odpowiedzią
  NIE daje `yes` (patrz `ReportType::yesMeansProblemPresent()`).
* Obszar incydentu = `h3_cells_to_multi_polygon_geometry(komórki positive)`.
* Granica (frontier) = sąsiedzi obszaru w stanie unknown. Kolejna fala pyta tylko tam.
* Komórka pytana dwa razy bez żadnej odpowiedzi przestaje blokować granicę (brak ludzi = brak danych).
* Fala kończy się po `VERIFICATION_WAVE_TTL_SEC` (90 s). Gdy granica się wyczerpie albo ring
  przekroczy `VERIFICATION_MAX_RING`, incydent przechodzi w `active` (granica stabilna).

Obraz `docker/postgres/Dockerfile` instaluje h3-pg, a skrypt `docker/postgres/initdb` tworzy rozszerzenia
`postgis`, `h3` i `h3_postgis` przy pierwszym starcie wolumenu.

### 3.3 Confidence

Deterministyczny, wyjaśnialny model (`Confidence\Service\ConfidenceCalculator`):

```
score = freshness * (reports + spread + crowd) + external

reports   0..0.40   nasycenie w ważonej liczbie raportów (3 raporty ≈ 63% maksimum)
spread    0..0.05   bonus za raporty z różnych komórek (niezależność)
crowd    -0.40..0.40  bayesowski udział „problem występuje” z TAK/NIE, skalowany wielkością próby
freshness exp(-wiek / 180 min)  stosowany do dowodów społecznościowych
external  0..0.25   najlepsza wiarygodność źródła zewnętrznego

< 0.35 UNVERIFIED · < 0.60 LIKELY · < 0.80 HIGH · >= 0.80 CONFIRMED
CONFIRMED wymaga dodatkowo źródła zewnętrznego o wiarygodności >= 0.5
```

Rozbicie na składowe zapisujemy w `incident.confidence_breakdown` i pokazujemy operatorowi.
AI nie jest arbitrem prawdziwości: operator widzi, skąd wzięła się liczba. Testy w
`tests/Unit/Confidence/ConfidenceCalculatorTest.php` pilnują kształtu krzywej.

### 3.4 AI jako research & correlation engine

Model językowy dotyka systemu w jednym miejscu: `Intelligence\Service\ResearcherInterface`. Dostawcę
wybiera zmienna `RESEARCH_PROVIDER` (`openai` domyślnie, `anthropic`, `none`) przez `ResearcherFactory`.

* Część wspólna (`ResearchPrompt`): prompt po polsku w roli analityka CZK, lista zaufanych domen
  (operatorzy energetyczni, wodociągi, RCB, PAP, media lokalne) i ścisły JSON schema odpowiedzi
  (lista źródeł z URL, wydawcą, wiarygodnością, datą i flagą „potwierdza to zdarzenie”) oraz parser.
* `OpenAiResearcher`: Responses API, narzędzie `web_search` z filtrem `allowed_domains` (limit 20 domen),
  `text.format` = `json_schema` ze `strict: true`, `max_tool_calls` = 6, `reasoning.effort` = `low`
  i `max_output_tokens` = 16000 (modele rozumujące zużywają budżet wyjścia na myślenie; przy 4096 odpowiedź
  była ucinana). Zmierzone: 25–35 s, ~27k/2k tokenów, ~0,10 USD na incydent. Test: `tarcza:research --dry-run`.
* `ClaudeResearcher`: `messages.create` z `web_search_20260209` i `output_config.format` = JSON schema.

Źródła potwierdzające zapisujemy jako `ExternalSource`, a `ConfidenceCalculator` decyduje, ile są warte.
Wybrany dostawca bez klucza API = research pominięty z wpisem w logu, reszta działa. Na scenie bez
internetu: `bin/console tarcza:simulate:confirm`.

### 3.5 Bezpieczeństwo i prywatność

* Trzy firewalle: `api_citizen` (`/api/v1`, JWT urządzenia), `api_command` (`/api/command`, JWT operatora),
  `command_web` (`/command`, sesja + formularz). Hierarchia ról: `ROLE_ADMIN > ROLE_OPERATOR > ROLE_ANALYST`.
* Citizen widzi tylko agregaty: poligon obszaru, poziom confidence, odsetek zgodności. Nigdy surowych
  punktów raportów (`IncidentPublicView` vs `IncidentCommandView`).
* Urządzenie przechowuje jedną aktualną lokalizację, bez historii. Brak jakichkolwiek danych osobowych.
* Rate limiter na `POST /api/v1/reports` (10 / 10 min / urządzenie), zdjęcia, aktualizację lokalizacji
  i rejestrację urządzeń (30 / h / IP); throttling logowania operatorów (5 prób / 5 min).
* Publiczna oś czasu incydentu pomija dane wewnętrzne (e-mail operatora, komórkę pierwszego zgłoszenia, identyfikatory zdjęć).
* Odczyt szczegółów incydentu w Command Center i każda akcja operatora trafiają do `audit_log`.
  Administrator przegląda dziennik w panelu (`/command/audit`) i zarządza kontami (`/command/operators`).
* Mercure: publikacja tylko z backendu (JWT), subskrypcja anonimowa na tematy `incidents` i `incidents/{id}`,
  które niosą wyłącznie dane dozwolone dla operatora panelu (panel jest za logowaniem).

### 3.6 Symulator demo

`bin/console tarcza:simulate:outage` tworzy kilkaset wirtualnych urządzeń rozsianych wokół centrum,
zapisuje ukryty poligon awarii jako prawdę bazową (`SimulationScenario`) i składa pierwsze raporty.
Scheduler co 10 s każe wirtualnym mieszkańcom odpowiadać na pytania weryfikacyjne zgodnie z tą prawdą
(z szumem). Dzięki temu na scenie wystarczą dwa prawdziwe telefony, a system i tak wyznaczy granicę
awarii w ciągu minuty. Wirtualne urządzenia mają flagę `simulated` i nigdy nie dostają pushy.

### 3.7 Funkcje post-MVP (zrealizowane)

Lista z sekcji 10 opisu produktu jest wdrożona w całości po stronie backendu; szczegóły decyzji w ADR 0007–0009.

| Funkcja | Gdzie | Jak działa |
|---|---|---|
| Historia incydentu | `Incident\Entity\IncidentEvent`, `IncidentTimeline` | append-only log zdarzeń (wykrycie, fale, zmiany zasięgu i poziomu, research, źródła, alerty, zdjęcia, zamknięcie); `GET /api/v1/incidents/{id}/timeline`, pełna wersja w detalu operatora i panelu |
| Zdjęcia | `Reporting\Service\ImageSanitizer`, `PhotoUploader`, `ReportPhoto` | `POST /api/v1/reports/{id}/photo`; re-enkodowanie do JPEG bez EXIF/GPS, maks. 1600 px, 3 na zgłoszenie; Flysystem `photos.storage`; analiza wizji przez `PhotoAnalyzerInterface` (OpenAI przy `RESEARCH_PROVIDER=openai`); galeria i pobieranie tylko dla operatorów, z audytem |
| External Sources Engine | `Intelligence\Service\Feed\*`, `ExternalSourcesTick` | kanały RSS/Atom z `config/packages/external_sources.yaml`, pobierane co 5 min z cache; deterministyczny matcher (słowa kluczowe typu + tokeny miejsca + okno czasu) dokłada `ExternalSource` z `found_by=rss`; `tarcza:sources:poll` do podglądu |
| Reputacja użytkownika | `Identity\Service\ReputationPolicy`, `ReputationUpdater` | po zamknięciu incydentu z werdyktem: zgłaszający +0,10 / -0,25, odpowiadający +0,02 / -0,05 względem końcowego stanu komórki; reputacja mnoży wagę zgłoszeń w confidence |
| Werdykt zamknięcia | `Incident\Enum\IncidentResolution` | `confirmed` / `false_alarm` (operator, API i panel) / `expired` (wygaszenie); zdarzenie `IncidentResolved` |
| Automatyczne alerty geograficzne | `Alerting\MessageHandler\AutoAlertOnConfidenceHandler` | pierwsze przekroczenie `AUTO_ALERT_LEVEL` (domyślnie `confirmed`) publikuje alert autora `system` do urządzeń w obszarze |
| Dostępność schronów | `Shelter\Enum\ShelterOccupancy` | `plenty` / `limited` / `full` na schronie i w potwierdzeniach; `status: full` z MVP mapuje się na `open` + `full` |
| Tryb offline / degraded | moduł `Guidance` | `GET /api/v1/offline-bundle?lat&lng` (schrony w promieniu, aktywne alerty, otwarte incydenty, procedury, `validUntil`) i `GET /api/v1/procedures` z wbudowanymi listami kontrolnymi po polsku |
| Zaawansowane wyznaczanie zasięgu | `Incident\Service\AreaCalculator`, `VerificationScheduler` | granica rośnie po heksagonach H3 w kierunku niepewnych komórek, dziury są wypełniane; to już nie promień |
| RBAC i audit log | `security.yaml`, moduł `Audit` | role analityk / operator / administrator, dziennik odczytów i akcji, w tym pobrań zdjęć |

Nowe wiadomości w Messengerze: `IncidentResolved`, `PhotoUploaded`, `ExternalSourcesTick`. Nowe tabele:
`incident_event`, `report_photo`, kolumny `incident.resolution`, `shelter.occupancy`,
`shelter_status_report.occupancy`.

### 3.8 Zgłoszenia obszarowe i punktowe (ADR 0010)

| | Obszarowe: prąd, woda, drogi, inne zagrożenia | Punktowe: brak paliwa, problem ze schronem |
|---|---|---|
| Do czego odnosi się zgłoszenie | okolica reportera (komórka H3) | konkretny obiekt: `poiId` z aplikacji albo najbliższa stacja (750 m) / schron (500 m) |
| Klastrowanie | ten sam typ + okno czasu + promień | ten sam typ + ten sam obiekt |
| Weryfikacja | fale po pierścieniach heksagonów, granica rośnie w kierunku nieznanych komórek | fala 0 przy obiekcie, kolejne przy 3 najbliższych obiektach tego rodzaju; pytanie nazywa obiekt i paliwo |
| Co robi odpowiedź | zmienia stan komórki, obszar i pewność | zmienia status obiektu (paliwo per rodzaj, dostępność schronu); do pewności liczą się odpowiedzi o zgłoszonym obiekcie |
| Mapa | wielokąt obszaru | pinezka obiektu; stacje jako warstwa `fuel_station`, schrony jak dotąd |

Kod: `Shared\Poi` (abstrakcja obiektów), moduł `Fuel` (stacje, dostępność per `FuelType`, import z OSM),
`Shelter` (import z dane.gov.pl, `ShelterAvailability`), `ReportSubmitter::resolvePoi`,
`IncidentClusterer::attachToPoi`, `VerificationScheduler::planPointWave`, `VerificationResponder`.

Dane: `tarcza:shelters:import` (rejestr krajowy, 86 tys. punktów, filtry po województwie / obszarze),
`tarcza:fuel-stations:import` (Overpass, `--around` / `--bbox`), `tarcza:fixtures:load --reset`
(deterministyczne dane demo: 8 dzielnic Poznania, wszystkie typy i stany, fale, źródła, alerty, zdjęcia, historia).
Oba importy mają zrzuty offline w `data/` (`--offline`, `--from-file`), więc `make demo` nie potrzebuje sieci.

## 4. Infrastruktura

```
compose.yaml
├── php        FrankenPHP (Caddy + PHP 8.4 + Mercure), https://localhost, bind-mount ./ -> /app
├── worker     ten sam obraz: messenger:consume async scheduler_tarcza
├── database   imresamu/postgis:16-3.5 + h3-pg (docker/postgres)
└── redis      redis:7-alpine (Messenger)
```

Entrypoint kontenera php czeka na bazę, wykonuje migracje i generuje parę kluczy JWT; `make demo`
dokłada import obiektów, konta i dane demo, więc jedna komenda daje gotowe środowisko. Produkcja: `compose.prod.yaml` (obraz z wbudowanym
kodem, worker mode FrankenPHP, Caddy wystawia TLS dla `SERVER_NAME`).

## 5. Znane ograniczenia i dalsze kroki

* Pushe wymagają konta serwisowego Firebase (`FIREBASE_CREDENTIALS`); bez niego są logowane, a aplikacja
  odpytuje `/verifications/pending`.
* Lokalizacja w tle na iOS jest ograniczona przez system; model zakłada ostatnią znaną pozycję i przypomnienie
  o jej odświeżeniu (`location_refresh`).
* Research AI wymaga klucza API (OpenAI lub Anthropic); bez klucza działa wszystko poza researchem,
  a operator może dodać źródło ręcznie. Limit 6 wyszukiwań na incydent trzyma koszt na poziomie centów.
* Jedna instalacja obsługuje jeden region (ADR 0011); wiele regionów to konfiguracja per instancja albo
  tabela regionów w kolejnej iteracji.
* Listy API nie mają paginacji (z założenia krótkie: bbox, najbliższe, pending); produkcja wymagałaby jej dla miast
  większych niż pilotaż.
* Command Center w Twig jest świadomym kompromisem; API `/api/command/*` i Mercure są gotowe pod osobny front.
* Tailwind z CDN w panelu należałoby na produkcji zbudować statycznie (AssetMapper).
