<?php

declare(strict_types=1);

namespace App\Incident\Service;

use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Repository\IncidentRepository;
use App\Reporting\Entity\Report;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use App\Shared\Poi\PoiRef;
use App\Shared\Poi\PoiRegistry;

/**
 * Incident Engine (MVP+).
 *
 *  Area reports : same type + same time window + same neighbourhood => same incident; the centroid follows
 *                 the reports and the H3 cells build the area.
 *  Point reports: same type + same object => same incident; the incident sits on the object, has exactly
 *                 one cell (the object's) for crowd tallies and never gets an area.
 */
final readonly class IncidentClusterer
{
    public function __construct(
        private IncidentRepository $incidents,
        private AreaCalculator $areaCalculator,
        private IncidentTimeline $timeline,
        private PoiRegistry $pois,
        private H3 $h3,
        private int $clusterRadiusMeters,
        private int $clusterWindowMinutes,
    ) {
    }

    /** @return array{incident: Incident, created: bool} */
    public function attach(Report $report): array
    {
        $poiId = $report->getPoiId();
        $poiKind = $report->getPoiKind();

        if (null !== $poiId && null !== $poiKind) {
            return $this->attachToPoi($report, new PoiRef($poiKind, $poiId, (string) $report->getPoiName(), $report->getLocation()));
        }

        $radius = max($this->clusterRadiusMeters, $report->getType()->typicalRadiusMeters());
        $incident = $this->incidents->findClusterCandidate($report->getType(), $report->getLocation(), $radius, $this->clusterWindowMinutes);
        $created = false;

        if (null === $incident) {
            $incident = new Incident($report->getType(), $report->getLocation(), $report->getH3Cell());
            $this->incidents->save($incident);
            $created = true;
        }

        $incident->addReport($report);

        $ring = $created ? 0 : $this->h3->distance($incident->getCenterCell(), $report->getH3Cell());
        $incident->cell($report->getH3Cell(), $ring)->addReport();

        $this->recenter($incident);
        $this->areaCalculator->recompute($incident);

        $this->timeline->record(
            $incident,
            $created ? IncidentEventType::Created : IncidentEventType::ReportAttached,
            ['reports' => $incident->getReports()->count(), 'cell' => $report->getH3Cell(), 'ring' => $ring],
        );

        return ['incident' => $incident, 'created' => $created];
    }

    /** @return array{incident: Incident, created: bool} */
    private function attachToPoi(Report $report, PoiRef $poi): array
    {
        $incident = $this->incidents->findOpenForPoi($report->getType(), $poi->id);
        $created = false;

        if (null === $incident) {
            // The object's own position is the incident position, not where the reporter stood.
            $poiLocation = $this->poiLocation($report, $poi);
            $poiCell = $this->h3->cellFor($poiLocation);
            $incident = new Incident($report->getType(), $poiLocation, $poiCell);
            $incident->bindToPoi(new PoiRef($poi->kind, $poi->id, $poi->name, $poiLocation), $poiCell);
            $this->incidents->save($incident);
            $created = true;
        }

        $incident->addReport($report);
        $incident->mergeFuelTypes($report->getFuelTypes());
        $incident->cell($incident->getCenterCell(), 0)->addReport();

        $this->timeline->record(
            $incident,
            $created ? IncidentEventType::Created : IncidentEventType::ReportAttached,
            [
                'reports' => $incident->getReports()->count(),
                'poiKind' => $poi->kind->value,
                'poiId' => $poi->id->toRfc4122(),
                'poiName' => $poi->name,
                'fuelTypes' => implode(',', array_map(static fn ($t) => $t->value, $incident->getFuelTypes())),
            ],
        );

        return ['incident' => $incident, 'created' => $created];
    }

    /**
     * Reports only carry the object's id and name; the authoritative position comes from the POI module.
     * ReportSubmitter snapped the report to an object within a few hundred metres, so the reporter's
     * position is an acceptable fallback should the object have been deleted meanwhile.
     */
    private function poiLocation(Report $report, PoiRef $poi): Point
    {
        $found = $this->pois->locator($poi->kind)->find($poi->id);

        return null === $found ? $report->getLocation() : $found->location;
    }

    /** Centroid = mean of report locations; center cell follows it (area incidents only). */
    private function recenter(Incident $incident): void
    {
        $lat = 0.0;
        $lng = 0.0;
        $n = 0;
        foreach ($incident->getReports() as $r) {
            $lat += $r->getLocation()->lat;
            $lng += $r->getLocation()->lng;
            ++$n;
        }
        if (0 === $n) {
            return;
        }
        $centroid = new Point($lat / $n, $lng / $n);
        $incident->relocate($centroid, $this->h3->cellFor($centroid));
    }
}
