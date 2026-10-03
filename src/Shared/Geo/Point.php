<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use InvalidArgumentException;
use JsonSerializable;

/**
 * WGS84 point. Immutable value object shared by every module.
 */
final readonly class Point implements JsonSerializable
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException(\sprintf('Invalid coordinates %f,%f', $lat, $lng));
        }
    }

    /** @param array{type?: string, coordinates: array{0: float|int, 1: float|int}} $geoJson */
    public static function fromGeoJson(array $geoJson): self
    {
        return new self((float) $geoJson['coordinates'][1], (float) $geoJson['coordinates'][0]);
    }

    /** @return array{type: 'Point', coordinates: array{0: float, 1: float}} */
    public function toGeoJson(): array
    {
        return ['type' => 'Point', 'coordinates' => [$this->lng, $this->lat]];
    }

    /** Haversine distance in meters. */
    public function distanceTo(self $other): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /** @return array{type: 'Point', coordinates: array{0: float, 1: float}} */
    public function jsonSerialize(): array
    {
        return $this->toGeoJson();
    }
}
