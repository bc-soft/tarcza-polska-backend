# Tarcza Polska backend - notatki dla asystenta

Symfony 7.4 / PHP 8.4, modularny monolit: `src/<Moduł>/{Entity,Controller,Dto,Message,MessageHandler,Repository,Service,View,Enum}`.
Architektura i decyzje: `docs/architecture.md`, `docs/adr/`. Kontrakt API: `docs/api.md`.

Zasady:
- Nowa logika domenowa trafia do serwisu w swoim module; kontroler tylko mapuje DTO i zwraca JSON.
- Moduły komunikują się przez wiadomości w `App\<Moduł>\Message` (Messenger, transport `async`).
  Zapis do encji cudzego modułu robimy przez jego serwis albo zdarzenie, nigdy bezpośrednio.
- Geometria: `App\Shared\Geo\Point` + typy Doctrine `geo_point` / `geo_geometry`; H3 wyłącznie przez `App\Shared\Geo\H3` (SQL).
- Confidence liczy tylko `Confidence\Service\ConfidenceCalculator` (deterministycznie); AI dokłada `ExternalSource`.
- Research AI: dostawca z `RESEARCH_PROVIDER` (openai | anthropic | none) przez `ResearcherFactory`; prompt, schema i parser są wspólne w `ResearchPrompt`, nie duplikuj ich w klasach dostawców.
- Widok publiczny (`IncidentPublicView`) nie może ujawniać surowych lokalizacji raportów.
- Zakres pilotażu to Poznań: wszystkie domyślne miejsca (mapa, importy, fikstury) biorą się z `App\Shared\Geo\Region`
  (`app.region.*` w `config/services.yaml`), nie z literałów współrzędnych w kodzie.
- Po zmianie encji: `make migration` i commit migracji. Nie edytuj wygenerowanych migracji ręcznie poza SQL dla PostGIS.
- Bramka jakości: `make qa` (php-cs-fixer, PHPStan lvl 8, PHPUnit). Nie wyciszaj PHPStan ignorami.
- Komendy: `php bin/console` lokalnie wymaga działającej bazy z `make up` (port 5432).

Git:
- Commituj na bieżąco, małymi krokami, po każdej domkniętej zmianie (nie zostawiaj dużych niezacommitowanych paczek pracy).
- Format: Conventional Commits, np. `feat(verification): plan next wave from frontier cells`,
  `fix(confidence): clamp crowd component`, `docs(adr): add ADR 0007`, `chore(docker): bump postgis image`.
  Scope = nazwa modułu (`identity`, `reporting`, `incident`, `verification`, `confidence`, `intelligence`,
  `alerting`, `shelter`, `command`, `simulation`, `shared`) albo obszar (`docker`, `ci`, `docs`, `deps`).
- Jedna zmiana logiczna = jeden commit; migracja Doctrine w tym samym commicie co zmiana encji.
