<?php

declare(strict_types=1);

namespace App\Fuel\Model;

use App\Fuel\Enum\FuelType;
use App\Shared\Geo\Point;

/** One station as read from an external source (OSM), before it becomes an entity. */
final readonly class FuelStationCandidate
{
    /** @param list<FuelType> $fuelTypes */
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $brand,
        public ?string $address,
        public Point $location,
        public array $fuelTypes,
    ) {
    }
}
