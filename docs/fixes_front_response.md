# Odpowiedź backendu na zgłoszenia aplikacji mobilnej

> Do [`fixes_front.md`](fixes_front.md). Stan po commitach z 2026-10-03 na `master`.
> Nowy zrzut specyfikacji: [`docs/openapi.json`](openapi.json) (wersja `1.1.0`) - skopiuj do repo mobile
> i przegeneruj klienta. Pełny kontrakt: [`api.md`](api.md), przewodnik: [`flutter-agent-guide.md`](flutter-agent-guide.md).

| # | Status | Co się zmieniło |
|---|---|---|
| 1 | zrobione | schematy odpowiedzi dla wszystkich `/api/v1/*` |
| 2 | zrobione | `ErrorResponse` + realne kody 4xx na każdym endpoincie |
| 3 | już było tak | `id` jest w `Feature.id` **i** `properties.id` dla każdego `kind` (test kontraktowy tego pilnuje) |
| 4 | zrobione | `DeviceRegistered { deviceId, token }` oba `required` |
| 5 | zrobione (opcja 2) | `source` w `PUT /devices/me/location`, `locationSource` w profilu |
| 6 | zrobione | push `location_refresh` + `PUT /devices/me/preferences` |
| 7 | decyzja: nie | zostajemy przy geokodowaniu systemowym, punkt zamknięty |
| 8 | zrobione | `statusLabel` incydentu, etykiety w `GET /reports/{id}` |
| 9 | zrobione | `verification_already_answered` / `verification_expired` |
| 10 | zrobione (+ naprawiony bug) | `Retry-After` i `error.retryAfter` przy 429 |
| 11 | zrobione | ponowna rejestracja odpina token FCM od starego urządzenia; 401 rozróżnia `token_expired` |
| 12 | zrobione | `expiresAt` w pushu, APNs time-sensitive; pola alertu na mapie już były |
| 13 | zrobione | `ETag` / `304` na `GET /map`, `GET /verifications/pending`, `GET /alerts` |

## 1. Schematy odpowiedzi

Nazwy jak w propozycji: `DeviceProfile`, `LocationUpdated`, `MapFeatureCollection`, `MapFeature`, `IncidentView`,
`ReportTypeOption`, `ReportAccepted`, `ReportStatusView`, `VerificationQuestion`, `VerificationResult`, `ShelterView`,
`AlertView`, `HealthStatus`, plus `GeoJsonGeometry`, `Community`, `DevicePreferences`, `DeviceRegistered` i enumy
`IncidentStatus`, `ConfidenceLevel`, `AlertSeverity` (istniejące `ReportType`, `ShelterStatus`, `VerificationAnswer`,
`LocationSource` są `$ref`-owane).

Jak to jest złożone (ważne dla generatora):

* `IncidentView = allOf [IncidentSummary, {area}]`, `ShelterView = allOf [ShelterSummary, {location}]`,
  `AlertView = allOf [AlertSummary, {area?}]`. Dzięki temu `properties` na mapie nie duplikują pól:
  `IncidentFeatureProperties = allOf [IncidentSummary, {kind: "incident"}]` itd.
* `MapFeature.properties` to `oneOf` trzech powyższych z `discriminator.propertyName = kind` i `mapping`.
  Jeśli `swagger_parser` nie poradzi sobie z `oneOf`/`allOf`, dajcie znać - płaski `MapFeatureProperties`
  (wszystko opcjonalne + `kind`) to kwestia kilku linii YAML.

**Dwie różnice względem Waszej listy pól wymaganych** (wynikają z realnego zachowania backendu, generator zrobi z nich `T?`):

* `IncidentView.area` jest `required`, ale **`nullable`**. Dopóki incydent ma status `detected` (nikt jeszcze nie odpowiedział
  na pytania), `area` to `null`; na mapie taki incydent idzie jako `Point` (centroid). Traktowaliście `area` jako nie-null -
  po regeneracji będzie `GeoJsonGeometry?`.
* `ShelterView.address` jest **`nullable`** (schrony dodawane ręcznie przez operatora mogą nie mieć adresu).

Resztę pogrubionych pól backend zawsze zwraca nie-null. `distanceMeters` jest `integer` (zaokrąglone metry), nie `number`.

W repo backendu jest teraz test kontraktowy (`tests/Contract`, odpalany w CI): każdy widok PHP jest renderowany z encji
i walidowany względem wygenerowanego `openapi.json` (wymagane pola, nullable, enumy, formaty, brak nieudokumentowanych
kluczy). Spec nie może już cicho rozjechać się z kodem.

## 2. Błędy

`ErrorResponse { error: { code, message, retryAfter?, violations?: [{ field, message }] } }`. Kody 4xx są dopisane
automatycznie na podstawie realnych reguł: `401` na wszystkim poza `POST /devices` i `GET /health`, `404` na każdym
`/{id}`, `422` na każdym endpoincie z body, a `400`, `409`, `410`, `429` tam, gdzie wskazaliście. `violations[].field`
bez zmian.

Dodatkowo odpowiedzi 401 z firewalla JWT mają teraz **ten sam kształt** co pozostałe błędy (wcześniej był to surowy
`{"code":401,"message":"..."}` z Lexika) i rozróżniają `unauthorized` (brak / zły token) od `token_expired`.

## 5. `source` lokalizacji

