<p align="center"><img src="docs/tarcza-polska-logo.png" alt="Tarcza Polska" width="220"></p>

# Tarcza Polska - backend

**Tarcza Polska** („Siła jest w nas”) to platforma odporności cywilnej: mieszkańcy zgłaszają zakłócenia
(brak prądu, wody, paliwa, nieprzejezdna droga, problem ze schronem, inne zagrożenie), a system sam dopytuje
ludzi w okolicy, wyznacza rzeczywisty zasięg problemu na siatce heksagonów H3, liczy wyjaśnialny wskaźnik
wiarygodności i informuje tych, których to dotyczy. Operator centrum zarządzania kryzysowego widzi wszystko
na żywo w Command Center. Projekt powstał na HackYeah 2026 w zadaniu **Defence**.

```
DETECT   POST /api/v1/reports              zgłoszenia -> incydent (klastrowanie: typ + czas + promień albo obiekt)
VERIFY   push „Czy masz teraz prąd?”        fale pytań po pierścieniach H3, tylko tam, gdzie granica jest nieznana
MAP      komórki positive / negative        obszar = unia heksów, granica domyka się sama; paliwo i schrony jako pinezki
INFORM   alert do poligonu                  push do urządzeń wewnątrz obszaru, automatycznie po potwierdzeniu lub ręcznie
```

To repozytorium to backend (API, silniki, Command Center). Aplikacja mobilna **Tarcza Citizen** (Flutter) żyje
w osobnym repozytorium i rozmawia z tym API; kontrakt jest w [docs/api.md](docs/api.md) i Swaggerze.

## Uruchomienie jedną komendą

Wymagany jest tylko Docker (Desktop albo Engine + Compose v2).

```bash
make demo
```

Komenda buduje obrazy, startuje stack (FrankenPHP, PostgreSQL + PostGIS + H3, Redis, worker), wykonuje migracje,
importuje **schrony z rejestru krajowego** i **stacje paliw z OpenStreetMap** dla Poznania (ze zrzutów w `data/`,
bez sieci), zakłada konta operatorów i ładuje dane demo: 8 dzielnic, wszystkie typy zgłoszeń i stany incydentów,
fale weryfikacji, źródła, alerty, zdjęcia i historia. Pierwszy start trwa kilka minut, kolejne kilkadziesiąt sekund.

| Co | Gdzie |
|---|---|
| Command Center | https://localhost/command (przy pierwszym wejściu zaakceptuj lokalny certyfikat Caddy; `https://localhost` przekierowuje tam) |
| Logowanie | `operator@tarcza.local` / `tarcza-demo` (`admin@tarcza.local` widzi też audyt i operatorów, `analyst@tarcza.local` tylko odczyt; to samo hasło) |
| Swagger / OpenAPI | https://localhost/api/doc |
| Zdrowie | `curl -k https://localhost/api/v1/health` |

Co klikać w panelu: mapa sytuacyjna z warstwami incydentów, schronów, stacji paliw i komunikatów; lista incydentów
z filtrami; detal incydentu z heksagonami, rozbiciem wskaźnika wiarygodności, falami weryfikacji, osią czasu,
źródłami zewnętrznymi i akcjami operatora (komunikat do obszaru, research AI, ręczne źródło, zamknięcie z werdyktem);
komunikaty; dziennik audytu i konta operatorów (jako admin).

Scenariusz na żywo (opcjonalnie, w drugim terminalu `make worker` pokazuje pracę silników):

```bash
make simulate      # 700 wirtualnych urządzeń i awaria prądu na Jeżycach: incydent rośnie w panelu w ciągu minuty
```

Research AI wymaga klucza w `.env.local` (`OPENAI_API_KEY`, albo `ANTHROPIC_API_KEY` z `RESEARCH_PROVIDER=anthropic`);
bez klucza działa wszystko poza nim. Pushe wymagają konta serwisowego Firebase; bez niego są logowane.
Szczegóły, zmienne i udostępnienie API w sieci lokalnej dla telefonów: [docs/getting-started.md](docs/getting-started.md).

## Jak to działa

