<?php

declare(strict_types=1);

namespace App\Incident\View;

use App\Incident\Entity\Incident;
use App\Shared\Api\GeoJson;

/**
 * What a citizen is allowed to see: aggregated area + confidence, never raw report positions.
 */
final class IncidentPublicView
{
    /** @return array<string, mixed> */
    public static function toArray(Incident $incident): array
    {
        $t = $incident->tallies();
        $answered = $t['yes'] + $t['no'];

        return [
            'id' => $incident->getId()->toRfc4122(),
            'type' => $incident->getType()->value,
            'typeLabel' => $incident->getType()->label(),
            'status' => $incident->getStatus()->value,
            'statusLabel' => $incident->getStatus()->label(),
            'confidenceLevel' => $incident->getConfidenceLevel()->value,
            'confidenceLabel' => $incident->getConfidenceLevel()->label(),
            'confidenceScore' => round($incident->getConfidenceScore(), 2),
            'startedAt' => $incident->getStartedAt()->format(\DATE_ATOM),
            'lastActivityAt' => $incident->getLastActivityAt()->format(\DATE_ATOM),
            'lastConfirmedAt' => $incident->getLastConfirmedAt()?->format(\DATE_ATOM),
            'community' => [
                'reports' => $t['reports'],
                'answers' => $answered,
                // "86% of responding users report the problem"
                'agreementPct' => $answered > 0 ? (int) round(100 * $t['yes'] / $answered) : null,
            ],
            'summary' => $incident->getAiSummary(),
            'area' => $incident->getArea(),
        ];
    }

    /** @return array<string, mixed> GeoJSON Feature (area polygon; centroid when the area is still empty) */
    public static function toFeature(Incident $incident): array
    {
        $props = self::toArray($incident);
        unset($props['area']);

        return GeoJson::feature(
            $incident->getArea() ?? $incident->getCentroid()->toGeoJson(),
            ['kind' => 'incident'] + $props,
            $incident->getId()->toRfc4122(),
        );
    }
}
