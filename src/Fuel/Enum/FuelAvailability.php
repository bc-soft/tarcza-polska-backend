<?php

declare(strict_types=1);

namespace App\Fuel\Enum;

enum FuelAvailability: string
{
    case Unknown = 'unknown';
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Brak danych',
            self::Available => 'Dostępne',
            self::Unavailable => 'Brak',
        };
    }
}
