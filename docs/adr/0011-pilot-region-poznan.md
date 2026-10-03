# ADR 0011: Zakres pilotażu ograniczony do Poznania

Data: 2026-10-03 · Status: przyjęte

## Kontekst

Pierwsze wdrożenie Tarczy Polska obejmuje jedno miasto. Dotąd „domyślne miejsce” było rozsiane po kodzie:
mapa w panelu miała wpisane współrzędne, importy wymagały `--around`, fikstury rozkładały dane po sześciu
miastach, a API bez `bbox` zwracało całą Polskę. Zespół mobilny i operatorzy potrzebują jednego, spójnego obszaru.

## Decyzja

* Jeden obiekt `App\Shared\Geo\Region` (nazwa, środek, promień) konfigurowany parametrami `app.region.*`
  w `config/services.yaml`. Pilotaż: Poznań, środek `52.4064, 16.9252`, promień 12 km.
* Region jest jedynym źródłem „domyślnego miejsca”: `GET /api/v1/map`, `/shelters`, `/fuel-stations` bez `bbox`
  zwracają jego prostokąt; mapa Command Center startuje w jego środku (global Twig `region`);
  `tarcza:shelters:import` i `tarcza:fuel-stations:import` bez filtrów pobierają tylko jego obszar;
  `tarcza:fixtures:load` rozkłada dane po dzielnicach Poznania (`ZoneCatalog`), a nie po miastach.
* Zgłoszenia spoza regionu nie są odrzucane. Region to domyślny zakres danych, nie bramka walidacji.
  Jeśli zajdzie potrzeba, odrzucanie będzie osobną decyzją (kod 422 `outside_region`).

## Konsekwencje

* Przeniesienie pilotażu do innego miasta to zmiana czterech parametrów i ponowny import obiektów.
* Literały współrzędnych pozostają tylko tam, gdzie opisują konkretne miejsca (scenariusz demo na Jeżycach,
  schrony w `tarcza:seed`, katalog dzielnic fikstur).
* `BoundingBox::poland()` zostaje jako granica rozsądku dla importów, nie jako fallback API.
