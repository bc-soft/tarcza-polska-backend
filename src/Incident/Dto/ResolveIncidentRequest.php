<?php

declare(strict_types=1);

namespace App\Incident\Dto;

use App\Incident\Enum\IncidentResolution;
use Symfony\Component\Validator\Constraints as Assert;

/** Operator verdict when closing an incident; defaults to "confirmed" when the body is omitted. */
final readonly class ResolveIncidentRequest
{
    public function __construct(
        public IncidentResolution $resolution = IncidentResolution::Confirmed,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {
    }
}
