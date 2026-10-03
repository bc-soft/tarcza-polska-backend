<?php

declare(strict_types=1);

namespace App\Tests\Unit\Incident;

use App\Incident\Enum\IncidentEventType;
use App\Incident\Model\TimelineEntry;
use App\Incident\View\IncidentTimelineView;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncidentTimelineViewTest extends TestCase
{
    #[Test]
    public function everyEventTypeHasAPolishLabel(): void
    {
        foreach (IncidentEventType::cases() as $type) {
            self::assertNotSame('', $type->label());
        }
    }

    #[Test]
    public function onlyRawReportAttachmentsAreHiddenFromCitizens(): void
    {
        self::assertFalse(IncidentEventType::ReportAttached->isPublic());
        self::assertTrue(IncidentEventType::AreaChanged->isPublic());
        self::assertTrue(IncidentEventType::ConfidenceChanged->isPublic());
        self::assertTrue(IncidentEventType::Resolved->isPublic());
    }

    #[Test]
    public function viewSerialisesTypeLabelTimeAndDetails(): void
    {
        $at = new DateTimeImmutable('2026-10-03T14:44:11+02:00');
        $entry = new TimelineEntry(IncidentEventType::ConfidenceChanged, $at, ['from' => 'likely', 'to' => 'high', 'score' => 0.612]);

        $row = IncidentTimelineView::toArray($entry);

        self::assertSame('confidence_changed', $row['type']);
        self::assertSame('Zmienił się poziom wiarygodności', $row['label']);
        self::assertSame('2026-10-03T14:44:11+02:00', $row['at']);
        self::assertSame(['from' => 'likely', 'to' => 'high', 'score' => 0.612], $row['details']);
        self::assertCount(1, IncidentTimelineView::list([$entry]));
    }
}
