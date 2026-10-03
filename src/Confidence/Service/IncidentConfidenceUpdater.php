<?php

declare(strict_types=1);

namespace App\Confidence\Service;

use App\Confidence\Model\ConfidenceInput;
use App\Incident\Entity\Incident;
use App\Incident\Enum\ConfidenceLevel;
use App\Intelligence\Repository\ExternalSourceRepository;

final readonly class IncidentConfidenceUpdater
{
    public function __construct(
        private ConfidenceCalculator $calculator,
        private ExternalSourceRepository $sources,
    ) {
    }

    /** @return array{previous: ConfidenceLevel, current: ConfidenceLevel, score: float} */
    public function update(Incident $incident): array
    {
        $previous = $incident->getConfidenceLevel();

        $weighted = 0.0;
        $cells = [];
        foreach ($incident->getReports() as $report) {
            $weighted += $report->getWeight();
            $cells[$report->getH3Cell()] = true;
        }
        $t = $incident->tallies();
        $external = $this->sources->bestCredibility($incident);

        $input = new ConfidenceInput(
            weightedReports: $weighted,
            reportCount: $incident->getReports()->count(),
            distinctReportCells: \count($cells),
            yes: $t['yes'],
            no: $t['no'],
            minutesSinceLastActivity: max(0, (time() - $incident->getLastActivityAt()->getTimestamp()) / 60),
            bestExternalCredibility: $external['credibility'],
            externalSourceCount: $external['count'],
        );

        $result = $this->calculator->calculate($input);
        $incident->updateConfidence($result->score, $result->level, $result->breakdown);

        return ['previous' => $previous, 'current' => $result->level, 'score' => $result->score];
    }
}
