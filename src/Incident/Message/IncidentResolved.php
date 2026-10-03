<?php

declare(strict_types=1);

namespace App\Incident\Message;

/**
 * Domain event: an incident was closed with a verdict. Consumers: Identity (reporter reputation).
 */
final readonly class IncidentResolved
{
    public function __construct(
        public string $incidentId,
        /** confirmed | false_alarm | expired */
        public string $resolution,
        public ?string $operatorEmail = null,
    ) {
    }
}
