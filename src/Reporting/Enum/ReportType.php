<?php

declare(strict_types=1);

namespace App\Reporting\Enum;

/**
 * What a citizen can report. Each type carries its verification question (asked to nearby users).
 */
enum ReportType: string
{
    case PowerOutage = 'power_outage';
    case WaterOutage = 'water_outage';
    case FuelShortage = 'fuel_shortage';
    case RoadBlocked = 'road_blocked';
    case ShelterIssue = 'shelter_issue';
    case OtherThreat = 'other_threat';

    public function label(): string
    {
        return match ($this) {
            self::PowerOutage => 'Brak prądu',
            self::WaterOutage => 'Brak wody',
            self::FuelShortage => 'Brak paliwa',
            self::RoadBlocked => 'Nieprzejezdna droga',
            self::ShelterIssue => 'Problem ze schronem',
            self::OtherThreat => 'Inne zagrożenie',
        };
    }

    /** Question pushed to users around a suspected incident of this type. */
    public function verificationQuestion(): string
    {
        return match ($this) {
            self::PowerOutage => 'Czy w tej chwili masz dostęp do prądu?',
            self::WaterOutage => 'Czy w tej chwili masz bieżącą wodę?',
            self::FuelShortage => 'Czy na stacji w Twojej okolicy jest dostępne paliwo?',
            self::RoadBlocked => 'Czy droga w Twojej okolicy jest przejezdna?',
            self::ShelterIssue => 'Czy schron w Twojej okolicy jest dostępny?',
            self::OtherThreat => 'Czy w Twojej okolicy występuje zgłoszone zagrożenie?',
        };
    }

    /**
     * For most types the question is phrased positively ("do you HAVE power?"), so a YES answer
     * means the problem is ABSENT. This flag tells the engine how to interpret answers.
     */
    public function yesMeansProblemPresent(): bool
    {
        return self::OtherThreat === $this;
    }

    /** Typical spatial footprint used for clustering radius scaling (meters). */
    public function typicalRadiusMeters(): int
    {
        return match ($this) {
            self::PowerOutage, self::WaterOutage => 1500,
            self::FuelShortage => 3000,
            self::RoadBlocked => 800,
            self::ShelterIssue => 300,
            self::OtherThreat => 1000,
        };
    }
}
