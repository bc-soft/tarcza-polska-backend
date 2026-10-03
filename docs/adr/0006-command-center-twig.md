# ADR 0006: Command Center w Twig + MapLibre + Mercure

Status: zaakceptowane, 2026-10-03 (do rewizji, jeśli pojawi się osoba frontendowa)

## Kontekst

Panel operatora musi pokazać mapę, hexy, confidence z rozbiciem, źródła i pozwolić wysłać alert.
Zespół jest backendowy; design to 20% oceny.

## Decyzja

Server-side Twig z Tailwind (CDN) i MapLibre GL JS (kafelki OpenFreeMap), aktualizacje live przez
Mercure (SSE) na tematach `incidents` i `incidents/{id}`. Akcje operatora to zwykłe formularze POST
z CSRF pod `/command/...`. Równolegle istnieje pełne API `/api/command/*` z JWT.

## Konsekwencje

* Brak osobnego builda frontu; panel działa od pierwszego `make up`.
* Jeśli dojdzie front w Next.js, konsumuje `/api/command/*` i Mercure bez zmian w backendzie.
* Tailwind z CDN jest akceptowalny na hackathon; na produkcję należałoby zbudować CSS (AssetMapper).

## Uzupełnienie (2026-10-03): design system i zakres panelu

Panel używa tokenów design systemu Tarcza współdzielonych z aplikacją mobilną: Primary `#D4213D`,
Secondary `#14213D`, Tertiary `#1B4DB1`, Neutral `#0F172A`, tło lawendowe, font Inter, karty `rounded-2xl`,
przyciski i filtry w formie pigułek. Tokeny są zdefiniowane raz w `templates/base.html.twig`
(`tailwind.config` + `window.TARCZA`), komponenty (odznaki, paski, statystyki, ikony) w `templates/command/_macros.html.twig`.
Mapa korzysta z jasnego stylu OpenFreeMap `positron`; paleta confidence: potwierdzone = tertiary (niebieski),
wysoka pewność = pomarańcz, prawdopodobne = bursztyn, niezweryfikowane = szary - tak jak legenda w aplikacji.

Zakres panelu: mapa sytuacyjna (warstwy incydentów, schronów i komunikatów), lista incydentów z filtrami,
detal z akcjami operatora (komunikat, ręczne źródło, ponowny research AI, zamknięcie) i osią czasu,
komunikaty, rejestr schronów (CRUD + status operatora), dziennik audytu i konta operatorów (admin).
Kontrolery webowe mapują formularze na DTO i wołają serwisy modułów (`ShelterManager`, `OperatorManager`,
`ResearchRequester`, `AlertPublisher`); walidacja DTO wraca jako flash (`WebFormValidator`).