`UpdateLocationRequest.source?: "home" | "gps" | "background"` (brak = `gps`), `DeviceProfile.locationSource` (nullable,
zanim urządzenie wyśle pierwszą pozycję). Model nadal przechowuje **jedną** pozycję (wygrywa ostatni `PUT`), tak jak dziś -
opcję 3 (`homeLocation` obok `lastLocation`) odkładamy, bo wymaga zmiany wyboru adresatów pytań i alertów; jeśli po demo
okaże się potrzebna, dołożymy. Zgłoszenie (`POST /reports`) i symulator zapisują pozycję jako `gps`.

## 6. Push `location_refresh`

* `data: { "type": "location_refresh" }`, `notification`: „Czy nadal jesteś w tej okolicy?” / „Otwórz Tarczę, aby otrzymywać
  właściwe alerty.”, priorytet normalny (Android `normal`, APNs `5`).
* Reguły: `locationUpdatedAt` starsze niż **24 h**, maks. **1 / dobę** na urządzenie, nie między **21:00 a 8:00**
  (Europe/Warsaw), nie do urządzeń niewidzianych od 30 dni. Warunku „nie do `source=background` z ostatnich 24 h” nie trzeba
  było dopisywać osobno: takie urządzenie ma świeże `locationUpdatedAt`, więc nie jest „stale”. Jeśli urządzenie w trybie
  czuwania zamilknie na dobę, przypomnienie dostanie - i o to chyba chodzi.
* `PUT /api/v1/devices/me/preferences { "locationRefresh": bool }` → 204, odczyt w `DeviceProfile.preferences.locationRefresh`,
  domyślnie `true`. Wasz lokalny przełącznik można podpiąć 1:1.
* Scheduler sprawdza kandydatów co 15 min.

## 7. Geokodowanie - nie

Zostajemy przy `CLGeocoder` / `Geocoder`. Powody: zewnętrzna zależność (Nominatim ma limit 1 rps i politykę użycia, PRG
wymaga importu i hostowania danych), a backend adresu i tak nie powinien znać - do API trafiają tylko współrzędne
(`docs/08`). Jeśli Android bez Google Play Services okaże się realnym przypadkiem testowym, wróćmy do tematu
z konkretnym urządzeniem.

## 8. Etykiety

* `IncidentView.statusLabel` (także w `properties` na mapie i w Mercure): `detected` → „Wykryte”, `verifying` →
  „Trwa weryfikacja”, `active` → „Zasięg ustalony”, `resolved` → „Zakończone” (Wasze zapasowe etykiety, więc nic nie
  przeskoczy).
* `GET /reports/{id}`: `typeLabel` na zgłoszeniu oraz `incident.{type, typeLabel, statusLabel, confidenceLabel}` obok
  dotychczasowych pól.

## 9 i 10. Kody i `Retry-After`

`409` → `verification_already_answered`, `410` → `verification_expired`. Ogólne `410` to teraz `gone` (nie `http_error`).

Przy 429: nagłówek `Retry-After: <sekundy>` **i** `error.retryAfter` (int). Uwaga: do tej pory przekroczenie limitu
kończyło się w praktyce **500 `internal_error`**, bo wyjątek rate limitera nie był mapowany - jeśli widzieliście 500 po
serii zgłoszeń, to było to. Naprawione i objęte testem.

## 11. Ponowna rejestracja

Wybrana opcja: `POST /devices` (i `PUT /devices/me/push-token`) z tokenem FCM, który wisi na innym urządzeniu, **odpina
go od tamtego** - stare urządzenie bez tokenu nie dostaje pushy, a po 12 h (pytania) / 24 h (alerty) wypada też
z targetowania przez `last_seen_at`. Dlatego przy ponownej rejestracji po 401 **podawajcie `pushToken` od razu**,
jeśli go macie. Endpointu `refresh` nie dodajemy: token żyje 30 dni, a odpięcie tokenu załatwia problem podwójnych pytań.

## 12. Push weryfikacyjny i alert na mapie

* `data.expiresAt` (ISO 8601) w pushu `verification`. Przy okazji: pole `type` w `data` **nigdy nie niosło typu incydentu**
  (kolidowało z `type = "verification"` i było nadpisywane) - typ incydentu jest teraz pod **`data.incidentType`**.
  Jeśli coś czytało `data.type` jako typ incydentu, dostawało zawsze `"verification"`.
* iOS: `apns-push-type: alert`, `apns-priority: 10`, `aps.interruption-level: time-sensitive` dla `verification` i `alert`.
  Możecie dodać capability *Time Sensitive Notifications*.
* Alert w `GET /map`: `createdAt` i `active` już były w `properties` (schemat `AlertFeatureProperties` to potwierdza).

## 13. ETag

`GET /map`, `GET /verifications/pending`, `GET /alerts` zwracają `ETag` (hash treści) i `Cache-Control: private`; z pasującym
`If-None-Match` odpowiadają `304` bez treści. Oszczędza transfer i parsowanie, nie zapytania do bazy - to świadomy
kompromis na MVP.

## Drobiazgi dla Was

* Wersja spec podbita do `1.1.0`; `info.description` opisuje ETag i kopertę błędów.
* Walidacja enumów w body (np. `source: "xx"`) zwraca 422 z komunikatem Symfony „This value should be of type int|string.”
  - brzydkie, ale `violations[].field` wskazuje właściwe pole; poprawimy komunikat, jeśli ma trafić do użytkownika.
* `confidenceScore` to `number`; przy wartości całkowitej JSON ma `0`/`1` bez części ułamkowej (standard dla Dart: `(x as num).toDouble()`).
