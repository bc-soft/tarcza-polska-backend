<?php

declare(strict_types=1);

namespace App\Incident\Message;

/**
 * Domain event: reports, cells or area of an incident changed.
 * Consumers: Confidence (recalculate), Verification (plan waves), Command (publish to Mercure).
 */
final readonly class IncidentUpdated
{
    public function __construct(
        public string $incidentId,
        public string $reason,
    ) {
    }
}
