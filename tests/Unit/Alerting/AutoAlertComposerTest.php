<?php

declare(strict_types=1);

namespace App\Tests\Unit\Alerting;

use App\Alerting\Entity\Alert;
use App\Alerting\Service\AutoAlertComposer;
use App\Incident\Entity\Incident;
use App\Incident\Enum\ConfidenceLevel;
use App\Reporting\Enum\ReportType;
use App\Shared\Geo\Point;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AutoAlertComposerTest extends TestCase
{
    #[Test]
    public function confirmedIncidentProducesWarningWithAgreementShare(): void
    {
        $incident = new Incident(ReportType::PowerOutage, new Point(52.41, 16.9), '891e24aa0b3ffff');
        $incident->updateConfidence(0.9, ConfidenceLevel::Confirmed, []);
        $cell = $incident->cell('891e24aa0b3ffff', 0);
        $cell->addReport();
        $cell->addAnswer('yes');
        $cell->addAnswer('yes');
        $cell->addAnswer('yes');
        $cell->addAnswer('no');

        $request = new AutoAlertComposer()->compose($incident);

        self::assertSame('Potwierdzono: brak prądu w Twojej okolicy', $request->title);
        self::assertStringContainsString('75%', $request->body);
        self::assertStringContainsString('Brak prądu', $request->body);
        self::assertSame(Alert::SEVERITY_WARNING, $request->severity);
        self::assertSame($incident->getId()->toRfc4122(), $request->incidentId);
        self::assertNull($request->area);
        self::assertSame(AutoAlertComposer::TTL_MINUTES, $request->ttlMinutes);
    }

    #[Test]
    public function highButUnconfirmedIncidentIsInformational(): void
    {
        $incident = new Incident(ReportType::RoadBlocked, new Point(52.41, 16.9), '891e24aa0b3ffff');
        $incident->updateConfidence(0.65, ConfidenceLevel::High, []);
        $incident->cell('891e24aa0b3ffff', 0)->addReport();

        $request = new AutoAlertComposer()->compose($incident);

        self::assertStringStartsWith('Prawdopodobnie:', $request->title);
        self::assertSame(Alert::SEVERITY_INFO, $request->severity);
        self::assertStringContainsString('zgłosiło 1 osób', $request->body);
    }

    #[Test]
    public function everyTypeHasAdvice(): void
    {
        foreach (ReportType::cases() as $type) {
            self::assertNotSame('', AutoAlertComposer::advice($type));
        }
    }
}
