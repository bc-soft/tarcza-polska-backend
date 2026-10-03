# Tarcza Polska: przewodnik integracyjny dla repo aplikacji Flutter

Ten plik jest pisany dla agenta (i ludzi) pracujących w repozytorium aplikacji mobilnej **Tarcza Citizen**.
Zawiera wszystko, co trzeba wiedzieć o backendzie, żeby zbudować aplikację bez zaglądania do kodu PHP.
Źródłem prawdy dla kształtu endpointów jest [`openapi.json`](openapi.json) (OpenAPI 3) i Swagger UI pod `/api/doc`.

Skrót produktu: obywatel zgłasza problem (brak prądu, wody, paliwa, nieprzejezdna droga, problem ze schronem,
inne zagrożenie). Backend łączy zgłoszenia w incydent, **sam dopytuje** użytkowników w okolicy pytaniem
TAK / NIE / NIE WIEM, wyznacza zasięg problemu na mapie i pozwala operatorowi wysłać komunikat do osób
w obszarze. Aplikacja ma cztery zadania: pokazać mapę, przyjąć zgłoszenie, zadać pytanie weryfikacyjne
i pokazać alert.

---

## 1. Środowiska i adresy

| Co | Gdzie |
|---|---|
| Backend na komputerze backendowca, ta sama sieć Wi-Fi | `http://<IP-tego-komputera>` po `make lan` w repo backendu (np. `http://192.168.2.2`); działa z telefonu, emulatora Androida i symulatora iOS bez żadnej konfiguracji TLS |
| Backend u Ciebie lokalnie (Docker) | `https://localhost` (certyfikat lokalny Caddy) albo `make lan` → `http://localhost`; z emulatora Androida `http://10.0.2.2` |
| Zdalnie | adres tunelu (`ngrok http 80` / `cloudflared`) przekazany przez backendowca |
| Swagger UI | `/api/doc`, JSON: `/api/doc.json` |
| Zdrowie backendu | `GET /api/v1/health` (publiczne) |

Zalecenie: w aplikacji trzymaj `API_BASE_URL` w konfiguracji buildu (`--dart-define=API_BASE_URL=http://192.168.2.2`)
i w developmencie używaj zwykłego HTTP, żeby nie walczyć z certyfikatem na urządzeniach. Android od wersji 9
blokuje czysty HTTP, więc w `AndroidManifest.xml` ustaw `android:usesCleartextTraffic="true"` dla buildów
debug (albo `network_security_config` z wyjątkiem dla adresu backendu). Na iOS analogicznie
`NSAppTransportSecurity` → `NSAllowsArbitraryLoads` w `Info.plist` dla debug. Produkcyjny adres będzie miał
poprawny certyfikat Let's Encrypt i tych wyjątków nie potrzebuje.

Wszystkie endpointy obywatela są pod prefiksem **`/api/v1`**. Endpointy `/api/command/*` są dla panelu
operatora i aplikacja mobilna ich nie używa.

---

## 2. Konwencje

* JSON, UTF-8. Czas w ISO 8601 ze strefą (backend zwraca `+02:00` lub `Z`), parsuj przez `DateTime.parse`.
* Identyfikatory to UUID (v7) jako string.
* **Geografia zawsze jako GeoJSON**, współrzędne w kolejności **`[lng, lat]`** (długość, szerokość).
  W zapytaniach HTTP (query/body) używamy osobnych pól `lat` i `lng`.
