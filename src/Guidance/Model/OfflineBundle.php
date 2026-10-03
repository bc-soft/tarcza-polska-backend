<?php

declare(strict_types=1);

namespace App\Guidance\Model;

use App\Alerting\Entity\Alert;
use App\Incident\Entity\Incident;
use App\Shared\Geo\Point;
use App\Shelter\Entity\Shelter;
use DateTimeImmutable;

/**
 * Everything the app should cache for degraded / offline mode, around one position.
 */
final readonly class OfflineBundle
{
    /**
     * @param list<Shelter>   $shelters
     * @param list<Alert>     $alerts
     * @param list<Incident>  $incidents
     * @param list<Procedure> $procedures
     */
    public function __construct(
        public Point $center,
        public int $radiusMeters,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $validUntil,
        public array $shelters,
        public array $alerts,
        public array $incidents,
        public array $procedures,
    ) {
    }
}
