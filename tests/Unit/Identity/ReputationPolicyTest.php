<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\Device;
use App\Identity\Service\ReputationPolicy;
use App\Incident\Enum\CellState;
use App\Incident\Enum\IncidentResolution;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReputationPolicyTest extends TestCase
{
    private ReputationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ReputationPolicy();
    }

    #[Test]
    public function reportersGainOnConfirmedAndLoseOnFalseAlarm(): void
    {
        self::assertGreaterThan(0, $this->policy->reporterDelta(IncidentResolution::Confirmed));
        self::assertLessThan(0, $this->policy->reporterDelta(IncidentResolution::FalseAlarm));
        self::assertSame(0.0, $this->policy->reporterDelta(IncidentResolution::Expired));
        self::assertGreaterThan(
            $this->policy->reporterDelta(IncidentResolution::Confirmed),
            -$this->policy->reporterDelta(IncidentResolution::FalseAlarm),
            'a false alarm must cost more than a confirmation earns',
        );
    }

    #[Test]
    public function answersAreJudgedAgainstTheFinalCellState(): void
    {
        $confirmed = IncidentResolution::Confirmed;

        self::assertSame(ReputationPolicy::ANSWER_CONSISTENT, $this->policy->answerDelta('yes', CellState::Positive, $confirmed));
        self::assertSame(ReputationPolicy::ANSWER_CONSISTENT, $this->policy->answerDelta('no', CellState::Negative, $confirmed));
        self::assertSame(ReputationPolicy::ANSWER_INCONSISTENT, $this->policy->answerDelta('yes', CellState::Negative, $confirmed));
        self::assertSame(ReputationPolicy::ANSWER_INCONSISTENT, $this->policy->answerDelta('no', CellState::Positive, $confirmed));
    }

    #[Test]
    public function unknownAnswersUndecidedCellsAndExpiryAreNeutral(): void
    {
        self::assertSame(0.0, $this->policy->answerDelta('unknown', CellState::Positive, IncidentResolution::Confirmed));
        self::assertSame(0.0, $this->policy->answerDelta(null, CellState::Positive, IncidentResolution::Confirmed));
        self::assertSame(0.0, $this->policy->answerDelta('yes', CellState::Unknown, IncidentResolution::Confirmed));
        self::assertSame(0.0, $this->policy->answerDelta('yes', CellState::Positive, IncidentResolution::Expired));
    }

    #[Test]
    public function falseAlarmOverrulesTheCrowd(): void
    {
        // The cell looked positive, but the operator declared a false alarm: saying "problem present" was wrong.
        self::assertSame(ReputationPolicy::ANSWER_INCONSISTENT, $this->policy->answerDelta('yes', CellState::Positive, IncidentResolution::FalseAlarm));
        self::assertSame(ReputationPolicy::ANSWER_CONSISTENT, $this->policy->answerDelta('no', CellState::Positive, IncidentResolution::FalseAlarm));
    }

    #[Test]
    public function deviceReputationStaysWithinBounds(): void
    {
        $device = new Device();
        for ($i = 0; $i < 50; ++$i) {
            $device->adjustReputation(ReputationPolicy::REPORTER_CONFIRMED);
        }
        self::assertSame(2.0, $device->getReputation());
        for ($i = 0; $i < 50; ++$i) {
            $device->adjustReputation(ReputationPolicy::REPORTER_FALSE_ALARM);
        }
        self::assertSame(0.0, $device->getReputation());
    }
}
