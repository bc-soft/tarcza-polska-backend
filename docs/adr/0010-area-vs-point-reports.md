# ADR 0010: Zgłoszenia obszarowe i punktowe - stacje paliw i schrony jako obiekty, nie jako plamy

Status: zaakceptowane, 2026-10-03

## Kontekst

Pierwotny model traktował każdy typ zgłoszenia tak samo: klastrowanie po odległości, dopytywanie komórka po
komórce i wielokąt na mapie. Dla braku prądu czy wody to ma sens, bo problem ma zasięg. Dla braku paliwa albo
zamkniętego schronu nie: problem dotyczy konkretnego obiektu, a sensowne pytanie weryfikacyjne brzmi
„czy na stacji X jest paliwo?”, zadane ludziom stojącym przy tej i sąsiednich stacjach. Wynik powinien być
pinezką ze statusem, nie plamą. Do tego brak paliwa trzeba rozróżniać po rodzaju (PB95, PB98, ON, LPG).

## Decyzja

* `ReportType::scope()`: `area` (prąd, woda, drogi, inne zagrożenia) albo `point` (paliwo, schron);
  `ReportType::poiKind()` wskazuje rodzaj obiektu.
* Wspólna abstrakcja obiektów w `Shared\Poi`: `PoiKind`, `PoiRef`, `PoiLocatorInterface` (znajdź / najbliższy /
  sąsiedzi) i `PoiStatusUpdaterInterface` (zastosuj odpowiedź tłumu do statusu obiektu). Implementacje żyją
  w modułach `Fuel` i `Shelter`, rejestr wybiera je po rodzaju. Reporting i Verification nie znają stacji ani schronów.
* Zgłoszenie punktowe musi być związane z obiektem: `poiId` z aplikacji albo najbliższy obiekt w promieniu
  przyciągania (750 m dla stacji, 500 m dla schronu); brak obiektu to 422 `poi_required`. Brak paliwa wymaga
  `fuelTypes`.
* Incydent punktowy siedzi na obiekcie (centroid = pozycja obiektu), ma jedną komórkę do liczenia głosów,
  nigdy nie dostaje obszaru. Jeden otwarty incydent na obiekt i typ; kolejne zgłoszenia dokładają rodzaje paliwa.
* Weryfikacja punktowa: fala 0 pyta ludzi przy zgłoszonym obiekcie, kolejne fale po trzech najbliższych obiektach
  tego samego rodzaju w promieniu (6 km stacje, 3 km schrony). Pytanie nazywa obiekt i paliwo. Każda odpowiedź
  aktualizuje status obiektu, o który pytano (dostępność paliwa per rodzaj, dostępność schronu); do pewności
  incydentu liczą się tylko odpowiedzi o zgłoszonym obiekcie.
* Źródła obiektów: stacje z OpenStreetMap przez Overpass (`tarcza:fuel-stations:import`), schrony z rejestru
  „Punkty schronienia w Polsce” na dane.gov.pl (`tarcza:shelters:import`, 86 tys. wierszy, filtry po województwie
  i obszarze, upsert po identyfikatorze publicznym, potwierdzenia obywateli nigdy nie są nadpisywane).
* Mapa: incydenty punktowe jako `Point`, stacje jako nowa warstwa `fuel_station` z dostępnością per paliwo,
  schrony jak dotąd z zapełnieniem i trybem otwarcia z rejestru.

## Konsekwencje

* Model pewności nie zmienia się: incydent punktowy ma te same składowe (zgłoszenia, tłum, źródła, świeżość).
* Aplikacja przy typach punktowych pokazuje wybór obiektu (lub przyjmuje najbliższy) i wybór paliwa; kontrakt
  w `docs/api.md` i przewodniku Fluttera.
* Overpass bywa niedostępny z sieci firmowych; import ma konfigurowalny adres (`OVERPASS_URL`) i działa też
  po prostu z innego mirrora. Bez stacji w okolicy zgłoszenie paliwa nie przejdzie, więc import jest krokiem
  wdrożenia, a fikstury tworzą stacje syntetyczne.
