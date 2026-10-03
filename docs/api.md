# Kontrakt API dla aplikacji Flutter

Pełna, generowana specyfikacja OpenAPI: `https://localhost/api/doc` (Swagger UI) i `/api/doc.json`.
`make openapi` zapisuje ją do `docs/openapi.json` dla generatora klienta Dart (`openapi-generator`, `dio`).

Konwencje:

* JSON, UTF-8, czasy w ISO 8601 (UTC), identyfikatory to UUID v7.
* Wszystko przestrzenne jest GeoJSON w WGS84, kolejność współrzędnych `[lng, lat]`.
* Błędy mają jeden kształt (`ErrorResponse` w OpenAPI): `{"error": {"code": "validation_failed", "message": "...", "violations": [{"field": "lat", "message": "..."}]}}`.
  Kody: `bad_request`, `unauthorized`, `token_expired`, `forbidden`, `not_found`, `conflict`, `gone`,
  `verification_already_answered`, `verification_expired`, `validation_failed`, `too_many_requests` (+ nagłówek `Retry-After`
  i `error.retryAfter` w sekundach), `internal_error`.
* Autoryzacja: nagłówek `Authorization: Bearer <jwt>`.
* `GET /map`, `GET /verifications/pending` i `GET /alerts` zwracają `ETag`; z `If-None-Match` odpowiadają `304 Not Modified`.
* Każda odpowiedź `/api/v1/*` ma schemat w `components.schemas` (`DeviceProfile`, `IncidentView`, `MapFeatureCollection`, ...);
  `tests/Contract` pilnuje, żeby widoki PHP nie rozjechały się ze specyfikacją.

## Citizen (`/api/v1`, rola `ROLE_CITIZEN`)

### Urządzenie

| Metoda | Ścieżka | Opis |
|---|---|---|
| POST | `/api/v1/devices` | Rejestracja anonimowego urządzenia. Body: `{platform?, appVersion?, pushToken?}`. Odpowiedź 201: `{deviceId, token}`. Publiczne. Token przechowuj w secure storage, ważny 30 dni. |
| GET | `/api/v1/devices/me` | Profil urządzenia (ostatnia lokalizacja, komórka H3, `locationSource`, `preferences`). |
| PUT | `/api/v1/devices/me/location` | `{lat, lng, accuracyMeters?, source?}` → `{h3Cell}`. `source` ∈ `home` (adres z onboardingu), `gps` (domyślne), `background` (tryb czuwania). 429 przy > 30 / min. |
| PUT | `/api/v1/devices/me/preferences` | `{locationRefresh: bool}` → 204. Zgoda na push `location_refresh` (domyślnie `true`). |
| PUT | `/api/v1/devices/me/push-token` | `{pushToken}` po każdej rotacji tokenu FCM. Token jest odpinany od innych urządzeń (jeden telefon = jeden token). |

### Zgłoszenia (DETECT)

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/reports/types` | Kategorie z etykietami PL do ekranu zgłoszenia. |
| POST | `/api/v1/reports` | `{type, lat, lng, description?}` → 202 `{reportId, h3Cell, createdAt}`. 429 przy > 10 zgłoszeń / 10 min. |
| GET | `/api/v1/reports/{id}` | Status mojego zgłoszenia: do jakiego incydentu trafiło (`incident.{type,typeLabel,status,statusLabel,confidenceLevel,confidenceLabel,confidenceScore}`), `null` zanim klastrowanie je przypisze. |

Wartości `type`: `power_outage`, `water_outage`, `fuel_shortage`, `road_blocked`, `shelter_issue`, `other_threat`.

### Mapa i incydenty (MAP)

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/map?bbox=minLng,minLat,maxLng,maxLat` | Jedna `FeatureCollection` na ekran mapy. `properties.kind` ∈ `incident` (poligon obszaru lub punkt, gdy obszar jeszcze pusty), `shelter` (punkt), `alert` (poligon). |
| GET | `/api/v1/incidents?lat&lng` | Otwarte incydenty; z `lat/lng` tylko te, których obszar zawiera moją pozycję. |
| GET | `/api/v1/incidents/{id}` | Widok publiczny: `status` + `statusLabel`, `confidenceLevel`, `confidenceScore`, `community.agreementPct`, `summary`, `area` (`null`, dopóki incydent jest tylko wykryty). Bez surowych punktów. |

