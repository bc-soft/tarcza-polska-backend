<?php

declare(strict_types=1);

namespace App\Guidance\View;

use App\Alerting\Entity\Alert;
use App\Alerting\View\AlertView;
use App\Guidance\Model\OfflineBundle;
use App\Incident\View\IncidentPublicView;
use App\Shelter\Entity\Shelter;
use App\Shelter\View\ShelterView;

final class OfflineBundleView
{
    /** @return array<string, mixed> */
    public static function toArray(OfflineBundle $bundle): array
    {
        $center = $bundle->center;

        return [
            'generatedAt' => $bundle->generatedAt->format(\DATE_ATOM),
            'validUntil' => $bundle->validUntil->format(\DATE_ATOM),
            'center' => $center->toGeoJson(),
            'radiusMeters' => $bundle->radiusMeters,
            'shelters' => array_map(
                static fn (Shelter $s) => ShelterView::toArray($s, $s->getLocation()->distanceTo($center)),
                $bundle->shelters,
            ),
            'alerts' => array_map(static fn (Alert $a) => AlertView::toArray($a), $bundle->alerts),
            'incidents' => array_map(IncidentPublicView::toArray(...), $bundle->incidents),
            'procedures' => ProcedureView::list($bundle->procedures),
        ];
    }
}
