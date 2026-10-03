<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Shared\Geo\Point;

/** A district of the pilot city: fixtures spread incidents, devices and objects around its centre. */
final readonly class Zone
{
    /** @param list<string> $streets */
    public function __construct(
        public string $name,
        public Point $center,
        public array $streets,
    ) {
    }
}
