# Tarcza Polska - backend

Backend platformy odporności cywilnej **Tarcza Polska** („Siła jest w nas”): crowdsourcing, aktywna
weryfikacja, geolokalizacja i AI do wykrywania zagrożeń, wyznaczania ich zasięgu i informowania ludzi.
Symfony 7.4 · PHP 8.4 · PostgreSQL + PostGIS + H3 · Redis · Mercure · FCM · OpenAI lub Claude (wybór zmienną `RESEARCH_PROVIDER`).

```bash
make build && make up      # https://localhost
make seed                  # operator@tarcza.local / tarcza-demo
make simulate              # scenariusz demo: awaria prądu, 700 wirtualnych urządzeń
make console c="tarcza:research --dry-run"   # test researchu AI (klucz w .env.local)
```

* Command Center: https://localhost/command
* OpenAPI / Swagger: https://localhost/api/doc
* Dokumentacja: [docs/architecture.md](docs/architecture.md), [docs/api.md](docs/api.md),
  [docs/getting-started.md](docs/getting-started.md), [docs/demo-scenario.md](docs/demo-scenario.md),
  decyzje w [docs/adr](docs/adr/), opis produktu w [docs/general-idea.md](docs/general-idea.md).

```
DETECT  POST /api/v1/reports            -> Incident (clustering: typ + czas + promień)
VERIFY  push "Czy masz prąd?"           -> VerificationWave ring 0, 1, 2 ... po granicy obszaru
MAP     IncidentCell positive/negative  -> obszar = unia hexów H3, granica = nieznani sąsiedzi
INFORM  POST /api/command/alerts        -> push do urządzeń wewnątrz poligonu
```

Confidence jest deterministyczne i wyjaśnialne; AI (OpenAI lub Claude z web search) dostarcza tylko źródła.
Integracja aplikacji mobilnej: [docs/flutter-agent-guide.md](docs/flutter-agent-guide.md).
