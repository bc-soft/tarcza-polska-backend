<?php

declare(strict_types=1);

namespace App\Incident\Dto;

use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Enum\IncidentStatus;
use App\Reporting\Enum\ReportType;

/** Filters of the Command Center incident list. Unknown enum values are ignored (treated as "all"). */
final readonly class IncidentSearchCriteria
{
    public function __construct(
        public ?IncidentStatus $status = null,
        public ?ReportType $type = null,
        public ?ConfidenceLevel $level = null,
        public bool $includeResolved = false,
        public int $limit = 200,
    ) {
    }

    public static function fromStrings(?string $status, ?string $type, ?string $level, bool $includeResolved, int $limit = 200): self
    {
        $parsedStatus = null !== $status && '' !== $status ? IncidentStatus::tryFrom($status) : null;

        return new self(
            status: $parsedStatus,
            type: null !== $type && '' !== $type ? ReportType::tryFrom($type) : null,
            level: null !== $level && '' !== $level ? ConfidenceLevel::tryFrom($level) : null,
            includeResolved: $includeResolved || IncidentStatus::Resolved === $parsedStatus,
            limit: max(1, min(500, $limit)),
        );
    }

    public function isEmpty(): bool
    {
        return null === $this->status && null === $this->type && null === $this->level && !$this->includeResolved;
    }
}
