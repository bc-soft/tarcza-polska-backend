<?php

declare(strict_types=1);

namespace App\Incident\Enum;

enum ConfidenceLevel: string
{
    case Unverified = 'unverified';
    case Likely = 'likely';
    case High = 'high';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Niezweryfikowane',
            self::Likely => 'Prawdopodobne',
            self::High => 'Wysoka wiarygodność',
            self::Confirmed => 'Potwierdzone',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Unverified => 0,
            self::Likely => 1,
            self::High => 2,
            self::Confirmed => 3,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
