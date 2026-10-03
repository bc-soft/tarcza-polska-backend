<?php

declare(strict_types=1);

namespace App\Confidence\Message;

/** Domain event emitted after every recalculation; the Command module publishes it to Mercure. */
final readonly class IncidentConfidenceUpdated
{
    public function __construct(
        public string $incidentId,
        public string $previousLevel,
        public string $currentLevel,
        public float $score,
        public string $reason,
    ) {
    }

    public function levelChanged(): bool
    {
        return $this->previousLevel !== $this->currentLevel;
    }
}
