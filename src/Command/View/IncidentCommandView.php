<?php

declare(strict_types=1);

namespace App\Command\View;

use App\Incident\Entity\Incident;
use App\Incident\Entity\IncidentCell;
use App\Incident\Service\IncidentTimeline;
use App\Incident\View\IncidentPublicView;
use App\Incident\View\IncidentTimelineView;
use App\Intelligence\Entity\ExternalSource;
use App\Reporting\Entity\Report;
use App\Reporting\Entity\ReportPhoto;
use App\Reporting\Repository\ReportPhotoRepository;
use App\Reporting\View\ReportPhotoView;
use App\Shared\Api\GeoJson;
use App\Shared\Geo\H3;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Operator view: everything the public view has plus raw reports, cells, sources and the confidence breakdown.
 * Access to this view is audit-logged.
 */
final readonly class IncidentCommandView
{
    public function __construct(
        private H3 $h3,
        private IncidentTimeline $timeline,
        private ReportPhotoRepository $photos,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(Incident $incident): array
    {
        $t = $incident->tallies();

        return IncidentPublicView::toArray($incident) + [
            'centroid' => $incident->getCentroid()->toGeoJson(),
            'centerCell' => $incident->getCenterCell(),
            'currentRing' => $incident->getCurrentRing(),
            'cells' => ['total' => $t['cells'], 'positive' => $t['positive'], 'negative' => $t['negative']],
            'answers' => ['yes' => $t['yes'], 'no' => $t['no'], 'unknown' => $t['unknown']],
            'researchedAt' => $incident->getResearchedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<ExternalSource> $sources
     * @param array<string, mixed> $verificationStats
     *
     * @return array<string, mixed>
     */
    public function detail(Incident $incident, array $sources, array $verificationStats): array
    {
        return $this->summary($incident) + [
            'confidenceBreakdown' => $incident->getConfidenceBreakdown(),
            'reports' => array_map(static fn (Report $r) => [
                'id' => $r->getId()->toRfc4122(),
                'location' => $r->getLocation()->toGeoJson(),
                'h3Cell' => $r->getH3Cell(),
                'description' => $r->getDescription(),
                'weight' => round($r->getWeight(), 2),
                'createdAt' => $r->getCreatedAt()->format(\DATE_ATOM),
                'simulated' => $r->getDevice()->isSimulated(),
                'reporterReputation' => round($r->getDevice()->getReputation(), 2),
            ], self::sortedReports($incident)),
            'cellsGeoJson' => $this->cellsCollection($incident),
            'sources' => array_map(static fn (ExternalSource $s) => $s->toArray(), $sources),
            'verification' => $verificationStats,
            'resolution' => $incident->getResolution()?->value,
            'resolutionLabel' => $incident->getResolution()?->label(),
            'timeline' => IncidentTimelineView::list($this->timeline->entries($incident)),
            'photos' => array_map(
                fn (ReportPhoto $p) => ReportPhotoView::command($p, $this->urls->generate('command_photo_file', ['id' => $p->getId()->toRfc4122()])),
                $this->photos->findByIncident($incident, includeUnsafe: true),
            ),
        ];
    }

    /** @return list<Report> oldest first */
    private static function sortedReports(Incident $incident): array
    {
        $reports = $incident->getReports()->toArray();
        usort($reports, static fn (Report $a, Report $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $reports;
    }

    /** @return array<string, mixed> FeatureCollection of hexagons with state + tallies */
    public function cellsCollection(Incident $incident): array
    {
        $features = [];
        /** @var IncidentCell $cell */
        foreach ($incident->getCells() as $cell) {
            $features[] = GeoJson::feature($this->h3->cellBoundary($cell->getH3Index()), [
                'h3' => $cell->getH3Index(),
                'state' => $cell->getState()->value,
                'ring' => $cell->getRing(),
                'reports' => $cell->getReportCount(),
                'yes' => $cell->getYesCount(),
                'no' => $cell->getNoCount(),
                'unknown' => $cell->getUnknownCount(),
                'asked' => $cell->getAskedCount(),
            ], $cell->getH3Index());
        }

        return GeoJson::collection($features);
    }
}
