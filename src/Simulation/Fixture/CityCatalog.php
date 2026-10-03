<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Shared\Geo\Point;

/** Real Polish cities with a few districts and streets, so fixtures read like data from the field. */
final class CityCatalog
{
    /** @var list<array{name: string, center: array{0: float, 1: float}, districts: list<string>, streets: list<string>}> */
    private const array CITIES = [
        ['name' => 'Poznań', 'center' => [52.4064, 16.9252], 'districts' => ['Jeżyce', 'Wilda', 'Łazarz', 'Rataje', 'Piątkowo', 'Grunwald', 'Winogrady'], 'streets' => ['Dąbrowskiego', 'Głogowska', '28 Czerwca 1956', 'Hetmańska', 'Bukowska', 'Słowiańska', 'Umultowska']],
        ['name' => 'Warszawa', 'center' => [52.2297, 21.0122], 'districts' => ['Mokotów', 'Praga-Południe', 'Wola', 'Ursynów', 'Bemowo', 'Białołęka', 'Targówek'], 'streets' => ['Puławska', 'Grochowska', 'Górczewska', 'KEN', 'Powstańców Śląskich', 'Modlińska', 'Radzymińska']],
        ['name' => 'Kraków', 'center' => [50.0647, 19.9450], 'districts' => ['Podgórze', 'Nowa Huta', 'Krowodrza', 'Bronowice', 'Dębniki', 'Prądnik Biały'], 'streets' => ['Wielicka', 'Aleja Jana Pawła II', 'Królewska', 'Bronowicka', 'Kapelanka', 'Opolska']],
        ['name' => 'Gdańsk', 'center' => [54.3520, 18.6466], 'districts' => ['Wrzeszcz', 'Oliwa', 'Przymorze', 'Orunia', 'Chełm', 'Zaspa'], 'streets' => ['Grunwaldzka', 'Opata Rybińskiego', 'Obrońców Wybrzeża', 'Trakt św. Wojciecha', 'Cienista', 'Pilotów']],
        ['name' => 'Wrocław', 'center' => [51.1079, 17.0385], 'districts' => ['Krzyki', 'Fabryczna', 'Psie Pole', 'Śródmieście', 'Nadodrze', 'Gaj'], 'streets' => ['Powstańców Śląskich', 'Legnicka', 'Krzywoustego', 'Jedności Narodowej', 'Trzebnicka', 'Świeradowska']],
        ['name' => 'Łódź', 'center' => [51.7592, 19.4560], 'districts' => ['Bałuty', 'Widzew', 'Polesie', 'Górna', 'Śródmieście'], 'streets' => ['Zgierska', 'Rokicińska', 'Konstantynowska', 'Rzgowska', 'Piotrkowska']],
    ];

    /** @return list<City> */
    public function cities(int $limit = 6): array
    {
        return array_map(
            static fn (array $c) => new City($c['name'], new Point($c['center'][0], $c['center'][1]), $c['districts'], $c['streets']),
            \array_slice(self::CITIES, 0, max(1, $limit)),
        );
    }
}