Poziomy `confidenceLevel`: `unverified`, `likely`, `high`, `confirmed` (kolory w panelu: szary, bursztyn, pomarańcz, czerwień).

### Aktywna weryfikacja (VERIFY)

Push FCM niesie `data: {type: "verification", verificationId, incidentId, incidentType, expiresAt}` (iOS: `apns-priority: 10`,
`interruption-level: time-sensitive`). Treść pytania dociągnij z API, bo push może nie dojść albo dojść po czasie.

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/verifications/pending` | Pytania czekające na odpowiedź (polluj na foreground). |
| GET | `/api/v1/verifications/{id}` | Jedno pytanie: `question`, `context`, `options`, `expiresAt`. |
| POST | `/api/v1/verifications/{id}/response` | `{answer: "yes" | "no" | "unknown"}`. 409 `verification_already_answered`, 410 `verification_expired`. |

Odpowiedź jest surowa (TAK na „czy masz prąd?”), normalizację robi backend.

### Alerty (INFORM)

Push FCM niesie `data: {type: "alert", alertId}`.

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/alerts?lat&lng` | Aktywne alerty obejmujące moją pozycję. |
| GET | `/api/v1/alerts/{id}` | Szczegóły z poligonem. |

### Schrony

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/shelters?lat&lng` | 10 najbliższych z `distanceMeters`. |
| GET | `/api/v1/shelters?bbox=` | Schrony w oknie mapy. |
| POST | `/api/v1/shelters/{id}/status` | `{status: "open" | "closed" | "unknown", occupancy?: "plenty" | "limited" | "full", comment?}`. `status: "full"` nadal działa i oznacza `open` + `occupancy: full`. |

Każdy schron ma `occupancy` i `occupancyLabel` (`unknown` · Brak danych o miejscach, `plenty` · Dużo miejsc, `limited` · Mało miejsc, `full` · Pełny). Pole ma sens tylko przy `status: open`.

### Historia incydentu

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/incidents/{id}/timeline` | Oś czasu, najstarsze pierwsze, z ETag. Wpisy `{type, label, at, details}`; typy: `created`, `wave_started`, `wave_closed`, `area_changed`, `confidence_changed`, `research_completed`, `source_added`, `alert_published`, `photo_attached`, `resolved`. `details` to małe liczby/etykiety (np. `{positiveCells, negativeCells, unknownCells, yes, no}` dla `area_changed`, `{from, to, score}` dla `confidence_changed`), nigdy pozycje. |

