<?php

declare(strict_types=1);

namespace App\Incident\Message;

/** Command: an operator closed the incident with a verdict (confirmed | false_alarm). */
final readonly class ResolveIncident
{
    public function __construct(
        public string $incidentId,
        public ?string $operatorEmail = null,
        public string $resolution = 'confirmed',
        public ?string $note = null,
    ) {
    }
}
