<?php

declare(strict_types=1);

namespace App\Tests\Unit\Confidence;

use App\Confidence\Model\ConfidenceInput;
use App\Confidence\Service\ConfidenceCalculator;
use App\Incident\Enum\ConfidenceLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfidenceCalculatorTest extends TestCase
{
    private ConfidenceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ConfidenceCalculator();
    }

    #[Test]
    public function singleReportIsUnverified(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 1.0, reportCount: 1, cells: 1));

        self::assertSame(ConfidenceLevel::Unverified, $result->level);
        self::assertLessThan(ConfidenceCalculator::T_LIKELY, $result->score);
    }

    #[Test]
    public function severalIndependentReportsBecomeLikely(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4));

        self::assertSame(ConfidenceLevel::Likely, $result->level);
        self::assertGreaterThan(0.35, $result->score);
        self::assertLessThan(0.60, $result->score);
    }

    #[Test]
    public function crowdAgreementRaisesConfidenceToHigh(): void
    {
        $before = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4));
        $after = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4, yes: 30, no: 3));

        self::assertGreaterThan($before->score, $after->score);
        self::assertSame(ConfidenceLevel::High, $after->level);
    }

    #[Test]
    public function crowdDisagreementLowersConfidence(): void
    {
        $before = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4));
        $after = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4, yes: 2, no: 25));

        self::assertLessThan($before->score, $after->score);
        self::assertSame(ConfidenceLevel::Unverified, $after->level);
    }

    #[Test]
    public function communityAloneNeverReachesConfirmed(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 50.0, reportCount: 50, cells: 20, yes: 200, no: 2));

        self::assertSame(ConfidenceLevel::High, $result->level);
    }

    #[Test]
    public function officialSourceUnlocksConfirmed(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4, yes: 30, no: 3, external: 0.9, externalCount: 1));

        self::assertSame(ConfidenceLevel::Confirmed, $result->level);
        self::assertGreaterThanOrEqual(ConfidenceCalculator::T_CONFIRMED, $result->score);
    }

    #[Test]
    public function weakExternalSourceDoesNotUnlockConfirmed(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 20.0, reportCount: 20, cells: 10, yes: 60, no: 2, external: 0.3, externalCount: 1));

        self::assertSame(ConfidenceLevel::High, $result->level);
    }

    #[Test]
    public function staleEvidenceDecays(): void
    {
        $fresh = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4, yes: 30, no: 3, minutes: 0));
        $old = $this->calculator->calculate($this->input(weightedReports: 5.0, reportCount: 5, cells: 4, yes: 30, no: 3, minutes: 360));

        self::assertLessThan($fresh->score, $old->score);
        self::assertEqualsWithDelta($fresh->score * exp(-2), $old->score, 0.001);
    }

    #[Test]
    public function breakdownIsExplainable(): void
    {
        $result = $this->calculator->calculate($this->input(weightedReports: 3.0, reportCount: 3, cells: 2, yes: 4, no: 1));

        self::assertArrayHasKey('components', $result->breakdown);
        self::assertSame(['reports', 'spread', 'crowd', 'freshness', 'external'], array_keys($result->breakdown['components']));
        self::assertSame($result->level->value, $result->breakdown['level']);
    }

    #[Test]
    public function scoreIsBoundedToUnitInterval(): void
    {
        $max = $this->calculator->calculate($this->input(weightedReports: 1000.0, reportCount: 1000, cells: 500, yes: 5000, no: 0, external: 1.0, externalCount: 5));
        $min = $this->calculator->calculate($this->input(weightedReports: 0.0, reportCount: 0, cells: 0, yes: 0, no: 500));

        self::assertSame(1.0, $max->score);
        self::assertSame(0.0, $min->score);
    }

    private function input(float $weightedReports, int $reportCount, int $cells, int $yes = 0, int $no = 0, float $minutes = 0.0, float $external = 0.0, int $externalCount = 0): ConfidenceInput
    {
        return new ConfidenceInput(
            weightedReports: $weightedReports,
            reportCount: $reportCount,
            distinctReportCells: $cells,
            yes: $yes,
            no: $no,
            minutesSinceLastActivity: $minutes,
            bestExternalCredibility: $external,
            externalSourceCount: $externalCount,
        );
    }
}
