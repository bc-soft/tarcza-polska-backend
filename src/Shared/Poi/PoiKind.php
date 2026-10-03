<?php

declare(strict_types=1);

namespace App\Shared\Poi;

/**
 * Kinds of points of interest that point-scoped reports refer to.
 */
enum PoiKind: string
{
    case FuelStation = 'fuel_station';
    case Shelter = 'shelter';

    public function label(): string
    {
        return match ($this) {
            self::FuelStation => 'Stacja paliw',
            self::Shelter => 'Schron',
        };
    }

    /** How far from the reporter a POI may be to be auto-selected when the app sends no poiId. */
    public function snapRadiusMeters(): int
    {
        return match ($this) {
            self::FuelStation => 750,
            self::Shelter => 500,
        };
    }

    /** Neighbouring POIs within this radius are asked about during point verification. */
    public function verificationRadiusMeters(): int
    {
        return match ($this) {
            self::FuelStation => 6000,
            self::Shelter => 3000,
        };
    }

    /** Devices this close to a POI count as "at" the POI. */
    public function presenceRadiusMeters(): int
    {
        return match ($this) {
            self::FuelStation => 400,
            self::Shelter => 300,
        };
    }
}
