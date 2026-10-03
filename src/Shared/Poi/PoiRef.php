<?php

declare(strict_types=1);

namespace App\Shared\Poi;

use App\Shared\Geo\Point;
use Symfony\Component\Uid\Uuid;

/** Module-agnostic handle to a point of interest (a fuel station, a shelter). */
final readonly class PoiRef
{
    public function __construct(
        public PoiKind $kind,
        public Uuid $id,
        public string $name,
        public Point $location,
    ) {
    }

    /** @return array{kind: string, id: string, name: string, location: array{type: string, coordinates: array{0: float, 1: float}}} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'id' => $this->id->toRfc4122(),
            'name' => $this->name,
            'location' => $this->location->toGeoJson(),
        ];
    }
}