* Autoryzacja: nagłówek `Authorization: Bearer <token>` na każdym wywołaniu poza `POST /api/v1/devices` i `GET /api/v1/health`.
* Błędy mają zawsze ten sam kształt:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Request payload is invalid",
    "violations": [{ "field": "lat", "message": "This value should be between -90 and 90." }]
  }
}
```

| HTTP | `error.code` | Kiedy |
|---|---|---|
| 400 | `bad_request` | zły parametr query (np. `bbox`) |
| 401 | `unauthorized` | brak lub niepoprawny token → zarejestruj urządzenie ponownie |
| 401 | `token_expired` | token wygasł (30 dni) → zarejestruj urządzenie ponownie |
| 403 | `forbidden` | brak uprawnień |
| 404 | `not_found` | zły identyfikator albo cudzy zasób |
| 409 | `verification_already_answered` | pytanie weryfikacyjne już ma odpowiedź |
| 410 | `verification_expired` | pytanie weryfikacyjne wygasło |
| 422 | `validation_failed` | błędne body, lista pól w `violations` |
| 429 | `too_many_requests` | limit zgłoszeń: 10 na 10 minut; lokalizacja: 30 na minutę. Nagłówek `Retry-After` i `error.retryAfter` podają sekundy do odblokowania |
| 500 | `internal_error` | błąd backendu |

Pole `violations[].field` odpowiada nazwie pola w body, więc można je mapować na pola formularza.
Kształt błędu to schemat `ErrorResponse` w `openapi.json`; każdy endpoint ma wypisane kody 4xx, które realnie zwraca.

Endpointy odpytywane cyklicznie (`GET /map`, `GET /verifications/pending`, `GET /alerts`) zwracają nagłówek `ETag`.
Wyślij go z powrotem w `If-None-Match`, a dostaniesz `304 Not Modified` bez treści, gdy nic się nie zmieniło.

---

## 3. Tożsamość urządzenia (bez kont)

Nie ma rejestracji użytkownika, e-maila ani hasła. Każda instalacja aplikacji rejestruje **anonimowe urządzenie**
i dostaje token JWT ważny 30 dni.

```http
POST /api/v1/devices
Content-Type: application/json

