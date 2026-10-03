<?php

declare(strict_types=1);

namespace App\Tests\Unit\Incident;

use App\Incident\Dto\IncidentSearchCriteria;
use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Enum\IncidentStatus;
use App\Reporting\Enum\ReportType;
use PHPUnit\Framework\TestCase;

final class IncidentSearchCriteriaTest extends TestCase
{
    public function testEmptyQueryMeansOpenIncidentsOnly(): void
    {
        $c = IncidentSearchCriteria::fromStrings(null, '', null, false);

        self::assertTrue($c->isEmpty());
        self::assertFalse($c->includeResolved);
        self::assertNull($c->status);
        self::assertNull($c->type);
        self::assertNull($c->level);
    }

    public function testParsesKnownEnumValues(): void
    {
        $c = IncidentSearchCriteria::fromStrings('active', 'power_outage', 'high', false);

        self::assertSame(IncidentStatus::Active, $c->status);
        self::assertSame(ReportType::PowerOutage, $c->type);
        self::assertSame(ConfidenceLevel::High, $c->level);
        self::assertFalse($c->isEmpty());
    }

    public function testUnknownValuesAreIgnoredInsteadOfFailing(): void
    {
        $c = IncidentSearchCriteria::fromStrings('bogus', 'nope', 'whatever', false);

        self::assertTrue($c->isEmpty());
    }

    public function testAskingForResolvedStatusImpliesIncludeResolved(): void
    {
        $c = IncidentSearchCriteria::fromStrings('resolved', null, null, false);

        self::assertSame(IncidentStatus::Resolved, $c->status);
        self::assertTrue($c->includeResolved);
    }

    public function testLimitIsClamped(): void
    {
        self::assertSame(1, IncidentSearchCriteria::fromStrings(null, null, null, false, 0)->limit);
        self::assertSame(500, IncidentSearchCriteria::fromStrings(null, null, null, false, 10_000)->limit);
    }
}
