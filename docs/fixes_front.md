# Zgłoszenia od aplikacji mobilnej do backendu

> Dla agenta / programisty repo **tarcza-polska-backend**. Spisane po pierwszej implementacji
> aplikacji Tarcza Citizen (Flutter) na podstawie [`openapi.json`](openapi.json) i
> [`backend-specs.md`](backend-specs.md). Aplikacja działa już z obecnym API — poniższe punkty
> usuwają obejścia po stronie mobile albo zamykają otwarte decyzje **[DO UZGODNIENIA]**.
>
> Priorytety: **P1** — blokuje regenerację klienta / grozi błędem parsowania, **P2** — decyzja
> produktowa potrzebna przed demo, **P3** — usprawnienie.
>
> Po każdej zmianie kontraktu: `make openapi` i skopiowanie pliku do `docs/openapi.json` w repo
> mobile (klient generujemy `swagger_parser` → Retrofit).

## Spis

| # | Priorytet | Temat |
|---|---|---|
| 1 | P1 | Schematy odpowiedzi w `openapi.json` |
| 2 | P1 | Schemat błędów i kody 4xx w `openapi.json` |
| 3 | P1 | `id` w `properties` każdego Feature z `GET /map` |
| 4 | P1 | `required` w schemacie `POST /devices` |
| 5 | P2 | Adres domowy vs ostatnia pozycja (`source` w `PUT /devices/me/location`) |
| 6 | P2 | Push `location_refresh` + wyłączenie przypomnień przez użytkownika |
| 7 | P2 | Geokodowanie adresu — decyzja |
| 8 | P3 | Etykiety tam, gdzie ich brakuje (`statusLabel` incydentu, skrót incydentu w raporcie) |
| 9 | P3 | Odrębne kody błędów dla 409 / 410 przy weryfikacji |
| 10 | P3 | `Retry-After` przy 429 |
| 11 | P3 | Ponowna rejestracja po 401 bez osieroconych urządzeń |
| 12 | P3 | Pola alertu w `GET /map` i push weryfikacyjny z `expiresAt` |
| 13 | P3 | Tańszy polling mapy (`ETag`) |

---

## 1. [P1] Schematy odpowiedzi w `openapi.json`

**Problem.** Poza `POST /api/v1/devices` żaden endpoint `/api/v1/*` nie ma schematu odpowiedzi.
`swagger_parser` generuje wtedy metody zwracające `Future<void>` — odpowiedź jest tracona.
Mobile obchodzi to ręcznym interfejsem Retrofit (`lib/data/remote/citizen_api.dart`) i ręcznymi
DTO (`lib/data/remote/dto/dtos.dart`), które trzeba będzie usunąć.

**Prośba.** Dodać `components.schemas` i podpiąć je w `responses.<kod>.content.application/json.schema`
(np. przez atrybuty `#[OA\Response(... content: new Model(type: ...))]` w Nelmio).
Proponowane nazwy (spójne z `docs/05-architektura-flutter.md`):

| Endpoint | Kod | Schemat |
|---|---|---|
| `GET /devices/me` | 200 | `DeviceProfile` |
| `PUT /devices/me/location` | 200 | `LocationUpdated` |
| `GET /map` | 200 | `MapFeatureCollection` |
| `GET /incidents` | 200 | `array<IncidentView>` |
| `GET /incidents/{id}` | 200 | `IncidentView` |
| `GET /reports/types` | 200 | `array<ReportTypeOption>` |
| `POST /reports` | 202 | `ReportAccepted` |
| `GET /reports/{id}` | 200 | `ReportStatusView` |
| `GET /verifications/pending` | 200 | `array<VerificationQuestion>` |
| `GET /verifications/{id}` | 200 | `VerificationQuestion` |
| `POST /verifications/{id}/response` | 200 | `VerificationResult` |
| `GET /shelters` | 200 | `array<ShelterView>` |
| `GET /shelters/{id}`, `POST /shelters/{id}/status` | 200 | `ShelterView` |
| `GET /alerts` | 200 | `array<AlertView>` |
| `GET /alerts/{id}` | 200 | `AlertView` |
| `GET /health` | 200 | `HealthStatus` |

Kształty — dokładnie to, co dziś parsuje aplikacja. **Pogrubione pola mobile traktuje jako wymagane**
(brak = błąd parsowania), pozostałe jako opcjonalne / nullable. Enumy jako `$ref` do istniejących
`ReportType`, `ShelterStatus`, `VerificationAnswer` + nowe `IncidentStatus`, `ConfidenceLevel`,
`AlertSeverity`.

