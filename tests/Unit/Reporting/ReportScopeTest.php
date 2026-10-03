<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reporting;

use App\Reporting\Enum\ReportScope;
use App\Reporting\Enum\ReportType;
use App\Shared\Poi\PoiKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReportScopeTest extends TestCase
{
    #[Test]
    public function fuelAndShelterReportsArePointScopedEverythingElseIsArea(): void
    {
        self::assertSame(ReportScope::Point, ReportType::FuelShortage->scope());
        self::assertSame(PoiKind::FuelStation, ReportType::FuelShortage->poiKind());
        self::assertSame(ReportScope::Point, ReportType::ShelterIssue->scope());
        self::assertSame(PoiKind::Shelter, ReportType::ShelterIssue->poiKind());

        foreach ([ReportType::PowerOutage, ReportType::WaterOutage, ReportType::RoadBlocked, ReportType::OtherThreat] as $type) {
            self::assertSame(ReportScope::Area, $type->scope(), $type->value);
            self::assertNull($type->poiKind(), $type->value);
        }
    }

    #[Test]
    public function pointQuestionsNameTheObjectAndTheFuel(): void
    {
        self::assertSame(
            'Czy na stacji Orlen Jeżyce jest teraz dostępne paliwo: Olej napędowy, LPG?',
            ReportType::FuelShortage->pointVerificationQuestion('Orlen Jeżyce', 'Olej napędowy, LPG'),
        );
        self::assertStringContainsString('Orlen Jeżyce', ReportType::FuelShortage->pointVerificationQuestion('Orlen Jeżyce'));
        self::assertStringContainsString('schron Rynek Jeżycki', ReportType::ShelterIssue->pointVerificationQuestion('Rynek Jeżycki'));
        self::assertSame(ReportType::PowerOutage->verificationQuestion(), ReportType::PowerOutage->pointVerificationQuestion('x'));
    }

    #[Test]
    public function yesStillMeansNoProblemForPointTypes(): void
    {
        // "Is there fuel?" / "Is the shelter accessible?" -> YES = problem absent.
        self::assertFalse(ReportType::FuelShortage->yesMeansProblemPresent());
        self::assertFalse(ReportType::ShelterIssue->yesMeansProblemPresent());
    }

    #[Test]
    public function poiKindRadiiAreSane(): void
    {
        foreach (PoiKind::cases() as $kind) {
            self::assertGreaterThan($kind->presenceRadiusMeters(), $kind->snapRadiusMeters());
            self::assertGreaterThan($kind->snapRadiusMeters(), $kind->verificationRadiusMeters());
            self::assertNotSame('', $kind->label());
        }
    }
}
