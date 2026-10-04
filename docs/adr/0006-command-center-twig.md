# ADR 0006: Command Center w Twig + MapLibre + Mercure

Status: zaakceptowane, 2026-10-03

## Kontekst

Panel operatora musi pokazać mapę, hexy, confidence z rozbiciem, źródła i pozwolić wysłać alert.
Zespół jest backendowy, a panel ma wyglądać i działać jak produkt, nie jak narzędzie administracyjne.

## Decyzja

Server-side Twig z Tailwind (CDN) i MapLibre GL JS (kafelki OpenFreeMap), aktualizacje live przez
Mercure (SSE) na tematach `incidents` i `incidents/{id}`. Akcje operatora to zwykłe formularze POST
z CSRF pod `/command/...`. Równolegle istnieje pełne API `/api/command/*` z JWT.

## Konsekwencje

* Brak osobnego builda frontu; panel działa od pierwszego `make up`.
* Jeśli dojdzie front w Next.js, konsumuje `/api/command/*` i Mercure bez zmian w backendzie.
* Tailwind z CDN jest akceptowalny na hackathon; na produkcję należałoby zbudować CSS (AssetMapper).

## Uzupełnienie (2026-10-03): design system i zakres panelu

Panel używa tokenów design systemu Tarcza współdzielonych z aplikacją mobilną i prezentacją: ciemne tło
„civil resilience” (`#0D0E10`, powierzchnie `#15171A`–`#22252A`), Primary czerwień `#E0262F`, akcenty
ok / warn / high / tertiary, fonty Barlow Condensed (nagłówki), Inter (tekst) i JetBrains Mono (etykiety).
Tokeny są zdefiniowane raz w `templates/base.html.twig` (`tailwind.config` + `window.TARCZA`), komponenty
(odznaki, paski, statystyki, ikony) w `templates/command/_macros.html.twig`. Mapa korzysta z ciemnego stylu
OpenFreeMap; paleta confidence: potwierdzone = czerwień, wysoka pewność = pomarańcz, prawdopodobne = bursztyn,
niezweryfikowane = szary, tak jak legenda w aplikacji.

Zakres panelu: mapa sytuacyjna (warstwy incydentów, schronów i komunikatów), lista incydentów z filtrami,
detal z akcjami operatora (komunikat, ręczne źródło, ponowny research AI, zamknięcie) i osią czasu,
komunikaty, schrony i stacje paliw jako warstwy mapy (CRUD schronów przez `/api/command/shelters`),
dziennik audytu i konta operatorów (admin).
Kontrolery webowe mapują formularze na DTO i wołają serwisy modułów (`ShelterManager`, `OperatorManager`,
`ResearchRequester`, `AlertPublisher`); walidacja DTO wraca jako flash (`WebFormValidator`).