```text
GeoJsonGeometry      { **type**: "Point" | "Polygon" | "MultiPolygon", **coordinates**: [...] }   // [lng, lat]

DeviceProfile        { **deviceId**, platform, hasPushToken: bool, lastLocation: GeoJsonGeometry(Point)|null,
                       h3Cell|null, locationUpdatedAt: date-time|null }
LocationUpdated      { **h3Cell** }

Community            { **reports**: int, **answers**: int, agreementPct: int|null }
IncidentView         { **id**, **type**: ReportType, **typeLabel**, **status**: IncidentStatus,
                       **confidenceLevel**: ConfidenceLevel, **confidenceLabel**, **confidenceScore**: number 0–1,
                       **startedAt**, **lastActivityAt**, lastConfirmedAt|null, **community**: Community,
                       summary|null, **area**: GeoJsonGeometry }

ShelterView          { **id**, **name**, **address**, **location**: GeoJsonGeometry(Point),
                       **status**: ShelterStatus, **statusLabel**, capacity: int|null,
                       lastConfirmedAt|null, **confirmationCount**: int,
                       distanceMeters: number   // tylko w wariancie ?lat&lng }

AlertView            { **id**, **title**, **body**, **severity**: AlertSeverity, incidentId|null,
                       **createdAt**, **expiresAt**, **active**: bool,
                       area: GeoJsonGeometry   // tylko GET /alerts/{id} }

VerificationQuestion { **verificationId**, **incidentId**, **type**: ReportType, **typeLabel**,
                       **question**, **context**, **options**: VerificationAnswer[], **sentAt**,
                       **expiresAt**, **answered**: bool }
VerificationResult   { **verificationId**, **incidentId**, **thanks** }

ReportTypeOption     { **value**: ReportType, **label** }
ReportAccepted       { **reportId**, **h3Cell**, **createdAt** }
ReportStatusView     { **reportId**, **type**: ReportType, **createdAt**,
                       incident: { **id**, **status**, **confidenceLevel**, **confidenceScore** } | null }

MapFeatureCollection { **type**: "FeatureCollection", **features**: MapFeature[] }
MapFeature           { **type**: "Feature", **id**, **geometry**: GeoJsonGeometry,
                       **properties**: oneOf(IncidentView bez `area` | ShelterView bez `location` | AlertView bez `area`)
                       + **kind**: "incident" | "shelter" | "alert"   // discriminator }
```

`oneOf` z `discriminator.propertyName: kind` na `properties` byłby idealny — generator zrobi z tego
union. Jeśli to kłopotliwe w Nelmio, wystarczy jeden płaski schemat `MapFeatureProperties`
ze wszystkimi polami opcjonalnymi + wymaganym `kind`.

**Kryterium akceptacji:** po `dart run swagger_parser` żadna metoda z `/api/v1/*` (poza
`PUT push-token` → 204) nie zwraca `void`.

---

## 2. [P1] Schemat błędów i kody 4xx w `openapi.json`

**Problem.** Kształt `{"error": {"code", "message", "violations"}}` jest opisany tylko w
`backend-specs.md` §2. W spec brakuje odpowiedzi błędów (jest tylko „goły” `429` dla
`POST /reports` i `409`/`410` dla odpowiedzi na pytanie, bez treści).

**Prośba.**

- Schemat `ErrorResponse { **error**: { **code**, **message**, violations: [{ **field**, **message** }] } }`.
- Podpiąć go pod realnie zwracane kody:

| Endpoint | Kody |
|---|---|
| wszystkie z JWT | `401` |
| `GET /…/{id}` | `404` |
| `GET /map`, `GET /shelters` (zły `bbox`) | `400` |
| `PUT /devices/me/location`, `POST /reports`, `POST /shelters/{id}/status`, `POST /verifications/{id}/response`, `POST /devices` | `422` |
| `POST /reports`, `PUT /devices/me/location` | `429` |
| `POST /verifications/{id}/response` | `409`, `410` |

- `violations[].field` = nazwa pola body (już tak jest — prosimy tylko o utrzymanie, mobile mapuje
  to na pola formularza, np. `description` w zgłoszeniu).

---

## 3. [P1] `id` w `properties` każdego Feature z `GET /map`

**Problem.** Przykład w `backend-specs.md` §5 ma `properties.id` tylko dla incydentu. Dla schronu
i alertu nie jest jasne, czy `id` jest w `properties`, czy tylko w `Feature.id`. Mobile od teraz
bierze `Feature.id` jako zapas, ale Feature bez żadnego `id` jest pomijany na mapie.

**Prośba.** Zawsze wypełniać `Feature.id` **i** `properties.id` (to samo UUID) dla wszystkich
`kind`. Potrzebne do nawigacji: `/shelter/{id}`, `/alerts/{id}`, `/incident/{id}`.

