<?php

declare(strict_types=1);

namespace App\Incident\Enum;

enum IncidentStatus: string
{
    /** Cluster detected, nobody asked yet. */
    case Detected = 'detected';
    /** Verification waves in progress, area boundary being established. */
    case Verifying = 'verifying';
    /** Boundary stabilised, incident is live. */
    case Active = 'active';
    /** Closed by an operator or by decay. */
    case Resolved = 'resolved';

    public function isOpen(): bool
    {
        return self::Resolved !== $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Wykryte',
            self::Verifying => 'Trwa weryfikacja',
            self::Active => 'Zasięg ustalony',
            self::Resolved => 'Zakończone',
        };
    }
}
