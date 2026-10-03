<?php

declare(strict_types=1);

namespace App\Incident\Model;

use App\Incident\Enum\IncidentEventType;
use DateTimeImmutable;

final readonly class TimelineEntry
{
    /** @param array<string, scalar|null> $details */
    public function __construct(
        public IncidentEventType $type,
        public DateTimeImmutable $at,
        public array $details,
    ) {
    }

    public function label(): string
    {
        return $this->type->label();
    }
}
