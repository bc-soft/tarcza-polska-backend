<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guidance;

use App\Guidance\Model\Procedure;
use App\Guidance\Service\ProcedureCatalog;
use App\Reporting\Enum\ReportType;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProcedureCatalogTest extends TestCase
{
    #[Test]
    public function everyReportTypeHasAtLeastOneDedicatedProcedure(): void
    {
        $catalog = new ProcedureCatalog();
        foreach (ReportType::cases() as $type) {
            $dedicated = array_filter($catalog->forType($type), static fn (Procedure $p) => \in_array($type, $p->appliesTo, true));
            self::assertNotEmpty($dedicated, $type->value.' has no procedure');
        }
    }

    #[Test]
    public function proceduresAreNonEmptyUniqueAndSortedByPriority(): void
    {
        $all = new ProcedureCatalog()->all();
        $ids = array_map(static fn (Procedure $p) => $p->id, $all);

        self::assertSame($ids, array_values(array_unique($ids)));
        foreach ($all as $p) {
            self::assertNotEmpty($p->steps, $p->id);
            self::assertNotSame('', $p->title);
        }
        $priorities = array_map(static fn (Procedure $p) => $p->priority, $all);
        $sorted = $priorities;
        rsort($sorted);
        self::assertSame($sorted, $priorities);
    }

    #[Test]
    public function typeFilterKeepsGeneralProcedures(): void
    {
        $forPower = new ProcedureCatalog()->forType(ReportType::PowerOutage);
        $ids = array_map(static fn (Procedure $p) => $p->id, $forPower);

        self::assertContains('power-outage', $ids);
        self::assertContains('no-network', $ids);
        self::assertNotContains('water-outage', $ids);
    }

    #[Test]
    public function boundingBoxAroundPointCoversTheRadius(): void
    {
        $center = new Point(52.4121, 16.9012);
        $bbox = BoundingBox::around($center, 15000);

        self::assertLessThan($center->lat, $bbox->minLat);
        self::assertGreaterThan($center->lat, $bbox->maxLat);
        self::assertEqualsWithDelta(15000, new Point($bbox->maxLat, $center->lng)->distanceTo($center), 200);
        self::assertEqualsWithDelta(15000, new Point($center->lat, $bbox->maxLng)->distanceTo($center), 200);
    }
}
