<?php

declare(strict_types=1);

namespace App\Shelter\Enum;

/**
 * How much room is left in an open shelter (post-MVP granularity: "dużo miejsc / mało miejsc / pełny").
 */
enum ShelterOccupancy: string
{
    case Unknown = 'unknown';
    case Plenty = 'plenty';
    case Limited = 'limited';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Brak danych o miejscach',
            self::Plenty => 'Dużo miejsc',
            self::Limited => 'Mało miejsc',
            self::Full => 'Pełny',
        };
    }
}
