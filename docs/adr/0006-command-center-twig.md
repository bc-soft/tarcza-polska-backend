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
