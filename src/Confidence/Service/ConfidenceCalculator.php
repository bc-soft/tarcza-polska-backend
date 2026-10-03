<?php

declare(strict_types=1);

namespace App\Confidence\Service;

use App\Confidence\Model\ConfidenceInput;
use App\Confidence\Model\ConfidenceResult;
use App\Incident\Enum\ConfidenceLevel;
use DateTimeImmutable;

/**
 * Deterministic, explainable confidence model. No LLM involved.
 *
 *   score = freshness * (reports + spread + crowd) + external
 *
 *   reports  : 0..0.40  saturating in the weighted number of reports (3 reports ~ 63 % of the max)
 *   spread   : 0..0.05  bonus for reports coming from distinct cells (independence)
 *   crowd    : -0.40..0.40  Bayesian "problem present" share from YES/NO answers, scaled by sample size
 *   freshness: exp(-age / 180 min) applied to community evidence
 *   external : 0..0.25  best credibility of external/official sources
 *
 * Levels: < 0.35 UNVERIFIED, < 0.60 LIKELY, < 0.80 HIGH, >= 0.80 CONFIRMED.
 * CONFIRMED additionally requires an external source with credibility >= 0.5 (community alone caps at HIGH).
 */
final class ConfidenceCalculator
{
    public const float W_REPORTS = 0.40;
    public const float W_SPREAD = 0.05;
    public const float W_CROWD = 0.40;
    public const float W_EXTERNAL = 0.25;

    public const float REPORT_SATURATION = 3.0;
    public const float FRESHNESS_HALF_LIFE_MIN = 180.0;
    public const float CROWD_PRIOR_SAMPLES = 5.0;

    public const float T_LIKELY = 0.35;
    public const float T_HIGH = 0.60;
    public const float T_CONFIRMED = 0.80;
    public const float MIN_EXTERNAL_FOR_CONFIRMED = 0.5;

    public function calculate(ConfidenceInput $in): ConfidenceResult
    {
        $reports = self::W_REPORTS * (1.0 - exp(-max(0.0, $in->weightedReports) / self::REPORT_SATURATION));

        $spread = $in->reportCount > 1
            ? self::W_SPREAD * min(1.0, ($in->distinctReportCells - 1) / 4.0)
            : 0.0;

        $n = $in->yes + $in->no;
        $crowd = 0.0;
        $posterior = null;
        if ($n > 0) {
            // Beta(1,1) posterior mean of "problem present", shrunk towards 0.5 for small samples
            $posterior = ($in->yes + 1) / ($n + 2);
            $sampleFactor = $n / ($n + self::CROWD_PRIOR_SAMPLES);
            $crowd = self::W_CROWD * (($posterior - 0.5) / 0.5) * $sampleFactor;
        }

        $freshness = exp(-max(0.0, $in->minutesSinceLastActivity) / self::FRESHNESS_HALF_LIFE_MIN);

        $external = self::W_EXTERNAL * max(0.0, min(1.0, $in->bestExternalCredibility));

        $community = $freshness * ($reports + $spread + $crowd);
        $score = max(0.0, min(1.0, $community + $external));

        $level = match (true) {
            $score >= self::T_CONFIRMED && $in->bestExternalCredibility >= self::MIN_EXTERNAL_FOR_CONFIRMED => ConfidenceLevel::Confirmed,
            $score >= self::T_HIGH => ConfidenceLevel::High,
            $score >= self::T_LIKELY => ConfidenceLevel::Likely,
            default => ConfidenceLevel::Unverified,
        };

        return new ConfidenceResult($score, $level, [
            'score' => round($score, 4),
            'level' => $level->value,
            'components' => [
                'reports' => round($reports, 4),
                'spread' => round($spread, 4),
                'crowd' => round($crowd, 4),
                'freshness' => round($freshness, 4),
                'external' => round($external, 4),
            ],
            'inputs' => [
                'weightedReports' => round($in->weightedReports, 2),
                'reportCount' => $in->reportCount,
                'distinctReportCells' => $in->distinctReportCells,
                'yes' => $in->yes,
                'no' => $in->no,
                'posteriorProblemPresent' => null === $posterior ? null : round($posterior, 3),
                'minutesSinceLastActivity' => round($in->minutesSinceLastActivity, 1),
                'bestExternalCredibility' => round($in->bestExternalCredibility, 2),
                'externalSourceCount' => $in->externalSourceCount,
            ],
            'computedAt' => new DateTimeImmutable()->format(\DATE_ATOM),
        ]);
    }
}
