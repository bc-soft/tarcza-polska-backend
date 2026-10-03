# Uruchomienie i praca z repo

## Wymagania

Docker Desktop (lub Docker Engine + Compose v2). Lokalne PHP 8.4 i Composer są przydatne do IDE,
PHPStan i testów jednostkowych, ale nie są wymagane: wszystko działa w kontenerach.

## Pierwsze uruchomienie

```bash
cp .env .env.local                # opcjonalnie: OPENAI_API_KEY (lub ANTHROPIC_API_KEY), FIREBASE_CREDENTIALS
make build                        # obrazy: FrankenPHP + PostGIS/H3
make up                           # https://localhost  (zaakceptuj lokalny certyfikat Caddy)
make seed                         # konta operatorów + schrony w Poznaniu
make simulate                     # scenariusz demo: 700 wirtualnych urządzeń, awaria prądu na Jeżycach
make worker                       # podgląd pracy silników
```

Następnie:

* Command Center: https://localhost/command (`operator@tarcza.local` / `tarcza-demo`)
* Swagger: https://localhost/api/doc
* Zdrowie: `curl -k https://localhost/api/v1/health`

Entrypoint kontenera `php` czeka na bazę, wykonuje migracje i generuje klucze JWT. Przy pierwszym
starcie na czystym wolumenie baza tworzy rozszerzenia `postgis`, `h3`, `h3_postgis`.

## Codzienna praca

| Komenda | Co robi |
|---|---|
| `make migration` | `doctrine:migrations:diff` po zmianie encji |
| `make migrate` | wykonuje migracje |
| `make test` | PHPUnit |
| `make lint` | php-cs-fixer (dry-run) + PHPStan poziom 8 |
| `make qa` | lint + testy, to samo co CI |
| `make console c="debug:router"` | dowolna komenda Symfony |
| `make openapi` | zrzut `docs/openapi.json` dla zespołu Fluttera |
| `make reset` | kasuje wolumeny (baza, Redis) |

Lokalne PHP: `composer install`, `vendor/bin/phpstan analyse`, `php bin/phpunit --testsuite unit`
działają bez Dockera (platforma Composera jest przypięta do rozszerzeń kontenera).

## Zmienne środowiskowe

| Zmienna | Domyślnie | Znaczenie |
|---|---|---|
| `RESEARCH_PROVIDER` | `openai` | dostawca researchu AI: `openai`, `anthropic` lub `none` |
| `OPENAI_API_KEY` | puste | klucz OpenAI; pusty = research pominięty (handler loguje) |
| `OPENAI_MODEL` | `gpt-5` | model OpenAI do researchu (musi wspierać narzędzie web search) |
| `ANTHROPIC_API_KEY` | puste | klucz Claude, używany gdy `RESEARCH_PROVIDER=anthropic` |
| `ANTHROPIC_MODEL` | `claude-opus-5` | model Claude do researchu |
| `FIREBASE_CREDENTIALS` | puste | ścieżka do JSON konta serwisowego; pusty = pushe tylko logowane |
| `H3_RESOLUTION` | 9 | rozdzielczość komórek (~174 m) |
| `INCIDENT_CLUSTER_RADIUS_M` | 1500 | promień klastrowania raportów |
| `INCIDENT_CLUSTER_WINDOW_MIN` | 360 | okno czasowe klastrowania |
| `VERIFICATION_WAVE_TTL_SEC` | 90 | czas życia jednej fali pytań |
| `VERIFICATION_MAX_RING` | 6 | maksymalny ring od centrum |
| `VERIFICATION_DEVICES_PER_CELL` | 5 | ile urządzeń pytamy w jednej komórce |
| `VERIFICATION_COOLDOWN_MIN` | 10 | minimalna przerwa między pytaniami do tego samego urządzenia |

## Debugowanie przepływu

```bash
make console c="debug:messenger"               # kto obsługuje które zdarzenie
make console c="debug:scheduler"               # harmonogram ticków
make console c="messenger:failed:show"         # nieudane wiadomości
make console c="messenger:consume async -vv"   # drugi worker ad hoc
make console c="dbal:run-sql 'SELECT h3_lat_lng_to_cell(POINT(16.9,52.4), 9)'"
```

## Produkcja (VPS)

```bash
SERVER_NAME=api.tarcza.example APP_SECRET=... JWT_PASSPHRASE=... CADDY_MERCURE_JWT_SECRET=... \
POSTGRES_PASSWORD=... ANTHROPIC_API_KEY=... \
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
docker compose exec php bin/console tarcza:seed --password='<silne hasło>'
```

Caddy sam pobierze certyfikat Let's Encrypt dla `SERVER_NAME`. Workflow `.github/workflows/ci.yml`
buduje obraz produkcyjny przy każdym pushu; deploy to `git pull` + powyższa komenda na serwerze.
