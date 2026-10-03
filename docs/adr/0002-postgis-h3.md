# ADR 0002: PostGIS jako źródło prawdy, H3 do komórek, własne typy Doctrine

Status: zaakceptowane, 2026-10-03

## Kontekst

Potrzebujemy zapytań „kto jest w promieniu”, „kto jest w poligonie”, unii komórek w obszar oraz
sąsiedztwa komórek do rozszerzania granicy. PHP nie ma dojrzałego bindingu H3, a biblioteki
Doctrine do PostGIS nie nadążają za DBAL 4.

## Decyzja

* PostgreSQL 16 + PostGIS 3.5 + rozszerzenie `h3` / `h3_postgis` (pakiet `postgresql-16-h3` z PGDG).
* Cała matematyka H3 w SQL przez `App\Shared\Geo\H3` (DBAL), bez bindingu w PHP.
* Dwa własne typy Doctrine: `geo_point` (Point VO) i `geo_geometry` (GeoJSON array), konwersja
  przez `ST_GeomFromGeoJSON` / `ST_AsGeoJSON`. Oba deklarują identyczny SQL, żeby `migrations:diff`
  był czysty (DBAL 4 porównuje deklaracje kolumn).
* Zapytania przestrzenne jako natywne SQL w repozytoriach (zwracają id, potem hydratacja encji).

## Konsekwencje

* Zero zależności od bibliotek przestrzennych w PHP.
* Rozdzielczość H3 = 9 (~174 m) jest parametrem; zmiana po powstaniu danych wymaga reseedu.
* Plan B przy problemie z obrazem: geohash precyzji 7 w PHP, ten sam interfejs `H3`.
