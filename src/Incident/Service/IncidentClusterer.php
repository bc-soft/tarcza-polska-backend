<?php

declare(strict_types=1);

namespace App\Incident\Service;

use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use App\Reporting\Entity\Report;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;

/**
 * Incident Engine (MVP): same type + same time window + same neighbourhood => same incident.
 */
final readonly class IncidentClusterer
{
    public function __construct(
        private IncidentRepository $incidents,
        private AreaCalculator $areaCalculator,
        private H3 $h3,
        private int $clusterRadiusMeters,
        private int $clusterWindowMinutes,
    ) {
    }

    /** @return array{incident: Incident, created: bool} */
    public function attach(Report $report): array
    {
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

        return ['incident' => $incident, 'created' => $created];
    }

    /** Centroid = mean of report locations; center cell follows it. */
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