* **Zgłoszenie to hipoteza, nie fakt.** Raporty tego samego typu w oknie czasu i promieniu łączą się w incydent.
  Brak paliwa i problem ze schronem odnoszą się do konkretnego obiektu i są weryfikowane przy nim (ADR 0010).
* **System sam zbiera brakujące dane.** Fala 0 pyta urządzenia w komórce incydentu i sześciu sąsiadach; każda
  kolejna pyta tylko komórki na granicy, o których nic nie wiadomo. Odpowiedź „u mnie prąd działa” domyka granicę.
* **Wiarygodność jest deterministyczna i wyjaśnialna.** `ConfidenceCalculator` liczy składowe (zgłoszenia,
  rozproszenie, tłum, świeżość, źródła zewnętrzne) i pokazuje rozbicie operatorowi. Poziom „potwierdzone” wymaga
  źródła zewnętrznego. Model językowy nie decyduje o niczym: dostarcza listę źródeł z web search (ADR 0003, 0005).
* **Obywatel widzi agregaty, operator widzi dowody.** Publiczny widok nigdy nie zawiera surowych pozycji
  zgłaszających; Command Center ma pełny obraz i każdą akcję w dzienniku audytu.
* **Wszystko długie dzieje się asynchronicznie** (Messenger + Redis, Scheduler), panel odświeża się przez Mercure.

Stack: Symfony 7.4 · PHP 8.4 · FrankenPHP · PostgreSQL 16 + PostGIS + h3-pg · Redis · Mercure · FCM · OpenAI lub Claude.
Jakość: `make qa` (php-cs-fixer, PHPStan poziom 8, PHPUnit: jednostkowe i kontraktowe wobec OpenAPI), to samo w CI.

## Dokumentacja

* [docs/architecture.md](docs/architecture.md): architektura, przepływ zdarzeń, model wiarygodności, bezpieczeństwo.
* [docs/api.md](docs/api.md) i [docs/openapi.json](docs/openapi.json): kontrakt API; [docs/flutter-agent-guide.md](docs/flutter-agent-guide.md): integracja aplikacji.
* [docs/getting-started.md](docs/getting-started.md): uruchomienie, zmienne, dane, debugowanie, produkcja.
* [docs/demo-scenario.md](docs/demo-scenario.md): trzyminutowy scenariusz pokazu.
* [docs/adr/](docs/adr/): decyzje projektowe (ADR 0001–0011), [docs/general-idea.md](docs/general-idea.md): opis produktu.
* [docs/presentation/tarcza-polska.pdf](docs/presentation/tarcza-polska.pdf): prezentacja (10 slajdów), [docs/visualisation/](docs/visualisation/): zrzuty.

## Zasoby zewnętrzne i użycie AI

Repozytorium zostało założone 3 października 2026 na HackYeah; cała praca jest w historii gita (Conventional Commits, małe kroki).

* **Dane**: rejestr „Punkty schronienia w Polsce” (dane.gov.pl, zrzut regionu w `data/shelters/`); stacje paliw
  z OpenStreetMap przez Overpass (zrzut w `data/osm/`, dane © autorzy OpenStreetMap, licencja ODbL); kafelki map
  OpenFreeMap / OpenMapTiles.
* **Usługi**: OpenAI Responses API lub Anthropic Claude API (research incydentów z web search, analiza zdjęć),
  Firebase Cloud Messaging (push). Wszystkie opcjonalne; bez kluczy system działa z pominięciem tych funkcji.
* **Biblioteki**: Symfony, Doctrine, FrankenPHP, LexikJWT, NelmioApiDoc, kreait/firebase-php, openai-php/client,
  anthropic-ai/sdk, MapLibre GL JS, Tailwind (pełna lista w `composer.json` i `importmap.php`).
* **AI w procesie**: kod, dokumentacja i materiały były tworzone z pomocą asystentów AI (m.in. Claude Code;
  instrukcje dla asystenta są w `CLAUDE.md`). Zespół zna i odpowiada za każdą decyzję architektoniczną
  i każdą linię, którą oddaje; decyzje są spisane w ADR-ach.
