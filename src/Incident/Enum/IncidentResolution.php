<?php

declare(strict_types=1);

namespace App\Incident\Enum;

/**
 * Why an incident was closed. Drives reporter reputation: confirmed incidents reward reporters,
 * false alarms penalise them, expiry is neutral.
 */
enum IncidentResolution: string
{
    case Confirmed = 'confirmed';
    case FalseAlarm = 'false_alarm';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Potwierdzony i zakończony',
            self::FalseAlarm => 'Fałszywy alarm',
            self::Expired => 'Wygasł bez aktywności',
        };
    }
}
