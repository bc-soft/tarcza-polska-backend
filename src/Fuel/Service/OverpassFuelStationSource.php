<?php

declare(strict_types=1);

namespace App\Fuel\Service;

use App\Fuel\Enum\FuelType;
use App\Fuel\Model\FuelStationCandidate;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fuel stations from OpenStreetMap through the Overpass API (amenity=fuel, nodes and ways with their centre).
 * The parser is separate from the HTTP call so it can be unit-tested with a fixture.
 */
final readonly class OverpassFuelStationSource
{
    public const string SOURCE = 'osm';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $overpassUrl,
    ) {
    }

    /** @return list<FuelStationCandidate> */
    public function fetchAround(Point $center, int $radiusMeters): array
    {
        $query = \sprintf(
            '[out:json][timeout:60];(node["amenity"="fuel"](around:%1$d,%2$F,%3$F);way["amenity"="fuel"](around:%1$d,%2$F,%3$F););out center tags;',
            $radiusMeters,
            $center->lat,
            $center->lng,
        );

        return $this->run($query);
    }

    /** @return list<FuelStationCandidate> */
    public function fetchInBoundingBox(BoundingBox $bbox): array
    {
        $box = \sprintf('%F,%F,%F,%F', $bbox->minLat, $bbox->minLng, $bbox->maxLat, $bbox->maxLng);
        $query = \sprintf('[out:json][timeout:90];(node["amenity"="fuel"](%1$s);way["amenity"="fuel"](%1$s););out center tags;', $box);

        return $this->run($query);
    }

    /** @return list<FuelStationCandidate> */
    private function run(string $query): array
    {
        $json = $this->httpClient->request('POST', $this->overpassUrl, [
            'body' => ['data' => $query],
            'headers' => ['User-Agent' => 'TarczaPolska/0.1 (+fuel-stations-import)'],
            'timeout' => 120,
        ])->getContent();

        return self::parse($json);
    }

    /** @return list<FuelStationCandidate> */
    public static function parse(string $json): array
    {
        /** @var array{elements?: list<array<string, mixed>>} $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($data['elements'] ?? [] as $el) {
            $tags = \is_array($el['tags'] ?? null) ? $el['tags'] : [];
            $lat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $lng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $brand = self::str($tags['brand'] ?? null) ?? self::str($tags['operator'] ?? null);
            $name = self::str($tags['name'] ?? null) ?? $brand ?? 'Stacja paliw';
            $address = self::address($tags);

            $fuels = [];
            foreach (FuelType::cases() as $type) {
                if ('yes' === ($tags[$type->osmTag()] ?? null)) {
                    $fuels[] = $type;
                }
            }

            $out[] = new FuelStationCandidate(
                externalId: \sprintf('%s/%s', (string) ($el['type'] ?? 'node'), (string) ($el['id'] ?? '')),
                name: $name,
                brand: $brand,
                address: $address,
                location: new Point((float) $lat, (float) $lng),
                fuelTypes: $fuels,
            );
        }

        return $out;
    }

    /** @param array<string, mixed> $tags */
    private static function address(array $tags): ?string
    {
        $street = self::str($tags['addr:street'] ?? null);
        $number = self::str($tags['addr:housenumber'] ?? null);
        $city = self::str($tags['addr:city'] ?? null);
        $line = trim(implode(' ', array_filter([$street, $number])));
        $parts = array_filter([$line, $city]);

        return [] === $parts ? null : implode(', ', $parts);
    }

    private static function str(mixed $v): ?string
    {
        return \is_string($v) && '' !== trim($v) ? trim($v) : null;
    }
}
