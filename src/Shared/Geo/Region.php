<?php

declare(strict_types=1);

namespace App\Shared\Geo;

/**
 * The single area the deployment serves (pilot: Poznań). Configured in config/services.yaml (app.region.*).
 *
 * Everything that needs a default place uses it: map fallbacks when the client sends no bbox, the Command Center
 * map centre, import commands without --around / --bbox, and demo fixtures.
 */
final readonly class Region
{
    public Point $center;

    public function __construct(
        public string $name,
        float $centerLat,
        float $centerLng,
        public int $radiusKm,
    ) {
        $this->center = new Point($centerLat, $centerLng);
    }

    public function radiusMeters(): int
    {
        return $this->radiusKm * 1000;
    }

    public function boundingBox(): BoundingBox
    {
        return BoundingBox::around($this->center, $this->radiusMeters());
    }

    public function contains(Point $point): bool
    {
        return $this->center->distanceTo($point) <= $this->radiusMeters();
    }
}
