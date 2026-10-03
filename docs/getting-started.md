# Uruchomienie i praca z repo

## Wymagania

Docker Desktop (lub Docker Engine + Compose v2). Lokalne PHP 8.4 i Composer są przydatne do IDE,
PHPStan i testów jednostkowych, ale nie są wymagane: wszystko działa w kontenerach.

## Pierwsze uruchomienie

```bash
echo 'OPENAI_API_KEY=sk-...' > .env.local   # sekrety tylko tu (plik ignorowany przez git), nigdy w .env
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

## Udostępnienie API w sieci lokalnej (dla zespołu Fluttera)

```bash
make lan        # to samo co make up, ale Caddy serwuje też zwykłe HTTP na porcie 80 dla dowolnego hosta
```

Komenda wypisze adres w stylu `http://192.168.2.2`. Telefon lub emulator w tej samej sieci Wi-Fi uderza
bezpośrednio w ten adres (bez TLS, więc bez problemów z certyfikatem). Panel i Swagger nadal działają pod
`https://localhost`, a Swagger po HTTP pod `http://<IP>/api/doc`. macOS może zapytać o zgodę na połączenia
przychodzące dla Dockera; zgódź się. Jeśli dalej nie ma odpowiedzi, sprawdź zaporę w Ustawieniach systemu.

Dla zdalnego developera (inna sieć) najprościej jest tunel: `ngrok http 80` albo `cloudflared tunnel --url http://localhost:80`
po `make lan`; wtedy przekaż mu wygenerowany adres `https://...`.

## Firebase (push)

Plik konta serwisowego z konsoli Firebase wrzuć do `config/firebase/service-account.json` (katalog jest
ignorowany przez git) i ustaw w `.env.local`:

```
FIREBASE_CREDENTIALS=%kernel.project_dir%/config/firebase/service-account.json
```

Po restarcie kontenerów (`make up`) sprawdź poświadczenia i wyślij testowy push na token z aplikacji:

```bash
make push-test                      # tylko weryfikacja poświadczeń z Google
make push-test t=<token FCM>        # testowe powiadomienie na konkretny telefon
make console c="tarcza:push:test --device=<uuid urządzenia>"
```

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
| `FIREBASE_CREDENTIALS` | puste | ścieżka do JSON konta serwisowego (może zawierać `%kernel.project_dir%`); pusty = pushe tylko logowane |
| `H3_RESOLUTION` | 9 | rozdzielczość komórek (~174 m) |
| `INCIDENT_CLUSTER_RADIUS_M` | 1500 | promień klastrowania raportów |
| `INCIDENT_CLUSTER_WINDOW_MIN` | 360 | okno czasowe klastrowania |
| `VERIFICATION_WAVE_TTL_SEC` | 90 | czas życia jednej fali pytań |
| `VERIFICATION_MAX_RING` | 6 | maksymalny ring od centrum |
| `VERIFICATION_DEVICES_PER_CELL` | 5 | ile urządzeń pytamy w jednej komórce |
| `VERIFICATION_COOLDOWN_MIN` | 10 | minimalna przerwa między pytaniami do tego samego urządzenia |
| `OVERPASS_URL` | 3 publiczne serwery | lista adresów Overpass rozdzielona przecinkami, próbowane po kolei (25 s na sondę, 60 s na zapytanie); główny `overpass-api.de` bywa niedostępny, więc dopisz działający mirror w `.env.local` |
| `AUTO_ALERT_LEVEL` | `confirmed` | poziom, przy którym system sam wysyła alert do obszaru (`confirmed`, `high`, `likely`; `off` wyłącza) |

Kanały RSS/Atom dla External Sources Engine konfiguruje się w `config/packages/external_sources.yaml`
(nazwa, URL, rodzaj, wiarygodność, interwał). Zdjęcia lądują w `var/storage/photos` (`config/packages/flysystem.yaml`;
na produkcji podmień adapter na S3/R2 i zachowaj nazwę `photos.storage`).

## Research AI: test i koszty

```bash
make console c="tarcza:research --dry-run"   # wywołuje dostawcę, drukuje wynik, nic nie zapisuje
make console c="tarcza:research --again"     # pełna ścieżka: zapis źródeł, podsumowanie, przeliczenie confidence
```

Zmierzone na `gpt-5` z niskim poziomem rozumowania: 25–35 s na incydent, 5 wyszukiwań, ~27 tys. tokenów
wejścia i ~2 tys. wyjścia, czyli rząd 10 centów za research. Research uruchamia się automatycznie raz na
incydent (po osiągnięciu poziomu „prawdopodobne”), więc 9 USD wystarczy na kilkadziesiąt incydentów.

Sekrety (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `FIREBASE_CREDENTIALS`) trzymaj w `.env.local`. Plik jest
ignorowany przez git i bind-mountowany do kontenerów; `compose.yaml` celowo nie wymienia tych zmiennych,
żeby pusta wartość z compose nie przesłoniła `.env.local`.

## Dane: schrony, stacje paliw, fikstury

```bash
make console c="tarcza:shelters:import --wojewodztwo=wielkopolskie"          # rejestr krajowy z dane.gov.pl (CSV, ~15 MB)
make console c="tarcza:shelters:import --around=52.4121,16.9012 --radius-km=20"
make console c="tarcza:shelters:import --dry-run"                             # tylko policz, nic nie zapisuj
make console c="tarcza:fuel-stations:import --around=52.4121,16.9012 --radius-km=15"   # OpenStreetMap przez Overpass (OVERPASS_URL)
make console c="tarcza:fixtures:load --reset --cities=4 --seed=42"           # różnorodne dane demo do panelu
```

Import schronów jest idempotentny (upsert po identyfikatorze publicznym) i nie nadpisuje potwierdzeń obywateli.
`tarcza:fixtures:load --reset` czyści incydenty, zgłoszenia, alerty, źródła, zdjęcia, symulowane urządzenia oraz
stacje i schrony oznaczone jako `fixture`; zaimportowane schrony i stacje zostają i są używane przez fikstury,
jeśli leżą w promieniu 8 km od centrum miasta. Ten sam `--seed` daje identyczne dane.

## Funkcje post-MVP: szybkie sprawdzenie

```bash
make console c="tarcza:sources:poll"                 # które kanały odpowiadają i co pasuje do otwartych incydentów
make console c="tarcza:sources:poll --apply"         # zapisz dopasowania jako źródła (to samo robi tick co 5 min)
curl -k "https://localhost/api/v1/incidents/<id>/timeline" -H "Authorization: Bearer <token>"
curl -k "https://localhost/api/v1/offline-bundle?lat=52.4121&lng=16.9012" -H "Authorization: Bearer <token>"
curl -k -X POST "https://localhost/api/v1/reports/<id>/photo" -H "Authorization: Bearer <token>" -F "photo=@zdjecie.jpg"
```

Zamknięcie incydentu z werdyktem w panelu (dwa przyciski) albo przez API:
`POST /api/command/incidents/{id}/resolve` z `{"resolution": "false_alarm"}`. Reputację zgłaszających widać
w detalu incydentu w kolumnie `reporterReputation`.

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
