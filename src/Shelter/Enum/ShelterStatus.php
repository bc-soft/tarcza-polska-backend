<?php

declare(strict_types=1);

namespace App\Shelter\Enum;

enum ShelterStatus: string
{
    case Unknown = 'unknown';
    case Open = 'open';
    case Closed = 'closed';
    /** Post-MVP granularity: open but crowded. */
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Brak danych',
            self::Open => 'Otwarty',
            self::Closed => 'Zamknięty',
            self::Full => 'Pełny',
        };
    }
}
