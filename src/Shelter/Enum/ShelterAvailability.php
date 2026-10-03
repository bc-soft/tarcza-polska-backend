<?php

declare(strict_types=1);

namespace App\Shelter\Enum;

/** Opening regime of a shelter as published in the national register (dane.gov.pl, "Dostepnosc"). */
enum ShelterAvailability: string
{
    case Unknown = 'unknown';
    case Always = 'always';
    case OnDemand = 'on_demand';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Brak danych',
            self::Always => 'Całodobowo',
            self::OnDemand => 'Na żądanie',
            self::Scheduled => 'W określonych godzinach',
        };
    }

    public static function fromRegister(string $raw): self
    {
        $v = mb_strtolower(trim($raw));

        return match (true) {
            str_contains($v, 'całodob'), str_contains($v, 'calodob') => self::Always,
            str_contains($v, 'żądanie'), str_contains($v, 'zadanie') => self::OnDemand,
            str_contains($v, 'godzin') => self::Scheduled,
            default => self::Unknown,
        };
    }
}
