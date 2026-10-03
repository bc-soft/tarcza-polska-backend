<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Shared\Geo\Point;

/** Districts of Poznań with real streets, so fixtures read like data from the field and all stay inside the city. */
final class ZoneCatalog
{
    /** @var list<array{name: string, center: array{0: float, 1: float}, streets: list<string>}> */
    private const array ZONES = [
        ['name' => 'Jeżyce', 'center' => [52.4140, 16.8990], 'streets' => ['Dąbrowskiego', 'Kraszewskiego', 'Kościelna', 'Poznańska', 'Słowackiego']],
        ['name' => 'Stare Miasto', 'center' => [52.4085, 16.9340], 'streets' => ['Półwiejska', 'Święty Marcin', 'Garbary', 'Wielka', 'Szkolna']],
        ['name' => 'Wilda', 'center' => [52.3900, 16.9270], 'streets' => ['28 Czerwca 1956', 'Dolna Wilda', 'Hetmańska', 'Pamiątkowa', 'Wierzbięcice']],
        ['name' => 'Łazarz', 'center' => [52.3990, 16.8980], 'streets' => ['Głogowska', 'Matejki', 'Łukaszewicza', 'Niegolewskich', 'Kolejowa']],
        ['name' => 'Grunwald', 'center' => [52.3980, 16.8700], 'streets' => ['Grunwaldzka', 'Bukowska', 'Marcelińska', 'Jugosłowiańska', 'Promienista']],
        ['name' => 'Rataje', 'center' => [52.3900, 16.9650], 'streets' => ['Piłsudskiego', 'Chartowo', 'Żegrze', 'Bolesława Krzywoustego', 'Kórnicka']],
        ['name' => 'Piątkowo', 'center' => [52.4600, 16.9170], 'streets' => ['Umultowska', 'Szymanowskiego', 'Opieńskiego', 'Stróżyńskiego', 'Wojciechowskiego']],
        ['name' => 'Winogrady', 'center' => [52.4330, 16.9330], 'streets' => ['Słowiańska', 'Serbska', 'Murawa', 'Naramowicka', 'Wilczak']],
    ];

    public const int MAX = 8;

    /** @return list<Zone> */
    public function zones(int $limit = self::MAX): array
    {
        return array_map(
            static fn (array $z) => new Zone($z['name'], new Point($z['center'][0], $z['center'][1]), $z['streets']),
            \array_slice(self::ZONES, 0, max(1, min(self::MAX, $limit))),
        );
    }
}