---

## 4. [P1] `required` w schemacie `POST /devices`

**Problem.** Odpowiedź `201` ma `deviceId` i `token` jako opcjonalne — generator robi z nich
`String?`, a mobile musi ręcznie sprawdzać `null`.

**Prośba.** Oznaczyć oba pola jako `required` (zawsze są zwracane).

Przy okazji (`RegisterDeviceRequest`): `platform`, `appVersion`, `pushToken` są nullable i bez
`required` — to OK, mobile wysyła je jawnie (`null`, gdy brak tokena FCM).

---

## 5. [P2] Adres domowy vs ostatnia pozycja

**Kontekst.** Model lokalizacji z `docs/08-bezpieczenstwo-prywatnosc.md`: adres domowy
(onboarding) + GPS przy otwarciu aplikacji + opcjonalny tryb czuwania w tle. Dziś wszystkie trzy
źródła trafiają w `PUT /devices/me/location` i nadpisują jedną pozycję.

**Co robi mobile teraz.**

- Onboarding / zmiana adresu → `PUT /devices/me/location` ze współrzędnymi domu.
- Otwarcie aplikacji ze zgodą na GPS → `PUT` z pozycją GPS (`accuracyMeters` z urządzenia).
- Tryb czuwania → `PUT` po zmianie komórki H3 (iOS wysyła natywnie przez `URLSession`, także po
  zamknięciu aplikacji; Android z foreground service / `workmanager` co 15 min).
- Użytkownik może jednym tapnięciem „wrócić do adresu domowego” (kolejny `PUT` z domem).

**Do decyzji (opcje):**

1. **Bez zmian w modelu** (obecne zachowanie) — jedna pozycja, wygrywa ostatni `PUT`.
   Minus: po powrocie GPS-owa pozycja zastępuje dom do następnej aktualizacji.
2. **Rekomendowane minimum:** opcjonalne pole `source: "home" | "gps" | "background"` w
   `UpdateLocationRequest` i zwracane w `DeviceProfile` (`locationSource`). Backend może wtedy np.
   nie wysyłać `location_refresh` urządzeniom w trybie czuwania i lepiej ważyć świeżość pozycji.
   Mobile już śledzi to pole lokalnie — wystarczy je wysłać.
3. **Osobne pole** `homeLocation` (`PUT /devices/me/home` `{lat, lng}`) obok `lastLocation`;
   wybór pytanych / adresatów alertu po obu pozycjach (dom ∪ ostatnia). Najlepsze pokrycie,
   ale więcej pracy po stronie wyboru odbiorców.

Prywatność bez zmian: do backendu trafiają tylko współrzędne, nigdy tekst adresu; bez historii.

---

## 6. [P2] Push `location_refresh` + wyłączenie przypomnień

**Kontekst.** Push okresowo zachęca do otwarcia aplikacji, żeby odświeżyć pozycję
(`docs/08`, `docs/07`). Mobile już obsługuje `data.type = "location_refresh"`: po otwarciu
pobiera GPS i wysyła `PUT /devices/me/location`. W ustawieniach jest przełącznik „Przypomnienia
o lokalizacji”, na razie tylko lokalny.

**Prośba.**

- Nowy typ pusha `data: { "type": "location_refresh" }` (+ `notification`).
  Proponowana treść: tytuł „Czy nadal jesteś w tej okolicy?”, treść „Otwórz Tarczę, aby
  otrzymywać właściwe alerty.”
- Wysyłka tylko gdy `locationUpdatedAt` starsze niż **24 h**, maks. **1 dziennie**, nie w nocy
  (np. 21:00–8:00), nie do urządzeń, które w ostatnich 24 h wysłały pozycję z `source=background`
  (por. pkt 5).
- Zgoda użytkownika: `PUT /api/v1/devices/me/preferences` `{ "locationRefresh": bool }` (204),
  odczyt w `DeviceProfile.preferences.locationRefresh`. Domyślnie `true`.
- Udokumentować w `backend-specs.md` §10 i w `openapi.json`.

---

## 7. [P2] Geokodowanie adresu — decyzja

**Co robi mobile teraz.** Geokodowanie systemowe (`geocoding`: iOS `CLGeocoder`, Android
`Geocoder`). Działa na symulatorze iOS dla adresów typu „Dabrowskiego 42, Poznan”.

**Do decyzji.** Czy backend da endpoint, np. `GET /api/v1/geocode?q=...` → `[{label, lat, lng}]`
(np. przez Nominatim / PRG z cache)? Zalety: spójne wyniki na obu platformach, polskie etykiety,
działanie na Androidzie bez Google Play Services. Jeśli **nie** — zostajemy przy systemowym
geokodowaniu i ten punkt zamykamy.

