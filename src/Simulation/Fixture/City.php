<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Shared\Geo\Point;

final readonly class City
{
    /**
     * @param list<string> $districts
     * @param list<string> $streets
     */
    public function __construct(
        public string $name,
        public Point $center,
        public array $districts,
        public array $streets,
    ) {
    }
}
