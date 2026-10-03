<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use InvalidArgumentException;

/**
 * "minLng,minLat,maxLng,maxLat" query parameter as used by map clients.
 */
final readonly class BoundingBox
{
    public function __construct(
        public float $minLng,
        public float $minLat,
        public float $maxLng,
        public float $maxLat,
    ) {
    }

    public static function fromString(string $bbox): self
    {
        $parts = array_map('floatval', explode(',', $bbox));
        if (4 !== \count($parts)) {
            throw new InvalidArgumentException('bbox must be "minLng,minLat,maxLng,maxLat"');
        }

        return new self($parts[0], $parts[1], $parts[2], $parts[3]);
    }

    /** Square box of +/- $radiusMeters around a point (good enough for pre-filtering at city scale). */
    public static function around(Point $center, int $radiusMeters): self
    {
        $dLat = $radiusMeters / 111_320.0;
        $dLng = $radiusMeters / (111_320.0 * max(0.01, cos(deg2rad($center->lat))));

        return new self(
            max(-180.0, $center->lng - $dLng),
            max(-90.0, $center->lat - $dLat),
            min(180.0, $center->lng + $dLng),
            min(90.0, $center->lat + $dLat),
        );
    }

    /** Whole of Poland, used when the client sends no bbox. */
    public static function poland(): self
    {
        return new self(14.07, 49.0, 24.15, 54.85);
    }

    /** SQL fragment usable with ST_MakeEnvelope. */
    public function envelopeSql(string $alias = 'bbox'): string
    {
        return \sprintf('ST_MakeEnvelope(:%s_min_lng, :%s_min_lat, :%s_max_lng, :%s_max_lat, 4326)', $alias, $alias, $alias, $alias);
    }

    /** @return array<string, float> */
    public function params(string $alias = 'bbox'): array
    {
        return [
            $alias.'_min_lng' => $this->minLng,
            $alias.'_min_lat' => $this->minLat,
            $alias.'_max_lng' => $this->maxLng,
            $alias.'_max_lat' => $this->maxLat,
        ];
    }
}
