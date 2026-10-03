# Kontrakt API dla aplikacji Flutter

Pełna, generowana specyfikacja OpenAPI: `https://localhost/api/doc` (Swagger UI) i `/api/doc.json`.
`make openapi` zapisuje ją do `docs/openapi.json` dla generatora klienta Dart (`openapi-generator`, `dio`).

Konwencje:

* JSON, UTF-8, czasy w ISO 8601 (UTC), identyfikatory to UUID v7.
* Wszystko przestrzenne jest GeoJSON w WGS84, kolejność współrzędnych `[lng, lat]`.
* Błędy mają jeden kształt: `{"error": {"code": "validation_failed", "message": "...", "violations": [{"field": "lat", "message": "..."}]}}`.
* Autoryzacja: nagłówek `Authorization: Bearer <jwt>`.

## Citizen (`/api/v1`, rola `ROLE_CITIZEN`)

### Urządzenie

| Metoda | Ścieżka | Opis |
|---|---|---|
| POST | `/api/v1/devices` | Rejestracja anonimowego urządzenia. Body: `{platform?, appVersion?, pushToken?}`. Odpowiedź 201: `{deviceId, token}`. Publiczne. Token przechowuj w secure storage, ważny 30 dni. |
| GET | `/api/v1/devices/me` | Profil urządzenia (ostatnia lokalizacja, komórka H3). |
| PUT | `/api/v1/devices/me/location` | `{lat, lng, accuracyMeters?}` → `{h3Cell}`. Wysyłaj przy starcie, powrocie na foreground i przesunięciu > 150 m. |
| PUT | `/api/v1/devices/me/push-token` | `{pushToken}` po każdej rotacji tokenu FCM. |

### Zgłoszenia (DETECT)

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/reports/types` | Kategorie z etykietami PL do ekranu zgłoszenia. |
| POST | `/api/v1/reports` | `{type, lat, lng, description?}` → 202 `{reportId, h3Cell, createdAt}`. 429 przy > 10 zgłoszeń / 10 min. |
| GET | `/api/v1/reports/{id}` | Status mojego zgłoszenia: do jakiego incydentu trafiło i z jakim confidence. |

Wartości `type`: `power_outage`, `water_outage`, `fuel_shortage`, `road_blocked`, `shelter_issue`, `other_threat`.

### Mapa i incydenty (MAP)

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/map?bbox=minLng,minLat,maxLng,maxLat` | Jedna `FeatureCollection` na ekran mapy. `properties.kind` ∈ `incident` (poligon obszaru lub punkt, gdy obszar jeszcze pusty), `shelter` (punkt), `alert` (poligon). |
| GET | `/api/v1/incidents?lat&lng` | Otwarte incydenty; z `lat/lng` tylko te, których obszar zawiera moją pozycję. |
| GET | `/api/v1/incidents/{id}` | Widok publiczny: `confidenceLevel`, `confidenceScore`, `community.agreementPct`, `summary`, `area`. Bez surowych punktów. |

Poziomy `confidenceLevel`: `unverified`, `likely`, `high`, `confirmed` (kolory w panelu: szary, bursztyn, pomarańcz, czerwień).

### Aktywna weryfikacja (VERIFY)

Push FCM niesie `data: {type: "verification", verificationId, incidentId}`. Treść pytania dociągnij z API,
bo push może nie dojść albo dojść po czasie.

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/api/v1/verifications/pending` | Pytania czekające na odpowiedź (polluj na foreground). |
| GET | `/api/v1/verifications/{id}` | Jedno pytanie: `question`, `context`, `options`, `expiresAt`. |
| POST | `/api/v1/verifications/{id}/response` | `{answer: "yes" | "no" | "unknown"}`. 409 gdy już odpowiedziano, 410 gdy wygasło. |

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
| POST | `/api/v1/shelters/{id}/status` | `{status: "open" | "closed" | "full" | "unknown", comment?}`. |

### Zdrowie

`GET /api/v1/health` (publiczne) zwraca wersje PostGIS i H3.

## Command (`/api/command`, rola `ROLE_ANALYST`+)

| Metoda | Ścieżka | Rola | Opis |
|---|---|---|---|
| POST | `/api/command/login` | - | `{email, password}` → `{token}`. |
| GET | `/api/command/stats` | analyst | liczniki do nagłówka panelu. |
| GET | `/api/command/incidents?all=1` | analyst | feed incydentów (podsumowania + centroid + liczby komórek). |
| GET | `/api/command/incidents/{id}` | analyst | pełny detal: surowe raporty, hexy jako GeoJSON, źródła, rozbicie confidence, statystyki fal. Zapis w audit logu. |
| GET | `/api/command/incidents/{id}/sources` | analyst | źródła zewnętrzne. |
| POST | `/api/command/incidents/{id}/sources` | operator | ręczne dodanie oficjalnego źródła `{url, title, kind, credibility, publisher?, excerpt?}`. |
| POST | `/api/command/incidents/{id}/resolve` | operator | zamknięcie incydentu. |
| GET | `/api/command/alerts` | analyst | ostatnie komunikaty. |
| POST | `/api/command/alerts` | operator | `{title, body, severity, incidentId?, area?, ttlMinutes}`. Obszar = `area` albo aktualny obszar incydentu. |

## Realtime (Mercure)

Hub: `https://<host>/.well-known/mercure`. Tematy:

* `incidents` - każdy przeliczony incydent: `{event: "incident.updated", reason, incident: <summary>}`.
* `incidents/{id}` - `{event, reason, incident: <widok publiczny>, cells: <FeatureCollection hexów>}`.

Flutter może subskrybować SSE (`eventsource` / `flutter_client_sse`), ale na MVP wystarczy FCM + odświeżenie
`/api/v1/map` po powrocie na foreground.