---

## 8. [P3] Brakujące etykiety

Zasada z `docs/03`: etykiety po polsku przychodzą z backendu, mobile ich nie tłumaczy.

- **Incydent:** brak `statusLabel` (`detected` / `verifying` / `active` / `resolved`).
  Mobile ma lokalne etykiety zapasowe: „Wykryte”, „Trwa weryfikacja”, „Zasięg ustalony”,
  „Zakończone”. Prośba o `statusLabel` w `IncidentView` (i w `properties` na mapie).
- **`GET /reports/{id}` → `incident`:** dodać `typeLabel` i `confidenceLabel` (ekran po wysłaniu
  zgłoszenia pokazuje „Zgłoszenie dołączono do zagrożenia …”).

---

## 9. [P3] Odrębne kody błędów dla 409 / 410 przy weryfikacji

Dziś `410` ma `error.code = "http_error"`, a `409` ma ogólne `conflict`. Mobile rozróżnia je po
statusie HTTP, więc to działa, ale prośba o jednoznaczne kody:
`409` → `verification_already_answered`, `410` → `verification_expired`.

---

## 10. [P3] `Retry-After` przy 429

Przy limicie zgłoszeń (10 / 10 min) mobile pokazuje „Spróbuj ponownie za kilka minut” i nie
ponawia automatycznie. Nagłówek `Retry-After: <sekundy>` (albo `error.retryAfter`) pozwoli podać
dokładny czas. Dotyczy też `PUT /devices/me/location` (30 / min).

---

## 11. [P3] Ponowna rejestracja po 401 bez osieroconych urządzeń

Zgodnie z §3 mobile przy `401` usuwa token i robi `POST /devices` — powstaje nowe urządzenie,
a stare (z ostatnią pozycją i tokenem FCM) zostaje w bazie i nadal może dostawać pytania / pushe.

Propozycje (dowolna):

- `POST /api/v1/devices/refresh` z wygasłym tokenem (podpis nadal weryfikowalny) → nowy JWT dla
  tego samego `deviceId`;
- albo przy `POST /devices` z tym samym `pushToken` — dezaktywacja poprzedniego urządzenia z tym
  tokenem (żeby jeden telefon nie dostawał dwóch pytań);
- albo wygaszanie urządzeń bez aktywności > 30 dni.

---

## 12. [P3] Pola alertu na mapie i push weryfikacyjny

- **Alert w `GET /map`:** prośba o `createdAt` i `active` w `properties` (jak w `GET /alerts`),
  żeby karta alertu na mapie i lista alertów miały ten sam model.
- **Push `verification`:** dodać `expiresAt` do `data`. Mobile i tak dociąga
  `GET /verifications/{id}`, ale przy słabym zasięgu pozwoli od razu odrzucić wygasłe pytanie
  bez dodatkowego zapytania.
- iOS: push weryfikacyjny wysyłać z `apns-push-type: alert`, `apns-priority: 10` i
  `interruption-level: time-sensitive` (pytanie żyje 90 s). Wymaga capability
  *Time Sensitive Notifications* po stronie aplikacji — dodamy, jeśli backend to wyśle.

---

## 13. [P3] Tańszy polling mapy

Mapa odpytuje `GET /map?bbox` co 30 s (plus `pending` i `alerts`). Przy setkach urządzeń w
demo (`make simulate`) warto dodać `ETag` / `If-None-Match` → `304 Not Modified` na `GET /map`,
`GET /verifications/pending` i `GET /alerts`. Mobile obsłuży `304` bez zmian w UI.

---

## Bez zmian po stronie backendu (dla informacji)

- **Nagłówek `ngrok-skip-browser-warning: 1`** — mobile dokleja go do każdego żądania (także z tła
  na iOS i w `workmanager` na Androidzie), więc tunel ngrok nie podmienia odpowiedzi na stronę
  ostrzeżenia. Backend nie musi nic robić; jeśli kiedyś dojdzie CORS dla wersji web, nagłówek
  musi być na liście `Access-Control-Allow-Headers`.
- **Throttling lokalizacji** — mobile wysyła pozycję tylko po zmianie komórki H3 res 9, nie
  częściej niż co 15 s (odświeżenie tej samej komórki co 6 h), więc limit 30 / min wystarcza.
- **Rejestracja z symulatora** — mobile wysyła `platform: "simulator"` na symulatorze iOS.
- **Confidence** — mobile niczego nie liczy, tylko prezentuje `confidenceLevel`, `confidenceScore`,
  `community.agreementPct`.