{ "platform": "android", "appVersion": "0.1.0", "pushToken": "<token FCM, opcjonalnie>" }
```
```json
{ "deviceId": "01a101cd-6adb-7a59-8e56-3eeee445c5ea", "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

* `platform`: `ios` | `android` | `web` | `simulator`.
* Token zapisz w bezpiecznym magazynie (`flutter_secure_storage`). Przy 401 (`unauthorized` lub `token_expired`) usuń
  token i zarejestruj się ponownie, podając aktualny `pushToken`. Powstanie nowe urządzenie, a token FCM zostanie
  odpięty od starego, więc telefon nie dostanie dwóch pytań.
* Token FCM możesz podać od razu albo później przez `PUT /api/v1/devices/me/push-token` (`{ "pushToken": "..." }`, 204).
  Wysyłaj go **przy każdej rotacji** (`FirebaseMessaging.instance.onTokenRefresh`).
* `GET /api/v1/devices/me` zwraca profil (`DeviceProfile`): `deviceId`, `platform`, `hasPushToken`, `lastLocation` (GeoJSON Point lub null),
  `h3Cell`, `locationUpdatedAt`, `locationSource` (`home` | `gps` | `background` | null), `preferences.locationRefresh`.
* `PUT /api/v1/devices/me/preferences` `{ "locationRefresh": false }` (204) wyłącza przypomnienia o lokalizacji (patrz §10).

---

## 4. Lokalizacja

Backend przechowuje **jedną** aktualną pozycję urządzenia, bez historii. Pozycja służy do dwóch rzeczy:
wybrania, kogo zapytać w weryfikacji, i komu dostarczyć alert. Bez pozycji urządzenie nigdy nie dostanie pytania ani alertu.

```http
PUT /api/v1/devices/me/location
{ "lat": 52.4125, "lng": 16.9020, "accuracyMeters": 12.5, "source": "gps" }
```
```json
{ "h3Cell": "891e24aa5c7ffff" }
```

Kiedy wysyłać:
1. przy starcie aplikacji i powrocie na pierwszy plan,
2. po przemieszczeniu o więcej niż ~150 m (to rozmiar komórki mapy),
3. nie częściej niż raz na kilkanaście sekund (limit 30/min).

`source` mówi, skąd pochodzi pozycja: `home` (adres z onboardingu, same współrzędne), `gps` (domyślne, pierwszy plan),
`background` (tryb czuwania). Backend przechowuje nadal jedną pozycję (wygrywa ostatni `PUT`), ale zna jej źródło
i zwraca je w profilu, żeby aplikacja mogła pokazać „adres domowy” vs „ostatnia pozycja GPS”.

Lokalizacja w tle nie jest wymagana na MVP. Zgłoszenie (`POST /api/v1/reports`) także aktualizuje pozycję urządzenia
(jako `gps`).

---

## 5. Mapa

Jedno wywołanie zwraca wszystko, co ma być na mapie, jako GeoJSON `FeatureCollection`:

```http
GET /api/v1/map?bbox=16.80,52.35,17.05,52.48
```

`bbox` = `minLng,minLat,maxLng,maxLat` (aktualne okno mapy). Bez `bbox` zwracana jest cała Polska (unikaj).

Każdy `Feature` ma `properties.kind` ∈ `incident` | `shelter` | `alert`:

```json
{
  "type": "Feature",
  "id": "01a101cb-1fda-7c63-a233-e76b5a1e2355",
  "geometry": { "type": "MultiPolygon", "coordinates": [ ... ] },
  "properties": {
    "kind": "incident",
    "id": "01a101cb-1fda-7c63-a233-e76b5a1e2355",
    "type": "power_outage",
    "typeLabel": "Brak prądu",
    "status": "verifying",
    "statusLabel": "Trwa weryfikacja",
    "confidenceLevel": "likely",
    "confidenceLabel": "Prawdopodobne",
    "confidenceScore": 0.43,
    "startedAt": "2026-10-03T14:44:11+02:00",
    "lastActivityAt": "2026-10-03T14:46:02+02:00",
    "lastConfirmedAt": null,
    "community": { "reports": 5, "answers": 10, "agreementPct": 60 },
    "summary": null
  }
}
```

Zasady renderowania:

* Każdy `Feature` ma to samo UUID w `id` i w `properties.id` (dla wszystkich `kind`). Schemat: `MapFeature` z `properties`
  jako `oneOf` po `kind` (`IncidentFeatureProperties` | `ShelterFeatureProperties` | `AlertFeatureProperties`).
* **Incydent** ma geometrię `MultiPolygon` (zasięg) albo `Point` (gdy zasięg nie jest jeszcze wyznaczony). Dla punktu narysuj marker, dla poligonu wypełnienie z przezroczystością ~35 % i obrys. `statusLabel`: Wykryte / Trwa weryfikacja / Zasięg ustalony / Zakończone.
* Kolor incydentu zależy od `confidenceLevel` (tak samo jak w panelu operatora):

| `confidenceLevel` | Etykieta | Kolor |
|---|---|---|
| `unverified` | Niezweryfikowane | `#64748B` szary |
| `likely` | Prawdopodobne | `#F59E0B` bursztyn |
| `high` | Wysoka wiarygodność | `#F97316` pomarańcz |
| `confirmed` | Potwierdzone | `#EF4444` czerwień |

* `community.agreementPct` to zdanie z opisu produktu: „Problem zgłasza 86 % odpowiadających użytkowników”. `null`, gdy nikt jeszcze nie odpowiedział.
* **Schron** to `Point` z `properties.status` ∈ `open` | `closed` | `full` | `unknown` (etykiety w `statusLabel`), `name`, `address`, `capacity`, `lastConfirmedAt`, `confirmationCount`.
* **Alert** to poligon z `title`, `body`, `severity` ∈ `info` | `warning` | `danger`, `createdAt`, `expiresAt`, `active`, `incidentId` (ten sam model co w `GET /alerts`).

Biblioteka: `flutter_map` z warstwą `PolygonLayer`/`MarkerLayer` i kafelkami OSM albo MapLibre (`maplibre_gl`)
z kafelkami OpenFreeMap (`https://tiles.openfreemap.org/styles/liberty`, bez klucza). Do parsowania GeoJSON
wystarczy ręczne mapowanie `coordinates` na `LatLng(lat: c[1], lng: c[0])`.

Szczegóły pojedynczego incydentu: `GET /api/v1/incidents/{id}` (te same pola co w `properties` plus `area`).
Lista incydentów obejmujących moją pozycję: `GET /api/v1/incidents?lat=&lng=`.

Widok obywatela celowo **nie zawiera** surowych pozycji zgłoszeń ani szczegółów źródeł. To nie błąd API.

---

## 6. Zgłoszenie (DETECT)

Ekran: wybór typu → lokalizacja (domyślnie aktualna, z możliwością przesunięcia pinezki) → opcjonalny opis → wyślij.

Lista typów z etykietami (możesz ją pobrać lub wpisać na sztywno):

```http
GET /api/v1/reports/types
```
```json
[
  { "value": "power_outage",  "label": "Brak prądu" },
  { "value": "water_outage",  "label": "Brak wody" },
  { "value": "fuel_shortage", "label": "Brak paliwa" },
  { "value": "road_blocked",  "label": "Nieprzejezdna droga" },
  { "value": "shelter_issue", "label": "Problem ze schronem" },
  { "value": "other_threat",  "label": "Inne zagrożenie" }
]
```

```http
POST /api/v1/reports
{ "type": "power_outage", "lat": 52.4125, "lng": 16.9020, "description": "Cała ulica bez światła" }
```
```json
HTTP 202
{ "reportId": "01a101cd-6c33-7ee8-8982-bad3315cbdbe", "h3Cell": "891e24aa5c7ffff", "createdAt": "2026-10-03T14:46:42+02:00" }
```

* Odpowiedź 202 oznacza „przyjęto”; łączenie w incydent dzieje się w tle w ciągu około sekundy.
* `description` maks. 1000 znaków, opcjonalny.
* 429 po 10 zgłoszeniach w 10 minut: pokaż komunikat, nie ponawiaj automatycznie.
* `GET /api/v1/reports/{id}` zwraca, do jakiego incydentu trafiło zgłoszenie (`incident` może być `null` przez chwilę):

```json
{ "reportId": "...", "type": "power_outage", "createdAt": "...", "incident": { "id": "...", "status": "verifying", "confidenceLevel": "likely", "confidenceScore": 0.43 } }
```

Dobry UX: po wysłaniu pokaż „Dziękujemy, sprawdzamy to z innymi mieszkańcami” i po 2–3 s odśwież mapę.

---

## 7. Pytanie weryfikacyjne (VERIFY) — najważniejszy ekran

Backend sam decyduje, kogo i kiedy zapytać. Aplikacja dostaje pytanie dwiema drogami naraz:
pushem FCM **i** przez endpoint `pending`. Push może nie dojść albo dojść po czasie, dlatego zawsze
po powrocie na pierwszy plan odpytaj `pending`.

```http
GET /api/v1/verifications/pending
```
```json
[
  {
    "verificationId": "01a101cb-2f10-7a0c-9c1e-3d1b2a4f5e60",
    "incidentId": "01a101cb-1fda-7c63-a233-e76b5a1e2355",
    "type": "power_outage",
    "typeLabel": "Brak prądu",
    "question": "Czy w tej chwili masz dostęp do prądu?",
    "context": "W Twojej okolicy zgłoszono: brak prądu.",
    "options": ["yes", "no", "unknown"],
    "sentAt": "2026-10-03T14:44:12+02:00",
    "expiresAt": "2026-10-03T14:45:42+02:00",
    "answered": false
  }
]
```

Odpowiedź:

```http
POST /api/v1/verifications/{verificationId}/response
{ "answer": "no" }
```
```json
{ "verificationId": "...", "incidentId": "...", "thanks": "Dziękujemy. Twoja odpowiedź pomaga wyznaczyć zasięg problemu." }
```

Zasady:

* Wyświetl dosłownie `context` + `question` i trzy przyciski: **TAK** (`yes`), **NIE** (`no`), **NIE WIEM** (`unknown`).
  Wysyłaj to, co kliknął użytkownik. Backend sam wie, że „NIE” na pytanie „czy masz prąd?” oznacza problem.
* Pytanie żyje **90 sekund** (`expiresAt`). Pokaż odliczanie; po czasie schowaj ekran. Odpowiedź po terminie daje 410.
* 409 oznacza, że już odpowiedziano (np. z innego ekranu). Potraktuj jak sukces.
* Jedno urządzenie nie dostanie pytania częściej niż co 10 minut, więc nie trzeba throttlować po stronie aplikacji.
* Pojedyncze pytanie (np. po tapnięciu pusha): `GET /api/v1/verifications/{id}`.

Jeśli masz czas na animację, to jest moment w demo, który robi największe wrażenie: po odpowiedzi odśwież mapę
i pokaż, jak zmienia się `confidenceScore` incydentu.

---

## 8. Alerty (INFORM)

Operator wysyła komunikat do obszaru. Urządzenia, których ostatnia pozycja leży wewnątrz obszaru, dostają push
`data.type = "alert"`. Niezależnie od pusha:

```http
GET /api/v1/alerts?lat=52.4121&lng=16.9012
```
```json
[
  {
    "id": "01a101cf-70c5-7f4e-a654-360c80f1252f",
    "title": "Potwierdzono awarię prądu",
    "body": "Problem zgłasza większość użytkowników w Twojej okolicy.",
    "severity": "warning",
    "incidentId": "01a101cb-1fda-7c63-a233-e76b5a1e2355",
    "createdAt": "2026-10-03T14:48:10+02:00",
    "expiresAt": "2026-10-03T15:48:10+02:00",
    "active": true
  }
]
```

`GET /api/v1/alerts/{id}` dodatkowo zwraca `area` (poligon). Ekran alertu: tytuł, treść, kolor po `severity`
(`info` niebieski, `warning` bursztyn, `danger` czerwień), przycisk „pokaż na mapie”, przycisk „najbliższy schron”.

---

## 9. Schrony

```http
GET /api/v1/shelters?lat=52.4125&lng=16.9020      # 10 najbliższych, z distanceMeters
GET /api/v1/shelters?bbox=16.80,52.35,17.05,52.48 # w oknie mapy
GET /api/v1/shelters/{id}
POST /api/v1/shelters/{id}/status  { "status": "open", "comment": "Wejście od podwórza" }
```

Pole `distanceMeters` jest tylko w wariancie z `lat/lng`. Statusy: `open` (Otwarty), `closed` (Zamknięty),
`full` (Pełny), `unknown` (Brak danych). Po potwierdzeniu endpoint zwraca zaktualizowany schron.

---

## 10. Push (FCM)

Backend wysyła przez Firebase Cloud Messaging wiadomości z sekcją `notification` (tytuł, treść) i `data`:

| `data.type` | Pozostałe pola `data` | Co zrobić po tapnięciu |
|---|---|---|
| `verification` | `verificationId`, `incidentId`, `incidentType`, `expiresAt` (ISO 8601) | otwórz ekran pytania, pobierz `GET /api/v1/verifications/{verificationId}`; jeśli `expiresAt` minęło, od razu pokaż „pytanie wygasło” |
| `alert` | `alertId` | otwórz ekran alertu, pobierz `GET /api/v1/alerts/{alertId}` |
| `location_refresh` | - | po otwarciu pobierz GPS i wyślij `PUT /api/v1/devices/me/location` |

Pytania i alerty idą z wysokim priorytetem (Android `priority: high`, iOS `apns-push-type: alert`, `apns-priority: 10`,
`interruption-level: time-sensitive`, dźwięk domyślny). Time-sensitive wymaga capability *Time Sensitive Notifications*
w aplikacji iOS; bez niej push dochodzi jak zwykły alert.

`location_refresh` („Czy nadal jesteś w tej okolicy?”) ma priorytet normalny i idzie tylko, gdy `locationUpdatedAt` jest
starsze niż 24 h, maks. raz na dobę, nie między 21:00 a 8:00 (Europe/Warsaw) i tylko przy `preferences.locationRefresh = true`.
Urządzenia w trybie czuwania (`source = background`) odświeżają pozycję same, więc w praktyce go nie dostają.

Po stronie Fluttera: `firebase_messaging`, obsługa `onMessage` (pierwszy plan: pokaż pytanie od razu, bez
czekania na tapnięcie), `onMessageOpenedApp` i `getInitialMessage` (start z pusha), `onTokenRefresh` → `PUT push-token`.

Backend ma skonfigurowane konto serwisowe projektu Firebase **`tarcza-polska`** i wysyła prawdziwe pushe.
`google-services.json` / `GoogleService-Info.plist` do aplikacji pobierz z tego samego projektu w konsoli Firebase.
Test z backendu na konkretny telefon: backendowiec uruchamia `make push-test t=<Twój token FCM>`; token wypisz
w aplikacji przez `FirebaseMessaging.instance.getToken()`. Aplikacja i tak musi działać na samym pollingu
`pending` i `alerts`, bo push może nie dojść.

---

## 11. Rekomendowany cykl życia ekranu głównego

1. Start: jeśli brak tokena → `POST /devices`; w przeciwnym razie `GET /devices/me` (401 → ponowna rejestracja z aktualnym `pushToken`).
2. Pobierz pozycję z GPS → `PUT /devices/me/location`.
3. Równolegle: `GET /map?bbox=` (okno mapy), `GET /verifications/pending`, `GET /alerts?lat&lng`.
4. Jeśli `pending` niepuste → pokaż pytanie jako arkusz na mapie.
5. Odświeżaj mapę co 30 s, gdy ekran jest widoczny, oraz po każdej akcji użytkownika (zgłoszenie, odpowiedź).
6. Na `resume` powtórz kroki 2–4.

Czas rzeczywisty przez SSE (Mercure, `https://<host>/.well-known/mercure?topic=incidents`) istnieje, ale jest
przeznaczony dla panelu operatora. Na MVP mobilnym polling + push wystarczą; nie trać na to czasu.

---

## 12. Słowniki

**Typy zgłoszeń** (`type`): `power_outage`, `water_outage`, `fuel_shortage`, `road_blocked`, `shelter_issue`, `other_threat` (etykiety w sekcji 6).

**Status incydentu** (`status`): `detected` (wykryty), `verifying` (trwa dopytywanie), `active` (zasięg ustalony), `resolved` (zamknięty; nie pojawia się na mapie).

**Poziom pewności** (`confidenceLevel`): `unverified`, `likely`, `high`, `confirmed` (kolory w sekcji 5). `confidenceScore` to liczba 0–1 do pokazania jako procent.

**Odpowiedź weryfikacyjna** (`answer`): `yes`, `no`, `unknown`.

**Status schronu**: `open`, `closed`, `full`, `unknown`. **Ważność alertu** (`severity`): `info`, `warning`, `danger`.

---

## 13. Generowanie klienta Dart

Plik [`openapi.json`](openapi.json) w tym katalogu jest aktualnym zrzutem (w backendzie: `make openapi`).

```bash
# w repo Fluttera
dart pub global activate openapi_generator_cli
openapi-generator generate -i ../tarcza-polska-backend/docs/openapi.json -g dart-dio -o packages/tarcza_api
```

Alternatywa, która na hackathonie bywa szybsza: ręczne modele dla ~8 odpowiedzi opisanych wyżej i jeden
klient `Dio` z interceptorem dodającym `Authorization` i mapującym `error.code` na wyjątki.

Minimalny interceptor:

```dart
final dio = Dio(BaseOptions(baseUrl: const String.fromEnvironment('API_BASE_URL')));
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (o, h) async {
    final token = await storage.read(key: 'device_token');
    if (token != null) o.headers['Authorization'] = 'Bearer $token';
    h.next(o);
  },
  onError: (e, h) async {
    if (e.response?.statusCode == 401) {
      await storage.delete(key: 'device_token'); // następny start zarejestruje urządzenie na nowo
    }
    h.next(e);
  },
));
```

---

## 14. Praca z backendem podczas developmentu

W repo backendu:

```bash
make up                      # https://localhost (lub SERVER_NAME=":80" docker compose up -d dla http)
make seed                    # schrony w Poznaniu + konta operatorów
make simulate                # 700 wirtualnych urządzeń + awaria prądu na Jeżycach (52.4121, 16.9012)
make worker                  # logi: tu widać, kiedy idzie fala pytań
```

Jak dostać pytanie weryfikacyjne na własny telefon w ciągu minuty:

1. Zarejestruj urządzenie i ustaw pozycję w okolicy Jeżyc, np. `52.4125, 16.9020`.
2. Uruchom `make simulate` (albo wyślij 2 zgłoszenia `power_outage` z dwóch urządzeń w promieniu 1,5 km).
3. Po ~2 s rusza fala 0, po 90 s kolejna. Jeśli Twoje urządzenie jest w jednej z pytanych komórek i ma świeżą
   pozycję, pojawi się w `GET /verifications/pending`. Gdy nie trafisz w komórkę, ustaw pozycję bliżej centrum
   (środek incydentu widać w panelu operatora: `https://localhost/command`, login `operator@tarcza.local` / `tarcza-demo`).
4. Odpowiedz, odśwież mapę: zmieni się `confidenceScore`, a po kolejnej fali rozrośnie się poligon.

Alert testowy: w panelu operatora wejdź w incydent i użyj formularza „Wyślij komunikat do obszaru”.

Reset danych demo (w repo backendu):

```bash
make console c="dbal:run-sql 'TRUNCATE report, incident, verification_wave, verification_request, external_source, alert, simulation_scenario CASCADE'"
make console c="dbal:run-sql \"DELETE FROM device WHERE simulated\""
```

---

## 15. Czego backend (jeszcze) nie robi

* Brak kont użytkowników, logowania, profilu. Tożsamość = instalacja aplikacji.
* Brak historii lokalizacji; backend zna tylko ostatnią pozycję.
* Brak uploadu zdjęć (pole istnieje w modelu, endpointu nie ma). Nie buduj na to UI w MVP.
* Brak trybu offline po stronie serwera; cache schronów i ostatniego stanu mapy to zadanie aplikacji.
* Brak tłumaczeń: etykiety przychodzą po polsku, wartości enumów są stałe i po angielsku.
* Brak paginacji: listy są krótkie z założenia (bbox, najbliższe 10, pending).
* Brak geokodowania adresu po stronie backendu: adres z onboardingu zamieniasz na współrzędne systemowym geokoderem
  (`geocoding`), do API trafiają tylko `lat`/`lng`.

Jeśli czegoś brakuje w API, najkrótsza droga to zgłoszenie w repo backendu z przykładowym żądaniem i oczekiwaną
odpowiedzią; dodanie endpointu w istniejącym module to zwykle kilkanaście minut.