### Procedury i tryb offline

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/procedures?type=` | Listy kontrolne „co robić, gdy…” po polsku, posortowane po priorytecie. Z `type` zwraca procedury dla typu plus ogólne. ETag. |
| GET | `/api/v1/offline-bundle?lat&lng&radiusMeters=15000` | Paczka do cache: schrony w promieniu (najbliższe pierwsze, z `distanceMeters`), aktywne alerty, otwarte incydenty i procedury, plus `generatedAt` i `validUntil` (24 h). ETag. Odśwież po `validUntil` albo przy powrocie na pierwszy plan. |

### Zdjęcia do zgłoszenia

| Metoda | Ścieżka | Opis |
|---|---|---|
| POST | `/api/v1/reports/{id}/photo` | `multipart/form-data`, pole `photo` (JPEG/PNG/WebP, do 10 MB, maks. 3 na zgłoszenie). Backend usuwa EXIF/GPS, skaluje do 1600 px i zwraca 202 `{photoId, reportId, status: "processing", width, height, bytes, createdAt}`. Błędy: 409 `photo_limit`, 413 `payload_too_large`, 415 `unsupported_media_type`, 429. Zdjęcia widzi tylko operator; analiza (czy pasuje do zgłoszenia, moderacja) działa w tle. |

### Zdrowie

`GET /api/v1/health` (publiczne) zwraca wersje PostGIS i H3.

### Push (FCM)

| `data.type` | Pola `data` | Priorytet |
|---|---|---|
| `verification` | `verificationId`, `incidentId`, `incidentType`, `expiresAt` | wysoki, time-sensitive |
| `alert` | `alertId` | wysoki, time-sensitive |
| `location_refresh` | - | normalny; gdy pozycja starsza niż 24 h, maks. 1 / dobę, nie w godz. 21-8, tylko przy `preferences.locationRefresh = true` |

## Command (`/api/command`, rola `ROLE_ANALYST`+)

| Metoda | Ścieżka | Rola | Opis |
|---|---|---|---|
| POST | `/api/command/login` | - | `{email, password}` → `{token}`. |
| GET | `/api/command/stats` | analyst | liczniki do nagłówka panelu. |
| GET | `/api/command/incidents?all=1` | analyst | feed incydentów (podsumowania + centroid + liczby komórek). |
| GET | `/api/command/incidents/{id}` | analyst | pełny detal: surowe raporty (z `reporterReputation`), hexy jako GeoJSON, źródła, rozbicie confidence, statystyki fal, pełna `timeline`, `photos`, `resolution`. Zapis w audit logu. |
| GET | `/api/command/incidents/{id}/sources` | analyst | źródła zewnętrzne. |
| POST | `/api/command/incidents/{id}/sources` | operator | ręczne dodanie oficjalnego źródła `{url, title, kind, credibility, publisher?, excerpt?}`. |
| POST | `/api/command/incidents/{id}/resolve` | operator | zamknięcie incydentu z werdyktem: body opcjonalne `{resolution: "confirmed" | "false_alarm", note?}` (domyślnie `confirmed`). Werdykt zasila reputację zgłaszających i odpowiadających. |
| GET | `/api/command/incidents/{id}/photos?includeUnsafe=1` | analyst | zdjęcia od obywateli z analizą (`relevant`, `matchesType`, `description`, `unsafe`, `confidence`) i `url` do pliku. |
| GET | `/api/command/photos/{id}/file` | analyst | plik JPEG (po usunięciu metadanych); każde pobranie w `audit_log`. |
| GET | `/api/command/alerts` | analyst | ostatnie komunikaty. |
| POST | `/api/command/alerts` | operator | `{title, body, severity, incidentId?, area?, ttlMinutes}`. Obszar = `area` albo aktualny obszar incydentu. |
| GET | `/api/command/shelters` | analyst | rejestr schronów (wszystkie, alfabetycznie). |
| POST | `/api/command/shelters` | operator | `{name, lat, lng, address?, capacity?}` - nowy schron (`source = operator`). |
| PUT | `/api/command/shelters/{id}` | operator | edycja danych podstawowych `{name, lat, lng, address?, capacity?}`. |
| POST | `/api/command/shelters/{id}/status` | operator | `{status}` - nadpisanie statusu przez operatora (nie liczy się jako potwierdzenie obywatela). |

### Panel Twig (`/command`, sesja)

Te same akcje są dostępne z panelu jako formularze POST z CSRF: `/command` (mapa sytuacyjna), `/command/incidents`
(lista z filtrami `status`, `type`, `level`, `all=1`), `/command/incidents/{id}` (detal + akcje: komunikat, źródło,
ponowny research AI, zamknięcie), `/command/alerts`, `/command/shelters`, a dla `ROLE_ADMIN` `/command/audit`
i `/command/operators` (konta i role). Każda akcja trafia do `audit_log`.

## Realtime (Mercure)

Hub: `https://<host>/.well-known/mercure`. Tematy:

* `incidents` - każdy przeliczony incydent: `{event: "incident.updated", reason, incident: <summary>}`.
* `incidents/{id}` - `{event, reason, incident: <widok publiczny>, cells: <FeatureCollection hexów>}`.

Flutter może subskrybować SSE (`eventsource` / `flutter_client_sse`), ale na MVP wystarczy FCM + odświeżenie
`/api/v1/map` po powrocie na foreground.
