<?php

declare(strict_types=1);

namespace App\Confidence\Model;

/**
 * Everything the deterministic confidence model looks at. Built from an Incident by IncidentConfidenceUpdater,
 * but kept free of Doctrine so the calculator is trivially unit-testable.
 */
final readonly class ConfidenceInput
{
    public function __construct(
        /** Sum of report weights (reporter reputation), not just a count. */
        public float $weightedReports,
        public int $reportCount,
        /** Distinct H3 cells the reports came from (spatial independence). */
        public int $distinctReportCells,
        /** "Problem present" answers from verification. */
        public int $yes,
        /** "Problem absent" answers from verification. */
        public int $no,
        /** Minutes since the last report or answer. */
        public float $minutesSinceLastActivity,
        /** Best credibility (0..1) among external sources found; 0 when none. */
        public float $bestExternalCredibility,
        public int $externalSourceCount,
    ) {
    }
}
