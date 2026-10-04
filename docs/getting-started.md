# Uruchomienie i praca z repo

## Wymagania

Docker Desktop (lub Docker Engine + Compose v2). Lokalne PHP 8.4 i Composer są przydatne do IDE,
PHPStan i testów jednostkowych, ale nie są wymagane: wszystko działa w kontenerach.

## Pierwsze uruchomienie: jedna komenda

```bash
make demo
```

`make demo` buduje obrazy (FrankenPHP + PostGIS/H3), startuje stack i czeka, aż będzie zdrowy (entrypoint
wykonuje migracje i generuje klucze JWT), importuje schrony i stacje paliw Poznania ze zrzutów w `data/`
(bez sieci), zakłada konta operatorów i ładuje dane demo do panelu: 8 dzielnic, wszystkie typy zgłoszeń
i stany incydentów, fale weryfikacji, źródła, alerty, zdjęcia i historia. Pierwsze uruchomienie trwa kilka
minut (budowa obrazów, `composer install`); kolejne kilkadziesiąt sekund. Komenda jest idempotentna:
importy robią upsert, konta nie są duplikowane, dane demo są ładowane od nowa (ten sam seed = te same dane).

Potem opcjonalnie:

```bash
make simulate                     # scenariusz na żywo: 700 wirtualnych urządzeń, awaria prądu na Jeżycach
make worker                       # podgląd pracy silników (klastrowanie, fale, research, pushe)
echo 'OPENAI_API_KEY=sk-...' > .env.local   # research AI; sekrety tylko tu (plik ignorowany przez git), nigdy w .env
```

Ręcznie, krok po kroku, to samo co `make demo`: `make build`, `make up`, `make console c="tarcza:shelters:import --offline"`,
`make console c="tarcza:fuel-stations:import --from-file=data/osm/fuel-stations-poznan.json"`, `make seed`,
`make console c="tarcza:fixtures:load --reset"`.

Następnie:

* Command Center: https://localhost/command (`operator@tarcza.local` / `tarcza-demo`; `admin@tarcza.local` widzi też
  dziennik audytu i konta operatorów, `analyst@tarcza.local` ma tylko odczyt; hasło wspólne)
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
| `OVERPASS_URL` | 3 publiczne serwery | lista adresów Overpass rozdzielona przecinkami, odpytywane równolegle (25 s na sondę, 60 s na zapytanie); bez odpowiedzi import regionu bierze zrzut z `data/osm` |
| `AUTO_ALERT_LEVEL` | `confirmed` | poziom, przy którym system sam wysyła alert do obszaru (`confirmed`, `high`, `likely`; `off` wyłącza) |

Kanały RSS/Atom dla External Sources Engine konfiguruje się w `config/packages/external_sources.yaml`
(nazwa, URL, rodzaj, wiarygodność; wspólny interwał `poll_minutes`). Zdjęcia lądują w `var/storage/photos` (`config/packages/flysystem.yaml`;
na produkcji podmień adapter na S3/R2 i zachowaj nazwę `photos.storage`).

## Research AI: test i koszty

```bash
make console c="tarcza:research --dry-run"   # wywołuje dostawcę, drukuje wynik, nic nie zapisuje
make console c="tarcza:research --again"     # pełna ścieżka: zapis źródeł, podsumowanie, przeliczenie confidence
```

Zmierzone na `gpt-5` z niskim poziomem rozumowania: 25–35 s na incydent, 5 wyszukiwań, ~27 tys. tokenów
wejścia i ~2 tys. wyjścia, czyli rząd 10 centów za research. Research uruchamia się automatycznie raz na
incydent (po osiągnięciu poziomu „prawdopodobne”), więc kilkadziesiąt incydentów to rząd kilku dolarów.

Sekrety (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `FIREBASE_CREDENTIALS`) trzymaj w `.env.local`. Plik jest
ignorowany przez git i bind-mountowany do kontenerów; `compose.yaml` celowo nie wymienia tych zmiennych,
żeby pusta wartość z compose nie przesłoniła `.env.local`.

## Region pilotażu: Poznań

Cała instalacja obsługuje jeden obszar, skonfigurowany w `config/services.yaml`:

```yaml
app.region.name: 'Poznań'
app.region.center_lat: 52.4064
app.region.center_lng: 16.9252
app.region.radius_km: 12
```

Region (`App\Shared\Geo\Region`) jest używany wszędzie, gdzie potrzebne jest „domyślne miejsce”: `GET /api/v1/map`,
`/shelters` i `/fuel-stations` bez `bbox` zwracają jego prostokąt, mapa w Command Center startuje w jego środku,
komendy importu bez `--around`/`--bbox` pobierają tylko jego obszar, a fikstury rozkładają dane po dzielnicach Poznania.
Zgłoszenia spoza regionu nie są odrzucane; API je przyjmie, ale nie pojawią się w domyślnym oknie mapy.

## Dane: schrony, stacje paliw, fikstury

```bash
make console c="tarcza:shelters:import"                                      # rejestr krajowy z dane.gov.pl (CSV, ~15 MB), tylko region
make console c="tarcza:shelters:import --offline"                            # to samo ze zrzutu data/shelters (bez sieci; tak robi make demo)
make console c="tarcza:shelters:import --wojewodztwo=wielkopolskie"          # szerzej: całe województwo
make console c="tarcza:shelters:import --dry-run"                             # tylko policz, nic nie zapisuj
make console c="tarcza:fuel-stations:import"                                 # OpenStreetMap przez Overpass (OVERPASS_URL), tylko region
make console c="tarcza:fuel-stations:import --from-file=data/osm/fuel-stations-poznan.json"  # offline, z zapisanego zrzutu
make console c="tarcza:fixtures:load --reset --zones=8 --seed=42"            # różnorodne dane demo: 8 dzielnic Poznania
```

Import stacji wysyła zapytanie równolegle do wszystkich mirrorów z `OVERPASS_URL` i bierze pierwszą pełną odpowiedź.
Gdy żaden nie odpowie (publiczne instancje Overpass bywają przeciążone), import regionu wczytuje zrzut
`data/osm/fuel-stations-poznan.json` (dane © OpenStreetMap, ODbL). Zrzut odświeża się zapisaniem odpowiedzi Overpass dla regionu.

Import schronów jest idempotentny (upsert po identyfikatorze publicznym) i nie nadpisuje potwierdzeń obywateli;
gdy pobranie się nie uda, import regionu sam sięga po zrzut `data/shelters/punkty-schronienia-poznan.csv`.
`tarcza:fixtures:load --reset` czyści incydenty, zgłoszenia, alerty, źródła, zdjęcia, symulowane urządzenia oraz
stacje i schrony oznaczone jako `fixture`; zaimportowane schrony i stacje zostają i są używane przez fikstury,
jeśli leżą w promieniu 3 km od centrum dzielnicy (po imporcie OSM i dane.gov.pl fikstury nie tworzą już własnych).
Ten sam `--seed` daje identyczne dane. Każda dzielnica dostaje 8 incydentów obszarowych i 3 punktowe, 50 urządzeń;
większość to historia (zamknięte: potwierdzone, fałszywy alarm, wygasłe). Otwarty jest jeden incydent obszarowy
na dzielnicę, brak paliwa co drugą i problem ze schronem co czwartą (przy 8 dzielnicach: 14 otwartych, 74 zamknięte).

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
