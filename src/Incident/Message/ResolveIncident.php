<?php

declare(strict_types=1);

namespace App\Incident\Message;

/** Command: an operator closed the incident. */
final readonly class ResolveIncident
{
    public function __construct(public string $incidentId, public ?string $operatorEmail = null)
    {
    }
}
